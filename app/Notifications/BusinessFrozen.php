<?php

namespace App\Notifications;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notifies owners when their business is suspended by an admin.
 */
class BusinessFrozen extends Notification
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
            'type' => 'business_frozen',
            'business_id' => $this->business->id,
            'business_name' => $this->business->name,
        ];
    }
}
