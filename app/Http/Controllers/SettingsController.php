<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBusinessRequest;
use App\Models\Business;
use App\Models\BusinessInvitation;
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
            ],
            'supportedLocales' => config('app.supported_locales'),
        ]);
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:'.implode(',', array_keys(config('app.supported_locales')))],
            'timezone' => ['nullable', 'string', 'in:'.implode(',', timezone_identifiers_list())],
        ]);

        $request->user()->forceFill($validated)->save();

        return back()->with('success', __('flash.settings.preferences_updated'));
    }

    public function businesses(Request $request): Response
    {
        $business = Business::findOrFail(BusinessContext::currentId());
        $canManageMembers = Gate::allows('business.edit_settings', $business);

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

        return Inertia::render('Settings/Businesses', [
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'slug' => $business->slug,
                'logoUrl' => $this->images->url($business, 'logo'),
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
            'countries' => Countries::all(),
            'members' => $members,
            'pendingInvitations' => $pendingInvitations,
            'can' => [
                'manageMembers' => $canManageMembers,
                'invite' => Gate::allows('membership.invite', [$business, 'staff']),
                'inviteOwner' => Gate::allows('membership.invite', [$business, 'owner']),
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
