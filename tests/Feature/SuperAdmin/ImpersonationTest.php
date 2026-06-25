<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\LoginEvent;
use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $siteAdmin;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdmin()->create(['email_verified_at' => now()]);
        $this->siteAdmin = User::factory()->siteAdmin()->create(['email_verified_at' => now()]);
        $this->regularUser = User::factory()->create(['email_verified_at' => now()]);
    }

    // ── Start ─────────────────────────────────────────────────────────────────

    public function test_admin_can_start_impersonating_a_regular_user(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.users.impersonate', $this->regularUser));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');
        $response->assertSessionHas(Impersonation::SESSION_KEY, $this->superAdmin->id);
        $this->assertAuthenticatedAs($this->regularUser);
    }

    public function test_site_admin_can_also_impersonate(): void
    {
        $this->actingAs($this->siteAdmin)
            ->post(route('admin.users.impersonate', $this->regularUser))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->regularUser);
    }

    public function test_cannot_impersonate_self(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('admin.users.impersonate', $this->superAdmin))
            ->assertStatus(422);

        $this->assertAuthenticatedAs($this->superAdmin);
    }

    public function test_cannot_impersonate_a_site_admin(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('admin.users.impersonate', $this->siteAdmin))
            ->assertStatus(422);
    }

    public function test_cannot_impersonate_a_super_admin(): void
    {
        $other = User::factory()->superAdmin()->create();

        $this->actingAs($this->superAdmin)
            ->post(route('admin.users.impersonate', $other))
            ->assertStatus(422);
    }

    public function test_regular_user_cannot_impersonate(): void
    {
        $target = User::factory()->create();

        $this->actingAs($this->regularUser)
            ->post(route('admin.users.impersonate', $target))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->post(route('admin.users.impersonate', $this->regularUser))
            ->assertRedirect(route('login'));
    }

    // ── Stop ──────────────────────────────────────────────────────────────────

    public function test_stop_returns_to_the_admin_account(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('admin.users.impersonate', $this->regularUser));

        $this->assertAuthenticatedAs($this->regularUser);

        $response = $this->post(route('impersonate.stop'));

        $response->assertRedirect(route('admin.users.show', $this->regularUser->id));
        $response->assertSessionMissing(Impersonation::SESSION_KEY);
        $this->assertAuthenticatedAs($this->superAdmin);
    }

    public function test_stop_is_a_noop_when_not_impersonating(): void
    {
        $this->actingAs($this->regularUser)
            ->post(route('impersonate.stop'))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->regularUser);
    }

    // ── Shared prop for the banner ────────────────────────────────────────────

    public function test_impersonation_prop_is_shared_at_the_top_level_for_the_banner(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('admin.users.impersonate', $this->regularUser));

        $this->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('impersonating.userName', $this->regularUser->name)
                ->where('impersonating.adminName', $this->superAdmin->name));
    }

    // ── Audit hygiene ─────────────────────────────────────────────────────────

    public function test_impersonation_does_not_record_a_login_event_for_the_user(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('admin.users.impersonate', $this->regularUser));

        $this->assertSame(
            0,
            LoginEvent::where('user_id', $this->regularUser->id)->count(),
        );
    }

    public function test_impersonation_does_not_bump_the_users_last_login(): void
    {
        $this->assertNull($this->regularUser->last_login_at);

        $this->actingAs($this->superAdmin)
            ->post(route('admin.users.impersonate', $this->regularUser));

        $this->regularUser->refresh();
        $this->assertNull($this->regularUser->last_login_at);
    }
}
