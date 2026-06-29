<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Bin;
use App\Models\Business;
use App\Models\Location;
use App\Models\Membership;
use App\Models\StockLevel;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\BusinessFrozen;
use App\Notifications\BusinessThawed;
use App\Scopes\BusinessScope;
use App\Support\Countries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AdminBusinessController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,frozen,deleted,onboarded'],
            'plan' => ['nullable', 'in:solo,store,enterprise'],
            'sort' => ['nullable', 'in:name,created_at,members,inventory_skus,inventory_bags,onboarded_at'],
            'dir' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'in:25,50,100,all'],
        ]);

        // For each business, we need to count members and calculate inventory stats.
        // Members count: non-deleted memberships
        // Inventory: distinct SKUs + total bags across all bins
        $membersCount = '(select count(*) from memberships m '
            .'where m.business_id = businesses.id and m.deleted_at is null)';

        $inventorySkus = '(select count(distinct sl.sku_id) from stock_levels sl '
            .'where sl.deleted_at is null and sl.business_id = businesses.id)';

        $inventoryBags = '(select coalesce(sum(sl.full_bags + sl.open_bags), 0) from stock_levels sl '
            .'where sl.deleted_at is null and sl.business_id = businesses.id)';

        // Primary owner: earliest-joined member with the owner role (for the
        // "Email owner" / "Switch to owner" actions). Portable, no FIELD().
        $ownerUserId = '(select m.user_id from memberships m '
            ."where m.business_id = businesses.id and m.role = 'owner' and m.deleted_at is null "
            .'order by m.joined_at asc limit 1)';

        $query = Business::withTrashed()
            ->select('businesses.*')
            ->addSelect(DB::raw("{$membersCount} as members_count"))
            ->addSelect(DB::raw("{$inventorySkus} as inventory_skus_count"))
            ->addSelect(DB::raw("{$inventoryBags} as inventory_bags_total"))
            ->addSelect(DB::raw("{$ownerUserId} as owner_user_id"));

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('slug', 'like', "%{$term}%")
                ->orWhere('contact_email', 'like', "%{$term}%"));
        }

        match ($request->input('status')) {
            'active' => $query->whereNull('deleted_at')->whereNull('frozen_at'),
            'frozen' => $query->whereNotNull('frozen_at')->whereNull('deleted_at'),
            'deleted' => $query->whereNotNull('deleted_at'),
            'onboarded' => $query->whereNotNull('onboarding_completed_at')->whereNull('deleted_at'),
            default => null,
        };

        if ($request->filled('plan')) {
            $query->where('plan', $request->input('plan'));
        }

        $sort = $request->input('sort', 'created_at');
        $dir = $request->input('dir') === 'asc' ? 'asc' : 'desc';

        match ($sort) {
            'name' => $query->orderBy('name', $dir),
            'created_at' => $query->orderBy('created_at', $dir),
            'members' => $query->orderBy('members_count', $dir),
            'inventory_skus' => $query->orderBy('inventory_skus_count', $dir),
            'inventory_bags' => $query->orderBy('inventory_bags_total', $dir),
            'onboarded_at' => $query->orderBy('onboarding_completed_at', $dir),
            default => $query->orderBy('created_at', 'desc'),
        };

        $perPageInput = $request->input('per_page', '50');
        $perPage = $perPageInput === 'all' ? 1000000 : (int) $perPageInput;

        $businesses = $query->paginate($perPage)->withQueryString();

        $businesses->through(fn (Business $business) => [
            'id' => $business->id,
            'name' => $business->name,
            'slug' => $business->slug,
            'plan' => $business->plan->value,
            'logo_url' => $business->logo_path
                ? Storage::disk('public')->url($business->logo_path)
                : asset('images/defaults/balloon-company-logo-light-default.png'),
            'members_count' => (int) $business->members_count,
            'inventory_skus_count' => (int) $business->inventory_skus_count,
            'inventory_bags_total' => (int) $business->inventory_bags_total,
            'owner_id' => $business->owner_user_id,
            'onboarded_at' => $business->onboarding_completed_at,
            'created_at' => $business->created_at,
            'frozen_at' => $business->frozen_at,
            'deleted_at' => $business->deleted_at,
        ]);

        // NOTE: prop is named businessList (not "businesses") to avoid clobbering
        // the globally-shared "businesses" prop (the current user's memberships,
        // used by BusinessSwitcher). Same reason "business" → "record" in show().
        return Inertia::render('SuperAdmin/Businesses/Index', [
            'businessList' => $businesses,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', ''),
                'plan' => $request->input('plan', ''),
                'sort' => $sort,
                'dir' => $dir,
                'per_page' => $perPageInput,
            ],
        ]);
    }

    /**
     * A single business's detail/support view. Resolves trashed businesses so support
     * can inspect deleted accounts.
     */
    public function show(string $business): Response
    {
        $model = Business::withTrashed()->findOrFail($business);

        // Members — bypass the tenant scope so this works regardless of the admin's
        // current business context. Owners first, then by join date.
        $members = Membership::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $model->id)
            ->whereNull('deleted_at')
            ->with('user:id,name,email,avatar_path')
            ->orderByRaw("CASE WHEN role = 'owner' THEN 0 ELSE 1 END")
            ->orderBy('joined_at', 'asc')
            ->get()
            ->map(fn (Membership $m) => [
                'id' => $m->id,
                'user_id' => $m->user?->id,
                'name' => $m->user?->name,
                'email' => $m->user?->email,
                'avatar_url' => $m->user?->avatar_path
                    ? Storage::disk('public')->url($m->user->avatar_path)
                    : asset('images/defaults/user-profile-default.png'),
                'role' => $m->role,
                'joined_at' => $m->joined_at,
            ])
            ->values();

        // Pending invitations
        $pendingInvitations = $model->businessInvitations()
            ->whereNull('responded_at')
            ->get(['id', 'invited_email', 'invited_user_id', 'role', 'expires_at'])
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'invited_email' => $inv->invited_email,
                'role' => $inv->role,
                'expires_at' => $inv->expires_at,
            ])
            ->values();

        // Inventory stats — StockLevel/Location/Bin all carry the BusinessScope,
        // so every count must bypass it AND filter explicitly by this business.
        // Otherwise they'd be scoped to the ADMIN's current business and report
        // zero/garbage for any other business.
        $inventorySkus = StockLevel::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $model->id)
            ->distinct('sku_id')
            ->count('sku_id');

        $inventoryBags = (int) StockLevel::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $model->id)
            ->sum(DB::raw('full_bags + open_bags'));

        $locationsCount = Location::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $model->id)
            ->count();

        $binsCount = Bin::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $model->id)
            ->count();

        // Support tickets from members of this business
        $memberIds = Membership::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $model->id)
            ->whereNull('deleted_at')
            ->pluck('user_id')
            ->toArray();

        $tickets = SupportTicket::whereIn('user_id', $memberIds)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'user_name', 'subject', 'archived_at', 'created_at']);

        return Inertia::render('SuperAdmin/Businesses/Show', [
            'record' => [
                'id' => $model->id,
                'name' => $model->name,
                'slug' => $model->slug,
                'plan' => $model->plan->value,
                'business_type' => $model->business_type,
                'logo_url' => $model->logo_path
                    ? Storage::disk('public')->url($model->logo_path)
                    : asset('images/defaults/balloon-company-logo-light-default.png'),
                'created_at' => $model->created_at,
                'onboarding_completed_at' => $model->onboarding_completed_at,
                'frozen_at' => $model->frozen_at,
                'deleted_at' => $model->deleted_at,
                'phone' => $model->phone,
                'address_line1' => $model->address_line1,
                'address_line2' => $model->address_line2,
                'city' => $model->city,
                'state_region' => $model->state_region,
                'postal_code' => $model->postal_code,
                'country' => $model->country ? Countries::name($model->country) : null,
                'website_url' => $model->website_url,
                'website_url_2' => $model->website_url_2,
                'contact_email' => $model->contact_email,
                'owner_id' => $model->owner()?->id,
            ],
            'members' => $members,
            'members_count' => count($members),
            'pending_invitations' => $pendingInvitations,
            'inventory_skus_count' => $inventorySkus,
            'inventory_bags_total' => $inventoryBags,
            'locations_count' => $locationsCount,
            'bins_count' => $binsCount,
            'tickets' => $tickets,
        ]);
    }

    /**
     * Suspend a business. Any admin may freeze a business; the frozen business's
     * members cannot transact (enforced by business-scoped request middleware).
     */
    public function suspend(Request $request, Business $business): RedirectResponse
    {
        $business->frozen_at = now();
        $business->save();

        foreach ($this->owners($business) as $owner) {
            $owner->notify(new BusinessFrozen($business));
        }

        return back()->with('success', __('flash.businesses.suspended', ['name' => $business->name]));
    }

    /**
     * Unsuspend a business.
     */
    public function thaw(Business $business): RedirectResponse
    {
        $business->frozen_at = null;
        $business->save();

        foreach ($this->owners($business) as $owner) {
            $owner->notify(new BusinessThawed($business));
        }

        return back()->with('success', __('flash.businesses.unsuspended', ['name' => $business->name]));
    }

    /**
     * Soft-delete (prune) a business. Super-Admin-only.
     */
    public function destroy(Request $request, Business $business): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $business->delete();

        return back()->with('success', __('flash.businesses.deleted', ['name' => $business->name]));
    }

    /**
     * The owner users of a business, for suspension notifications. Bypasses the
     * tenant scope so it resolves regardless of the admin's current business
     * context (Membership carries BusinessScope).
     *
     * @return Collection<int, User>
     */
    private function owners(Business $business): Collection
    {
        return Membership::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $business->id)
            ->where('role', 'owner')
            ->whereNull('deleted_at')
            ->with('user')
            ->get()
            ->map(fn (Membership $m) => $m->user)
            ->filter()
            ->values();
    }
}
