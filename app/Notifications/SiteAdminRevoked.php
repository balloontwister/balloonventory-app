<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Shown to a user when a Super Admin removes their Site Admin access.
 * Database-only — a platform-level account change, no email.
 */
class SiteAdminRevoked extends Notification
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'site_admin_revoked',
        ];
    }
}
