<?php

namespace App\Notifications;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notifies owners when their suspended business is restored by an admin.
 */
class BusinessThawed extends Notification
{
    use Queueable;

    public function __construct(
        public Business $business,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'business_thawed',
            'business_id' => $this->business->id,
            'business_name' => $this->business->name,
        ];
    }
}
