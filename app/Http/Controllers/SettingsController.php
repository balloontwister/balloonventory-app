<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBusinessRequest;
use App\Models\Business;
use App\Models\BusinessDistributor;
use App\Models\BusinessInvitation;
use App\Models\Distributor;
use App\Models\Membership;
use App\Scopes\BusinessScope;
use App\Services\ImageAttachmentService;
use App\Support\BusinessContext;
use App\Support\Countries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(private readonly ImageAttachmentService $images) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Settings/Index', [
            'preferences' => [
                'locale' => $user->locale ?? 'en',
                'timezone' => $user->timezone,
                'theme' => $user->theme ?? 'system',
            ],
            'supportedLocales' => config('app.supported_locales'),
        ]);
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:'.implode(',', array_keys(config('app.supported_locales')))],
            'timezone' => ['nullable', 'string', 'in:'.implode(',', timezone_identifiers_list())],
            'theme' => ['required', 'string', 'in:light,dark,system'],
        ]);

        $request->user()->forceFill($validated)->save();

        return back()->with('success', __('flash.settings.preferences_updated'));
    }

    public function businesses(Request $request): Response
    {
        $business = Business::findOrFail(BusinessContext::currentId());
        $canManageMembers = Gate::allows('business.edit_settings', $business);

        // The viewer's own membership in the current business. Drives "Leave this
        // business": any member may leave, except a sole owner (who must transfer
        // ownership or delete instead — the leave endpoint enforces this too).
        $ownMembership = Membership::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $business->id)
            ->where('user_id', $request->user()->id)
            ->whereNull('deleted_at')
            ->first();

        $canLeave = false;
        if ($ownMembership) {
            if ($ownMembership->role !== 'owner') {
                $canLeave = true;
            } else {
                $ownerCount = Membership::withoutGlobalScope(BusinessScope::class)
                    ->where('business_id', $business->id)
                    ->where('role', 'owner')
                    ->whereNull('deleted_at')
                    ->count();
                $canLeave = $ownerCount > 1;
            }
        }

        $members = [];
        $pendingInvitations = [];

        if ($canManageMembers) {
            $members = Membership::withoutGlobalScope(BusinessScope::class)
                ->with('user:id,name,email')
                ->where('business_id', $business->id)
                ->whereNull('deleted_at')
                ->get()
                ->map(fn (Membership $m) => [
                    'id' => $m->id,
                    'user_id' => $m->user_id,
                    'name' => $m->user->name,
                    'email' => $m->user->email,
                    'role' => $m->role,
                    'is_self' => $m->user_id === $request->user()->id,
                ])
                ->values()
                ->all();

            $pendingInvitations = BusinessInvitation::withoutGlobalScope(BusinessScope::class)
                ->with('inviter:id,name')
                ->where('business_id', $business->id)
                ->where('status', BusinessInvitation::STATUS_PENDING)
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->get()
                ->map(fn (BusinessInvitation $inv) => [
                    'id' => $inv->id,
                    'invited_email' => $inv->invited_email,
                    'role' => $inv->role,
                    'inviter_name' => $inv->inviter->name,
                    'expires_at' => $inv->expires_at?->toISOString(),
                ])
                ->values()
                ->all();
        }

        $preferredDistributorIds = BusinessDistributor::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $business->id)
            ->where('is_enabled', true)
            ->pluck('distributor_id')
            ->flip();

        $distributors = Distributor::active()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Distributor $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'slug' => $d->slug,
                'sort_order' => $d->sort_order,
                'enabled' => $preferredDistributorIds->has($d->id),
            ])
            ->values()
            ->all();

        return Inertia::render('Settings/Businesses', [
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'slug' => $business->slug,
                'logoUrl' => $this->images->url($business, 'logo'),
                'color' => $business->color,
                'phone' => $business->phone,
                'address_line1' => $business->address_line1,
                'address_line2' => $business->address_line2,
                'city' => $business->city,
                'state_region' => $business->state_region,
                'postal_code' => $business->postal_code,
                'country' => $business->country,
                'website_url' => $business->website_url,
                'website_url_2' => $business->website_url_2,
                'contact_email' => $business->contact_email,
            ],
            'distributors' => $distributors,
            'countries' => Countries::all(),
            'members' => $members,
            'pendingInvitations' => $pendingInvitations,
            'ownMembershipId' => $ownMembership?->id,
            'can' => [
                'manageMembers' => $canManageMembers,
                'invite' => Gate::allows('membership.invite', [$business, 'staff']),
                'inviteOwner' => Gate::allows('membership.invite', [$business, 'owner']),
                'leave' => $canLeave,
                // Seam for membership-tier gating later; open to all users for now.
                'createBusiness' => true,
            ],
        ]);
    }

    public function updateBusinessLogo(Request $request): RedirectResponse
    {
        $business = Business::findOrFail(BusinessContext::currentId());

        Gate::authorize('business.manage_logo', $business);

        $request->validate([
            'logo' => ['nullable', 'mimes:png,jpg,jpeg,webp,svg', 'max:5120'],
        ]);

        if ($request->hasFile('logo')) {
            $this->images->set($business, 'logo', $request->file('logo'));
        } elseif ($request->boolean('logo_clear')) {
            $this->images->clear($business, 'logo');
        }

        return back()->with('success', __('flash.settings.business_logo_updated'));
    }

    /**
     * Update the business's accent color. This is a business-level property that
     * every member sees (and which changes as the user switches businesses), so
     * it is gated by business.edit_settings like the rest of the business profile.
     */
    public function updateBusinessColor(Request $request): RedirectResponse
    {
        $business = Business::findOrFail(BusinessContext::currentId());

        Gate::authorize('business.edit_settings', $business);

        $validated = $request->validate([
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $business->update(['color' => $validated['color']]);

        return back()->with('success', __('flash.settings.business_color_updated'));
    }

    public function updateBusiness(UpdateBusinessRequest $request): RedirectResponse
    {
        $business = Business::findOrFail(BusinessContext::currentId());

        Gate::authorize('business.edit_settings', $business);

        $validated = $request->validated();

        $business->update([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name'], $business->id),
            'phone' => $validated['phone'] ?? null,
            'address_line1' => $validated['address_line1'] ?? null,
            'address_line2' => $validated['address_line2'] ?? null,
            'city' => $validated['city'] ?? null,
            'state_region' => $validated['state_region'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'country' => $validated['country'] ?? null,
            'website_url' => $validated['website_url'] ?? null,
            'website_url_2' => $validated['website_url_2'] ?? null,
            'contact_email' => $validated['contact_email'] ?? null,
        ]);

        return back()->with('success', __('flash.settings.business_name_updated'));
    }

    public function updateDistributors(Request $request): RedirectResponse
    {
        $business = Business::findOrFail(BusinessContext::currentId());

        Gate::authorize('business.edit_settings', $business);

        $validated = $request->validate([
            'distributor_ids' => ['nullable', 'array'],
            'distributor_ids.*' => ['string', 'exists:distributors,id'],
        ]);

        $ids = $validated['distributor_ids'] ?? [];

        // Disable everything not selected. This is a query-builder mass update,
        // so it carries a proper WHERE.
        BusinessDistributor::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $business->id)
            ->whereNotIn('distributor_id', $ids)
            ->update(['is_enabled' => false]);

        // Enable (or insert) the selected ones via upsert. We must NOT use an
        // instance save()/updateOrCreate() here: business_distributors has a
        // composite primary key and the model declares $primaryKey = null, so a
        // dirty instance update emits an UPDATE with no WHERE and clobbers every
        // business's preferences. upsert() keys on the composite columns.
        if ($ids !== []) {
            BusinessDistributor::upsert(
                collect($ids)->map(fn (string $distributorId) => [
                    'business_id' => $business->id,
                    'distributor_id' => $distributorId,
                    'is_enabled' => true,
                ])->all(),
                ['business_id', 'distributor_id'],
                ['is_enabled'],
            );
        }

        return back()->with('success', __('flash.settings.distributors_updated'));
    }

    private function uniqueSlug(string $name, string $excludeId): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;

        while (Business::where('slug', $slug)->where('id', '!=', $excludeId)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
