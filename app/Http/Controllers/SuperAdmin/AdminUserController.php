<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\AdminLevel;
use App\Http\Controllers\Controller;
use App\Mail\TemplatedMailable;
use App\Models\AdminUserMessage;
use App\Models\Business;
use App\Models\EmailLog;
use App\Models\LoginEvent;
use App\Models\Membership;
use App\Models\SkuFeedback;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SiteAdminGranted;
use App\Notifications\SiteAdminRevoked;
use App\Scopes\BusinessScope;
use App\Support\Countries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
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
            'per_page' => ['nullable', 'in:25,50,100,all'],
        ]);

        // Inventory is per-business. A user can belong to several businesses, so
        // the figures reflect their PRIMARY business — the one they own, else the
        // earliest they joined. These correlated subqueries bypass the
        // BelongsToBusiness scope (raw SQL ignores Eloquent scopes) so the count
        // doesn't depend on which business the acting admin is currently in.
        $primaryBusinessId = '(select m.business_id from memberships m '
            .'where m.user_id = users.id and m.deleted_at is null '
            ."order by (m.role = 'owner') desc, m.joined_at asc, m.id asc limit 1)";

        $inventorySkus = '(select count(distinct sl.sku_id) from stock_levels sl '
            ."where sl.deleted_at is null and sl.business_id = {$primaryBusinessId})";

        $inventoryBags = '(select coalesce(sum(sl.full_bags + sl.open_bags), 0) from stock_levels sl '
            ."where sl.deleted_at is null and sl.business_id = {$primaryBusinessId})";

        $primaryBusinessName = "(select b.name from businesses b where b.id = {$primaryBusinessId})";

        $query = User::withTrashed()
            ->select('users.*')
            ->addSelect(DB::raw("{$inventorySkus} as inventory_skus_count"))
            ->addSelect(DB::raw("{$inventoryBags} as inventory_bags_total"))
            ->addSelect(DB::raw("{$primaryBusinessName} as primary_business_name"))
            ->withCount(['supportTickets', 'skuFeedback'])
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
            'businesses' => $query->orderBy('primary_business_name', $dir),
            default => $query->orderBy('last_login_at', $dir),
        };

        $perPageInput = $request->input('per_page', '50');
        $perPage = $perPageInput === 'all' ? 1000000 : (int) $perPageInput;

        $users = $query->paginate($perPage)->withQueryString();

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
                'per_page' => $perPageInput,
            ],
        ]);
    }

    /**
     * A single user's detail/support view. Resolves trashed users too so support
     * can still inspect pruned/deleted accounts. Login history and ledger are
     * still placeholders (no source tables yet); contact fields (phone/website/
     * city/country) aren't collected yet either.
     */
    public function show(string $user): Response
    {
        $model = User::withTrashed()->findOrFail($user);

        // Businesses the user belongs to — bypass the tenant scope so this works
        // regardless of the acting admin's current business.
        $businesses = Membership::withoutGlobalScope(BusinessScope::class)
            ->where('user_id', $model->id)
            ->with('business')
            ->orderBy('joined_at')
            ->get()
            ->map(fn (Membership $m) => [
                'id' => $m->business?->id,
                'name' => $m->business?->name,
                'role' => $m->role,
                'joined_at' => $m->joined_at,
                'contact' => $m->business ? [
                    'phone' => $m->business->phone,
                    'address_line1' => $m->business->address_line1,
                    'address_line2' => $m->business->address_line2,
                    'city' => $m->business->city,
                    'state_region' => $m->business->state_region,
                    'postal_code' => $m->business->postal_code,
                    'country' => Countries::name($m->business->country),
                    'website_url' => $m->business->website_url,
                    'website_url_2' => $m->business->website_url_2,
                    'contact_email' => $m->business->contact_email,
                ] : null,
            ])
            ->filter(fn (array $b) => $b['name'] !== null)
            ->values();

        $feedback = SkuFeedback::where('user_id', $model->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'sku_id', 'sku_name', 'field', 'suggested_value', 'status', 'created_at']);

        $tickets = SupportTicket::where('user_id', $model->id)
            ->withCount('replies')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'subject', 'archived_at', 'created_at']);

        // Emails are logged with a user_id when known, but also match on the
        // recipient address to catch any sent before the link was recorded.
        // Admin-composed direct messages are excluded here and instead pulled
        // from admin_user_messages below (which also carries the body) so the
        // send isn't listed twice.
        $loggedEmails = EmailLog::where(fn ($q) => $q
            ->where('user_id', $model->id)
            ->orWhere('to', $model->email))
            ->where(fn ($q) => $q
                ->whereNull('mailable')
                ->orWhere('mailable', '!=', 'AdminUserMessageMail'))
            ->orderByDesc('sent_at')
            ->limit(50)
            ->get(['id', 'subject', 'mailable', 'sent_at'])
            ->map(fn (EmailLog $e) => [
                'id' => $e->id,
                'subject' => $e->subject,
                'mailable' => $e->mailable,
                'sent_at' => $e->sent_at,
                'body' => null,
            ]);

        // Admin-composed direct messages — carry the full body for review.
        $directMessages = AdminUserMessage::where('user_id', $model->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'subject', 'body', 'created_at'])
            ->map(fn (AdminUserMessage $m) => [
                'id' => $m->id,
                'subject' => $m->subject,
                'mailable' => 'AdminUserMessageMail',
                'sent_at' => $m->created_at,
                'body' => $m->body,
            ]);

        $emails = $loggedEmails->concat($directMessages)
            ->sortByDesc('sent_at')
            ->take(50)
            ->values();

        // Login history — matched by user_id (successes) or attempted email
        // (failed/lockout attempts on this account, where user_id may be null).
        $loginEvents = LoginEvent::where(fn ($q) => $q
            ->where('user_id', $model->id)
            ->orWhere('email', $model->email))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'event', 'ip_address', 'user_agent', 'created_at']);

        return Inertia::render('SuperAdmin/Users/Show', [
            'user' => [
                'id' => $model->id,
                'name' => $model->name,
                'email' => $model->email,
                'original_email' => $model->original_email,
                'avatar_url' => $model->avatar_path
                    ? Storage::disk('public')->url($model->avatar_path)
                    : asset('images/defaults/user-profile-default.png'),
                'admin_level' => $model->admin_level,
                'email_verified_at' => $model->email_verified_at,
                'created_at' => $model->created_at,
                'last_login_at' => $model->last_login_at,
                'frozen_at' => $model->frozen_at,
                'deleted_at' => $model->deleted_at,
                'locale' => $model->locale,
                'timezone' => $model->timezone,
                'phone' => $model->phone,
                'address_line1' => $model->address_line1,
                'address_line2' => $model->address_line2,
                'city' => $model->city,
                'state_region' => $model->state_region,
                'postal_code' => $model->postal_code,
                'country' => Countries::name($model->country),
                'website_url' => $model->website_url,
                'website_url_2' => $model->website_url_2,
            ],
            'businesses' => $businesses,
            'feedback' => $feedback,
            'tickets' => $tickets,
            'emails' => $emails,
            'loginEvents' => $loginEvents,
        ]);
    }

    public function promote(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        abort_if($user->isSuperAdmin(), 422, 'A Super Admin cannot be promoted to Site Admin.');

        $user->admin_level = AdminLevel::SiteAdmin;
        $user->save();

        $user->notify(new SiteAdminGranted);

        return back()->with('success', "{$user->name} has been promoted to Site Admin.");
    }

    public function demote(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        abort_if($user->isSuperAdmin(), 422, 'Super Admin access cannot be removed from this screen.');
        abort_unless($user->isSiteAdmin(), 422, 'This user is not a Site Admin.');

        $user->admin_level = null;
        $user->save();

        $user->notify(new SiteAdminRevoked);

        return back()->with('success', "Site Admin access removed from {$user->name}.");
    }

    /**
     * Suspend an account. Any admin may freeze a non-super, non-self user; the
     * frozen user can still sign in but is limited to the account area
     * (enforced by EnsureAccountActive).
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
     * Directly set a password for a regular user account. Useful when the user
     * has lost access to their email and cannot receive a reset link. The new
     * password is communicated out-of-band by the admin. An optional notification
     * email (never containing the password) can be sent to alert the user.
     */
    public function setPassword(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'Use your profile to change your own password.');
        abort_if($user->isAnyAdmin(), 422, 'You cannot set the password for an admin account.');

        $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'notify' => ['boolean'],
            'logout_sessions' => ['boolean'],
        ]);

        $user->forceFill([
            'password' => Hash::make($request->string('password')),
            'remember_token' => Str::random(60),
        ])->save();

        if ($request->boolean('logout_sessions')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        if (! $request->boolean('notify')) {
            return back()->with('success', __('flash.users.password_set', ['name' => $user->name]));
        }

        if (! $user->email) {
            return back()->with('warning', __('flash.users.password_set_no_email', ['name' => $user->name]));
        }

        $mail = TemplatedMailable::forKey('password_changed_by_admin', [
            'user_name' => $user->name,
            'app_url' => config('app.url'),
        ]);

        if (! $mail) {
            return back()->with('warning', __('flash.users.password_set_notify_unavailable', ['name' => $user->name]));
        }

        Mail::to($user->email)->send($mail);

        return back()->with('success', __('flash.users.password_set_notified', ['name' => $user->name]));
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
