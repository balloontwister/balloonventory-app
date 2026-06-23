<?php

namespace App\Notifications;

use App\Mail\TemplatedMailable;
use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to a business owner when an invited user accepts and joins.
 */
class InvitationAccepted extends Notification
{
    use Queueable;

    public const TEMPLATE_KEY = 'invitation_accepted';

    public function __construct(
        public string $businessId,
        public string $businessName,
        public string $actorName,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (EmailTemplate::isActive(self::TEMPLATE_KEY)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): TemplatedMailable
    {
        return TemplatedMailable::forKey(self::TEMPLATE_KEY, [
            'user_name' => $notifiable->name,
            'actor_name' => $this->actorName,
            'business_name' => $this->businessName,
            'app_url' => route('dashboard'),
        ])->to($notifiable->email);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'invitation_accepted',
            'business_id' => $this->businessId,
            'business_name' => $this->businessName,
            'actor_name' => $this->actorName,
        ];
    }
}
