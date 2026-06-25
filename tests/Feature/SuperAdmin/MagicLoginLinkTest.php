<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\MagicLoginLink;
use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MagicLoginLinkTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdmin()->create(['email_verified_at' => now()]);
        $this->regularUser = User::factory()->create(['email_verified_at' => now()]);
    }

    // ── Generation (admin) ─────────────────────────────────────────────────────

    public function test_admin_can_generate_a_magic_login_link(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('admin.users.magic-login', $this->regularUser));

        $response->assertOk();
        $response->assertJsonStructure(['url', 'expires_in_minutes']);

        $this->assertSame(
            1,
            MagicLoginLink::where('user_id', $this->regularUser->id)
                ->whereNull('used_at')
                ->count(),
        );

        // The raw token is never stored — only its hash.
        $link = MagicLoginLink::first();
        $this->assertSame(64, strlen($link->token_hash));
        $this->assertTrue($link->expires_at->isFuture());
        $this->assertSame($this->superAdmin->id, $link->created_by_user_id);
    }

    public function test_cannot_generate_a_link_for_an_admin(): void
    {
        $admin = User::factory()->siteAdmin()->create();

        $this->actingAs($this->superAdmin)
            ->postJson(route('admin.users.magic-login', $admin))
            ->assertStatus(422);
    }

    public function test_regular_user_cannot_generate_a_link(): void
    {
        $this->actingAs($this->regularUser)
            ->postJson(route('admin.users.magic-login', User::factory()->create()))
            ->assertForbidden();
    }

    // ── Consumption (public) ───────────────────────────────────────────────────

    public function test_landing_page_renders_for_a_valid_link(): void
    {
        ['token' => $token] = MagicLoginLink::generate($this->regularUser, $this->superAdmin);

        $this->get(route('magic-login.show', $token))
            ->assertOk();

        $this->assertGuest();
    }

    public function test_consuming_a_link_signs_in_as_the_user_and_burns_it(): void
    {
        ['link' => $link, 'token' => $token] = MagicLoginLink::generate($this->regularUser, $this->superAdmin);

        $response = $this->post(route('magic-login.consume', $token));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->regularUser);

        $link->refresh();
        $this->assertNotNull($link->used_at);
    }

    public function test_a_guest_consuming_a_link_is_not_an_impersonation_session(): void
    {
        ['token' => $token] = MagicLoginLink::generate($this->regularUser, $this->superAdmin);

        $response = $this->post(route('magic-login.consume', $token));

        $this->assertAuthenticatedAs($this->regularUser);
        $response->assertSessionMissing(Impersonation::SESSION_KEY);
    }

    public function test_an_admin_consuming_a_link_becomes_an_impersonation_session(): void
    {
        ['token' => $token] = MagicLoginLink::generate($this->regularUser, $this->superAdmin);

        $response = $this->actingAs($this->superAdmin)
            ->post(route('magic-login.consume', $token));

        // The admin is now viewing as the user, with a return path.
        $this->assertAuthenticatedAs($this->regularUser);
        $response->assertSessionHas(Impersonation::SESSION_KEY, $this->superAdmin->id);

        $this->post(route('impersonate.stop'))
            ->assertRedirect(route('admin.users.show', $this->regularUser->id));
        $this->assertAuthenticatedAs($this->superAdmin);
    }

    public function test_a_link_cannot_be_used_twice(): void
    {
        ['token' => $token] = MagicLoginLink::generate($this->regularUser, $this->superAdmin);

        $this->post(route('magic-login.consume', $token));
        $this->post(route('logout'));

        $second = $this->post(route('magic-login.consume', $token));
        $second->assertRedirect(route('login'));
        $second->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_an_expired_link_is_rejected(): void
    {
        ['link' => $link, 'token' => $token] = MagicLoginLink::generate($this->regularUser, $this->superAdmin);
        $link->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->post(route('magic-login.consume', $token))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        $this->get(route('magic-login.show', 'not-a-real-token'))
            ->assertRedirect(route('login'));

        $this->post(route('magic-login.consume', 'not-a-real-token'))
            ->assertRedirect(route('login'));
    }

    public function test_a_link_for_a_now_admin_account_is_rejected(): void
    {
        ['token' => $token] = MagicLoginLink::generate($this->regularUser, $this->superAdmin);

        // The account was promoted to admin after the link was minted.
        $this->regularUser->forceFill(['admin_level' => 'site_admin'])->save();

        $this->post(route('magic-login.consume', $token))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
