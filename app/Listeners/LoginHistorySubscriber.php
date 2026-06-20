<?php

namespace App\Listeners;

use App\Models\LoginEvent;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Events\Dispatcher;

/**
 * Records the login history: a row for each successful sign-in, failed attempt,
 * and throttle lockout. Registered as an event subscriber in AppServiceProvider
 * (method names are not "handle", so they aren't auto-discovered and won't
 * double-register).
 */
class LoginHistorySubscriber
{
    public function handleLogin(Login $event): void
    {
        LoginEvent::create([
            'user_id' => $event->user?->getAuthIdentifier(),
            'email' => $event->user?->email,
            'event' => LoginEvent::SUCCESS,
            'ip_address' => request()->ip(),
            'user_agent' => $this->agent(request()->userAgent()),
        ]);
    }

    public function handleFailed(Failed $event): void
    {
        LoginEvent::create([
            'user_id' => $event->user?->getAuthIdentifier(),
            'email' => $event->credentials['email'] ?? null,
            'event' => LoginEvent::FAILED,
            'ip_address' => request()->ip(),
            'user_agent' => $this->agent(request()->userAgent()),
        ]);
    }

    public function handleLockout(Lockout $event): void
    {
        LoginEvent::create([
            'email' => $event->request->input('email'),
            'event' => LoginEvent::LOCKOUT,
            'ip_address' => $event->request->ip(),
            'user_agent' => $this->agent($event->request->userAgent()),
        ]);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Failed::class => 'handleFailed',
            Lockout::class => 'handleLockout',
        ];
    }

    /** Cap the stored user agent to keep rows tidy. */
    private function agent(?string $value): ?string
    {
        return $value ? mb_substr($value, 0, 1024) : null;
    }
}
