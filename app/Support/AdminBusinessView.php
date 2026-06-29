<?php

namespace App\Support;

/**
 * Super-Admin "View as business" support. Lets an admin enter any business —
 * including ownerless or memberless ones — and operate inside it as themselves
 * (no user impersonation). The viewed business id is stashed in the session;
 * while it's set:
 *
 *   - SetBusinessContext points the tenant scope at that business,
 *   - ChecksMembership resolves the super-admin's role there as 'owner' (so the
 *     existing permission matrix grants full business abilities — scoped to this
 *     one business only),
 *   - HandleInertiaRequests surfaces a persistent banner + owner-level frontend
 *     permissions.
 *
 * The override is always gated on the user actually being a Super Admin, so a
 * stray session value can't grant anything on its own.
 */
class AdminBusinessView
{
    /** Holds the business id the admin is currently viewing as. */
    public const SESSION_KEY = 'admin_viewing_business_id';

    public static function start(string $businessId): void
    {
        session()->put(self::SESSION_KEY, $businessId);
    }

    public static function stop(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public static function businessId(): ?string
    {
        return session(self::SESSION_KEY);
    }

    public static function isActive(): bool
    {
        return session()->has(self::SESSION_KEY);
    }

    public static function isViewing(string $businessId): bool
    {
        return self::businessId() === $businessId;
    }
}
