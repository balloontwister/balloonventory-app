<?php

namespace App\Http\Middleware;

use App\Models\Membership;
use App\Scopes\BusinessScope;
use App\Support\BusinessContext;
use Illuminate\Http\Request;
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
            ],
            'locale' => fn () => app()->getLocale(),
            'supportedLocales' => fn () => collect(config('app.supported_locales'))
                ->map(fn ($label, $code) => ['code' => $code, 'label' => $label])
                ->values(),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
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
            ] : null,

            'businesses' => $memberships->map(fn (Membership $m) => [
                'id' => $m->business->id,
                'name' => $m->business->name,
                'color' => $m->business_badge_color,
                'pivot' => ['role' => $m->role],
            ])->values(),

            'membership' => [
                'id' => $currentMembership->id,
                'role' => $currentMembership->role,
                'business_badge_color' => $currentMembership->business_badge_color,
            ],

            'permissions' => self::permissionsForRole($currentMembership->role),
        ];
    }

    private static function permissionsForRole(string $role): array
    {
        return match ($role) {
            'owner' => [
                'inventory.check_in', 'inventory.check_out', 'inventory.manual_adjust',
                'inventory.override_count', 'inventory.view_counts', 'inventory.view_audit_log',
                'sku.create_private', 'sku.edit_private', 'sku.delete_private',
                'sku.edit_override', 'sku.report_error',
                'upc.manage', 'upc.resolve_pending', 'upc.scan',
                'list.view', 'list.create', 'list.edit', 'list.delete', 'favorites.edit',
                'job.view', 'job.create', 'job.edit', 'job.delete', 'job.set_status',
                'local_price.view', 'local_price.edit',
                'membership.invite_owner', 'membership.invite_manager',
                'membership.invite_staff', 'membership.invite_guest',
                'membership.change_role_any', 'membership.change_role_staff_guest',
                'membership.remove_owner', 'membership.remove_manager',
                'membership.remove_staff_guest', 'business.edit_settings',
            ],
            'manager' => [
                'inventory.check_in', 'inventory.check_out', 'inventory.manual_adjust',
                'inventory.override_count', 'inventory.view_counts', 'inventory.view_audit_log',
                'sku.create_private', 'sku.edit_private', 'sku.delete_private',
                'sku.edit_override', 'sku.report_error',
                'upc.manage', 'upc.resolve_pending', 'upc.scan',
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
                'upc.manage', 'upc.scan',
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
