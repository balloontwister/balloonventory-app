<?php

namespace App\Notifications;

use App\Mail\TemplatedMailable;
use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to a member when an owner removes them from a business. Email only —
 * the member no longer has access, so there is no in-app notice to show.
 */
class MemberRemoved extends Notification
{
    use Queueable;

    public const TEMPLATE_KEY = 'member_removed';

    public function __construct(
        public string $businessName,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return EmailTemplate::isActive(self::TEMPLATE_KEY) ? ['mail'] : [];
    }

    public function toMail(object $notifiable): TemplatedMailable
    {
        return TemplatedMailable::forKey(self::TEMPLATE_KEY, [
            'user_name' => $notifiable->name,
            'business_name' => $this->businessName,
            'app_url' => route('dashboard'),
        ])->to($notifiable->email);
    }
}
