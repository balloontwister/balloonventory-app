<?php

namespace Tests\Feature\Auth;

use App\Models\LoginEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LoginHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_is_recorded(): void
    {
        $user = User::factory()->create(['email' => 'logmein@example.com']);

        $this->post('/login', [
            'email' => 'logmein@example.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('login_events', [
            'user_id' => $user->id,
            'email' => 'logmein@example.com',
            'event' => LoginEvent::SUCCESS,
        ]);
    }

    public function test_failed_login_is_recorded(): void
    {
        $user = User::factory()->create(['email' => 'wrongpass@example.com']);

        $this->post('/login', [
            'email' => 'wrongpass@example.com',
            'password' => 'not-the-password',
        ]);

        $this->assertGuest();
        $this->assertDatabaseHas('login_events', [
            'user_id' => $user->id,
            'email' => 'wrongpass@example.com',
            'event' => LoginEvent::FAILED,
        ]);
    }

    public function test_repeated_failures_record_a_lockout(): void
    {
        User::factory()->create(['email' => 'target@example.com']);

        // Throttle trips after 5 attempts; the 6th fires a Lockout event.
        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', [
                'email' => 'target@example.com',
                'password' => 'nope',
            ]);
        }

        $this->assertDatabaseHas('login_events', [
            'email' => 'target@example.com',
            'event' => LoginEvent::LOCKOUT,
        ]);
    }

    public function test_prune_deletes_events_past_retention(): void
    {
        $this->travelTo(Carbon::parse('2026-06-20'));

        $old = LoginEvent::create([
            'email' => 'old@example.com',
            'event' => LoginEvent::SUCCESS,
            'created_at' => now()->subMonths(19),
        ]);
        $recent = LoginEvent::create([
            'email' => 'recent@example.com',
            'event' => LoginEvent::SUCCESS,
            'created_at' => now()->subMonths(3),
        ]);

        $this->artisan('app:prune-login-events')->assertSuccessful();

        $this->assertDatabaseMissing('login_events', ['id' => $old->id]);
        $this->assertDatabaseHas('login_events', ['id' => $recent->id]);
    }
}
