<?php

namespace App\Support;

/**
 * Session-key constants + helpers shared by the impersonation controller and the
 * login-event listeners, so the magic strings live in exactly one place.
 */
class Impersonation
{
    /** Holds the acting admin's id while they impersonate another user. */
    public const SESSION_KEY = 'impersonator_id';

    /**
     * Set around an Auth::login() call that's part of starting or ending an
     * impersonation, so login-history / last-login listeners can skip it.
     */
    public const TRANSITION_KEY = '__impersonation_transition';

    /**
     * Whether the current request is mid-impersonation-switch (a synchronous
     * Auth::login the login listeners should ignore).
     */
    public static function isTransitioning(): bool
    {
        return (bool) session(self::TRANSITION_KEY);
    }
}
