<?php

namespace App\Listeners;

use App\Support\Impersonation;
use Illuminate\Auth\Events\Login;

class RecordLastLogin
{
    public function handle(Login $event): void
    {
        // Don't bump last_login_at when an admin switches into/out of an
        // impersonation session — it isn't the user actually signing in.
        if (Impersonation::isTransitioning()) {
            return;
        }

        $event->user->forceFill(['last_login_at' => now()])->save();
    }
}
