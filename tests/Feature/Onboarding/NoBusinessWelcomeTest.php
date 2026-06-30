<?php

namespace Tests\Feature\Onboarding;

use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\Membership;
use App\Models\User;
use App\Scopes\BusinessScope;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class NoBusinessWelcomeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(EmailTemplateSeeder::class);
    }

    public function test_no_business_user_hitting_a_gated_route_is_redirected_to_welcome(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('onboarding.welcome'));
    }

    public function test_no_business_user_can_view_the_welcome_landing(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('onboarding.welcome'))
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('Onboarding/Welcome')
                    ->has('pendingInvitations', 0)
            );
    }

    public function test_user_with_membership_is_redirected_off_the_welcome_landing(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        Membership::create([
            'user_id' => $user->id,
            'business_id' => $business->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('onboarding.welcome'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_welcome_landing_surfaces_a_pending_invitation(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        Membership::create([
            'user_id' => $owner->id,
            'business_id' => $business->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $invitee = User::factory()->create(['email_verified_at' => now()]);
        BusinessInvitation::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $business->id,
            'invited_email' => $invitee->email,
            'invited_user_id' => $invitee->id,
            'role' => 'staff',
            'token' => Str::random(64),
            'invited_by_user_id' => $owner->id,
            'status' => BusinessInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(14),
        ]);

        $this->actingAs($invitee)
            ->get(route('onboarding.welcome'))
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('Onboarding/Welcome')
                    ->has('pendingInvitations', 1)
                    ->where('pendingInvitations.0.business_name', $business->name)
                    ->where('pendingInvitations.0.role_label', 'Artist')
            );
    }

    public function test_no_business_invitee_can_accept_an_invitation_and_then_reach_the_dashboard(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        Membership::create([
            'user_id' => $owner->id,
            'business_id' => $business->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $invitee = User::factory()->create(['email_verified_at' => now()]);
        $token = Str::random(64);
        BusinessInvitation::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $business->id,
            'invited_email' => $invitee->email,
            'invited_user_id' => $invitee->id,
            'role' => 'staff',
            'token' => $token,
            'invited_by_user_id' => $owner->id,
            'status' => BusinessInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(14),
        ]);

        // A member-less invitee can hit accept-in-app (it lives outside the business gate).
        $this->actingAs($invitee)
            ->post(route('invitations.accept-in-app'), ['token' => $token])
            ->assertRedirect();

        $this->assertDatabaseHas('memberships', [
            'business_id' => $business->id,
            'user_id' => $invitee->id,
            'role' => 'staff',
        ]);

        // Now that they have a membership, the welcome landing forwards to the dashboard.
        $this->actingAs($invitee)
            ->get(route('onboarding.welcome'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_no_business_user_can_still_create_their_own_business(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->post(route('onboarding.store-business'), ['name' => 'Solo Balloons'])
            ->assertRedirect(route('onboarding.wizard'));

        $this->assertDatabaseHas('businesses', ['name' => 'Solo Balloons']);
    }
}
