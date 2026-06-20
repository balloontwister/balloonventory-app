<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\LoginEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginLogTest extends TestCase
{
    use RefreshDatabase;

    private function logEvent(string $event, array $overrides = []): LoginEvent
    {
        return LoginEvent::create(array_merge([
            'email' => 'someone@example.com',
            'event' => $event,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
        ], $overrides));
    }

    public function test_admin_can_view_the_login_log(): void
    {
        $this->logEvent(LoginEvent::SUCCESS);
        $this->logEvent(LoginEvent::FAILED);

        $this->actingAs(User::factory()->siteAdmin()->create())
            ->get(route('admin.login-log.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SuperAdmin/LoginLog/Index')
                ->has('events.data', 2)
                ->has('failed7d')
            );
    }

    public function test_login_log_can_be_filtered_by_event(): void
    {
        $this->logEvent(LoginEvent::SUCCESS);
        $this->logEvent(LoginEvent::FAILED, ['email' => 'bad@example.com']);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('admin.login-log.index', ['event' => 'failed']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('events.data', 1)
                ->where('events.data.0.event', 'failed')
            );
    }

    public function test_regular_user_cannot_view_the_login_log(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.login-log.index'))
            ->assertForbidden();
    }
}
