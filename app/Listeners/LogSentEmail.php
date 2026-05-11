<?php

namespace App\Listeners;

use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Mail\Events\MessageSent;

class LogSentEmail
{
    public function handle(MessageSent $event): void
    {
        $addresses = $event->message->getTo() ?? [];
        $first = reset($addresses) ?: null;
        $to = $first?->getAddress();

        $subject = $event->message->getSubject() ?? '';
        $mailable = $event->data['__laravel_mailable'] ?? 'unknown';

        $userId = $to ? User::where('email', $to)->value('id') : null;

        EmailLog::create([
            'to' => $to ?? '',
            'subject' => $subject,
            'mailable' => class_basename($mailable),
            'user_id' => $userId,
        ]);
    }
}
