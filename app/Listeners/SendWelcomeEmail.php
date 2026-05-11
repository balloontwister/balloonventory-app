<?php

namespace App\Listeners;

use App\Mail\TemplatedMailable;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendWelcomeEmail
{
    public function handle(Verified $event): void
    {
        $user = $event->user;

        try {
            $welcome = TemplatedMailable::forKey('welcome', [
                'user_name' => $user->name,
                'app_url' => config('app.url'),
            ]);

            if ($welcome) {
                Mail::to($user->email)->send($welcome);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send welcome email', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
