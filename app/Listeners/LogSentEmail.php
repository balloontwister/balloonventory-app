<?php

namespace App\Listeners;

use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Mail\Events\MessageSent;

class LogSentEmail
{
    public function handle(MessageSent $event): void
    {
        $to = array_key_first($event->message->getTo() ?? []);
        $subject = $event->message->getSubject() ?? '';
        $mailable = $event->data['__laravel_mailable'] ?? 'unknown';

        $userId = null;
        if ($to) {
            $userId = User::where('email', $to)->value('id');
        }

        EmailLog::create([
            'to' => $to ?? '',
            'subject' => $subject,
            'mailable' => class_basename($mailable),
            'user_id' => $userId,
        ]);
    }
}
