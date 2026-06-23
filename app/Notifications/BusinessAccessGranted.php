<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Shown to the user who just gained access to a business (the joiner).
 * Database-only — it drives the dashboard notice with a "Switch" CTA.
 */
class BusinessAccessGranted extends Notification
{
    use Queueable;

    public function __construct(
        public string $businessId,
        public string $businessName,
        public string $roleLabel,
    ) {}

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
            'type' => 'business_access_granted',
            'business_id' => $this->businessId,
            'business_name' => $this->businessName,
            'role_label' => $this->roleLabel,
        ];
    }
}
