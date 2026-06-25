<?php

namespace App\Http\Middleware;

use App\Models\Business;
use App\Models\Distributor;
use App\Models\DistributorCatalogProposal;
use App\Models\Membership;
use App\Scopes\BusinessScope;
use App\Support\BusinessContext;
use App\Support\NotificationPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'isSuperAdmin' => $request->user()?->isSuperAdmin() ?? false,
                'isSiteAdmin' => $request->user()?->isSiteAdmin() ?? false,
                'isAnyAdmin' => $request->user()?->isAnyAdmin() ?? false,
                'isFrozen' => $request->user()?->isFrozen() ?? false,
                'avatarUrl' => $this->avatarUrl($request),
            ],
            'locale' => fn () => app()->getLocale(),
            'supportedLocales' => fn () => collect(config('app.supported_locales'))
                ->map(fn ($label, $code) => ['code' => $code, 'label' => $label])
                ->values(),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'warning' => fn () => $request->session()->get('warning'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'notifications' => fn () => ($user = $request->user())
                ? [
                    'unreadCount' => $user->unreadNotifications()->count(),
                    'recent' => NotificationPresenter::recent($user, 10, unreadOnly: true),
                ]
                : ['unreadCount' => 0, 'recent' => []],
            'pendingProposalsCount' => fn () => ($request->user()?->isSuperAdmin())
                ? DistributorCatalogProposal::pending()->count()
                : 0,
            'brokenDistributorsCount' => fn () => ($request->user()?->isSuperAdmin())
                ? Distributor::where('health_status', Distributor::HEALTH_BROKEN)->count()
                : 0,
            ...$this->businessProps($request),
        ];
    }

    private function businessProps(Request $request): array
    {
        $user = $request->user();

        if (! $user) {
            return [
                'business' => null,
                'businesses' => [],
                'membership' => null,
                'permissions' => [],
            ];
        }

        $memberships = Membership::withoutGlobalScope(BusinessScope::class)
            ->with('business')
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->get();

        if ($memberships->isEmpty()) {
            return [
                'business' => null,
                'businesses' => [],
                'membership' => null,
                'permissions' => [],
            ];
        }

        $currentBusinessId = BusinessContext::currentId();
        $currentMembership = $memberships->firstWhere('business_id', $currentBusinessId)
            ?? $memberships->first();

        $currentBusiness = $currentMembership->business;

        return [
            'business' => $currentBusiness ? [
                'id' => $currentBusiness->id,
                'name' => $currentBusiness->name,
                'slug' => $currentBusiness->slug,
                'color' => $currentMembership->business_badge_color,
                'logoUrl' => $this->businessLogoUrl($currentBusiness),
            ] : null,

            'businesses' => $memberships->map(fn (Membership $m) => [
                'id' => $m->business->id,
                'name' => $m->business->name,
                'color' => $m->business_badge_color,
                'logoUrl' => $this->businessLogoUrl($m->business),
                'pivot' => ['role' => $m->role, 'membership_id' => $m->id],
            ])->values(),

            'membership' => [
                'id' => $currentMembership->id,
                'role' => $currentMembership->role,
                'business_badge_color' => $currentMembership->business_badge_color,
            ],

            'permissions' => self::permissionsForRole($currentMembership->role),
        ];
    }

    private function businessLogoUrl(Business $business): string
    {
        if ($business->logo_path) {
            return Storage::disk('public')->url($business->logo_path);
        }

        $locale = app()->getLocale();
        $file = $locale === 'es'
            ? 'balloon-company-es-logo-light-default.png'
            : 'balloon-company-logo-light-default.png';

        return asset("images/defaults/{$file}");
    }

    private function avatarUrl(Request $request): string
    {
        $user = $request->user();

        if ($user?->avatar_path) {
            return Storage::disk('public')->url($user->avatar_path);
        }

        return asset('images/defaults/user-profile-default.png');
    }

    private static function permissionsForRole(string $role): array
    {
        return match ($role) {
            'owner' => [
                'inventory.check_in', 'inventory.check_out', 'inventory.manual_adjust',
                'inventory.override_count', 'inventory.view_counts', 'inventory.view_audit_log',
                'sku.create_private', 'sku.edit_private', 'sku.delete_private',
                'sku.edit_override', 'sku.report_error',
                'list.view', 'list.create', 'list.edit', 'list.delete', 'list.manage_visibility', 'favorites.edit',
                'job.view', 'job.create', 'job.edit', 'job.delete', 'job.set_status',
                'local_price.view', 'local_price.edit',
                'membership.invite_owner', 'membership.invite_manager',
                'membership.invite_staff', 'membership.invite_guest',
                'membership.change_role_any', 'membership.change_role_staff_guest',
                'membership.remove_owner', 'membership.remove_manager',
                'membership.remove_staff_guest', 'business.edit_settings',
                'business.manage_logo',
            ],
            'manager' => [
                'inventory.check_in', 'inventory.check_out', 'inventory.manual_adjust',
                'inventory.override_count', 'inventory.view_counts', 'inventory.view_audit_log',
                'sku.create_private', 'sku.edit_private', 'sku.delete_private',
                'sku.edit_override', 'sku.report_error',
                'list.view', 'list.create', 'list.edit', 'list.delete', 'favorites.edit',
                'job.view', 'job.create', 'job.edit', 'job.delete', 'job.set_status',
                'local_price.view', 'local_price.edit',
                'membership.invite_staff', 'membership.invite_guest',
                'membership.change_role_staff_guest', 'membership.remove_staff_guest',
                'business.edit_settings',
            ],
            'staff' => [
                'inventory.check_in', 'inventory.check_out', 'inventory.manual_adjust',
                'inventory.override_count', 'inventory.view_counts', 'inventory.view_audit_log',
                'sku.create_private', 'sku.edit_private', 'sku.report_error',
                'list.view', 'list.create', 'list.edit', 'list.delete', 'favorites.edit',
                'job.view', 'job.create', 'job.edit', 'job.set_status',
                'local_price.view', 'local_price.edit',
            ],
            'guest' => [
                'inventory.view_counts', 'sku.report_error',
                'list.view', 'list.create', 'list.edit',
            ],
            default => [],
        };
    }
}
