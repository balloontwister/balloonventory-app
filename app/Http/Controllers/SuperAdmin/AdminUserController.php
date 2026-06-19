<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\AdminLevel;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\User;
use App\Scopes\BusinessScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,frozen,deleted,admins'],
            'sort' => ['nullable', 'in:name,email,admin_level,created_at,last_login_at,inventory,activity,businesses'],
            'dir' => ['nullable', 'in:asc,desc'],
        ]);

        // Inventory is per-business; a user reaches it through their (non-scoped)
        // memberships. These correlated subqueries deliberately bypass the
        // BelongsToBusiness scope so the admin sees totals across ALL of a user's
        // businesses, not just the one they're currently acting in.
        $businessIdsForUser = 'select m.business_id from memberships m '
            .'where m.user_id = users.id and m.deleted_at is null';

        $inventorySkus = '(select count(distinct sl.sku_id) from stock_levels sl '
            ."where sl.deleted_at is null and sl.business_id in ({$businessIdsForUser}))";

        $inventoryBags = '(select coalesce(sum(sl.full_bags + sl.open_bags), 0) from stock_levels sl '
            ."where sl.deleted_at is null and sl.business_id in ({$businessIdsForUser}))";

        $query = User::withTrashed()
            ->select('users.*')
            ->addSelect(DB::raw("{$inventorySkus} as inventory_skus_count"))
            ->addSelect(DB::raw("{$inventoryBags} as inventory_bags_total"))
            ->withCount([
                'supportTickets',
                'skuFeedback',
                'memberships' => fn ($q) => $q->withoutGlobalScope(BusinessScope::class),
            ])
            ->with([
                'memberships' => fn ($q) => $q->withoutGlobalScope(BusinessScope::class)
                    ->with('business:id,name'),
            ]);

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('original_email', 'like', "%{$term}%"));
        }

        match ($request->input('status')) {
            'active' => $query->whereNull('deleted_at')->whereNull('frozen_at'),
            'frozen' => $query->whereNotNull('frozen_at')->whereNull('deleted_at'),
            'deleted' => $query->whereNotNull('deleted_at'),
            'admins' => $query->whereNotNull('admin_level'),
            default => null,
        };

        $sort = $request->input('sort', 'last_login_at');
        $dir = $request->input('dir') === 'asc' ? 'asc' : 'desc';

        match ($sort) {
            'name' => $query->orderBy('name', $dir),
            'email' => $query->orderBy('email', $dir),
            'admin_level' => $query->orderBy('admin_level', $dir),
            'created_at' => $query->orderBy('created_at', $dir),
            'inventory' => $query->orderBy('inventory_skus_count', $dir),
            'activity' => $query->orderByRaw("(support_tickets_count + sku_feedback_count) {$dir}"),
            'businesses' => $query->orderBy('memberships_count', $dir),
            default => $query->orderBy('last_login_at', $dir),
        };

        $users = $query->paginate(50)->withQueryString();

        $users->through(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'original_email' => $user->original_email,
            'email_verified_at' => $user->email_verified_at,
            'admin_level' => $user->admin_level,
            'created_at' => $user->created_at,
            'last_login_at' => $user->last_login_at,
            'frozen_at' => $user->frozen_at,
            'deleted_at' => $user->deleted_at,
            'inventory_skus_count' => (int) $user->inventory_skus_count,
            'inventory_bags_total' => (int) $user->inventory_bags_total,
            'support_tickets_count' => (int) $user->support_tickets_count,
            'sku_feedback_count' => (int) $user->sku_feedback_count,
            'businesses' => $user->memberships
                ->map(fn (Membership $m) => [
                    'id' => $m->business?->id,
                    'name' => $m->business?->name,
                    'role' => $m->role,
                ])
                ->filter(fn (array $b) => $b['name'] !== null)
                ->values(),
        ]);

        return Inertia::render('SuperAdmin/Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', ''),
                'sort' => $sort,
                'dir' => $dir,
            ],
        ]);
    }

    public function promote(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        abort_if($user->isSuperAdmin(), 422, 'A Super Admin cannot be promoted to Site Admin.');

        $user->admin_level = AdminLevel::SiteAdmin;
        $user->save();

        return back()->with('success', "{$user->name} has been promoted to Site Admin.");
    }

    public function demote(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        abort_if($user->isSuperAdmin(), 422, 'Super Admin access cannot be removed from this screen.');
        abort_unless($user->isSiteAdmin(), 422, 'This user is not a Site Admin.');

        $user->admin_level = null;
        $user->save();

        return back()->with('success', "Site Admin access removed from {$user->name}.");
    }

    /**
     * Suspend an account. Any admin may freeze a non-super, non-self user; the
     * frozen user is then blocked at login and ejected from any active session.
     */
    public function freeze(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'You cannot freeze your own account.');
        abort_if($user->isSuperAdmin(), 422, 'A Super Admin account cannot be frozen.');

        $user->frozen_at = now();
        $user->save();

        return back()->with('success', __('flash.users.frozen', ['name' => $user->name]));
    }

    public function thaw(User $user): RedirectResponse
    {
        $user->frozen_at = null;
        $user->save();

        return back()->with('success', __('flash.users.thawed', ['name' => $user->name]));
    }

    public function sendPasswordReset(User $user): RedirectResponse
    {
        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('success', __('flash.users.reset_sent', ['email' => $user->email]));
        }

        return back()->with('warning', __('flash.users.reset_failed'));
    }

    /**
     * Soft-delete (prune) a user. Super-Admin-only and never an admin or self —
     * admins must be demoted before they can be removed.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        abort_if($user->id === $request->user()->id, 422, 'You cannot delete your own account.');
        abort_if($user->isAnyAdmin(), 422, 'Remove admin access before deleting this account.');

        $user->delete();

        return back()->with('success', __('flash.users.deleted', ['name' => $user->name]));
    }
}
