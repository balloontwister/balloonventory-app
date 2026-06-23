<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\Membership;
use App\Models\User;
use App\Scopes\BusinessScope;
use App\Support\BusinessContext;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessInvitationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(EmailTemplateSeeder::class);

        $this->owner = User::factory()->create(['email_verified_at' => now()]);
        $this->business = Business::factory()->create();

        Membership::create([
            'user_id' => $this->owner->id,
            'business_id' => $this->business->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        BusinessContext::set($this->business->id);
    }

    protected function tearDown(): void
    {
        BusinessContext::clear();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Invite
    // -------------------------------------------------------------------------

    public function test_owner_can_invite_existing_user_and_email_is_sent(): void
    {
        Mail::fake();

        $invitee = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($this->owner)->post(route('memberships.invite'), [
            'email' => $invitee->email,
            'role' => 'staff',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('business_invitations', [
            'business_id' => $this->business->id,
            'invited_user_id' => $invitee->id,
            'role' => 'staff',
            'status' => BusinessInvitation::STATUS_PENDING,
        ]);

        // TemplatedMailable implements ShouldQueue, so it is queued, not sent synchronously.
        Mail::assertQueuedCount(1);
    }

    public function test_invite_unknown_email_is_rejected(): void
    {
        $response = $this->actingAs($this->owner)->post(route('memberships.invite'), [
            'email' => 'nobody@example.com',
            'role' => 'staff',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('business_invitations', 0);
    }

    public function test_owner_cannot_invite_themselves(): void
    {
        $response = $this->actingAs($this->owner)->post(route('memberships.invite'), [
            'email' => $this->owner->email,
            'role' => 'staff',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('business_invitations', 0);
    }

    public function test_invite_already_member_is_rejected(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        Membership::create([
            'user_id' => $member->id,
            'business_id' => $this->business->id,
            'role' => 'staff',
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($this->owner)->post(route('memberships.invite'), [
            'email' => $member->email,
            'role' => 'staff',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_staff_cannot_invite_owner(): void
    {
        $staffUser = User::factory()->create(['email_verified_at' => now()]);
        Membership::create([
            'user_id' => $staffUser->id,
            'business_id' => $this->business->id,
            'role' => 'staff',
            'joined_at' => now(),
        ]);

        $invitee = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($staffUser)->post(route('memberships.invite'), [
            'email' => $invitee->email,
            'role' => 'owner',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('business_invitations', 0);
    }

    // -------------------------------------------------------------------------
    // Magic-link accept (GET /invitations/{token}/accept)
    // -------------------------------------------------------------------------

    public function test_magic_link_logs_in_unauthenticated_user_and_creates_membership(): void
    {
        $invitee = User::factory()->create(['email_verified_at' => now()]);
        $token = Str::random(64);

        BusinessInvitation::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'invited_email' => $invitee->email,
            'invited_user_id' => $invitee->id,
            'role' => 'staff',
            'token' => $token,
            'invited_by_user_id' => $this->owner->id,
            'status' => BusinessInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(14),
        ]);

        $response = $this->get(route('invitations.accept', ['token' => $token]));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($invitee);

        $this->assertDatabaseHas('memberships', [
            'business_id' => $this->business->id,
            'user_id' => $invitee->id,
            'role' => 'staff',
        ]);

        // Token must be rotated after acceptance (single-use).
        $this->assertDatabaseMissing('business_invitations', ['token' => $token]);
        $this->assertDatabaseHas('business_invitations', [
            'invited_user_id' => $invitee->id,
            'status' => BusinessInvitation::STATUS_ACCEPTED,
        ]);
    }

    public function test_magic_link_rejects_wrong_logged_in_user(): void
    {
        $invitee = User::factory()->create(['email_verified_at' => now()]);
        $otherUser = User::factory()->create(['email_verified_at' => now()]);
        $token = Str::random(64);

        BusinessInvitation::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'invited_email' => $invitee->email,
            'invited_user_id' => $invitee->id,
            'role' => 'staff',
            'token' => $token,
            'invited_by_user_id' => $this->owner->id,
            'status' => BusinessInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(14),
        ]);

        $response = $this->actingAs($otherUser)
            ->get(route('invitations.accept', ['token' => $token]));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('memberships', ['user_id' => $invitee->id]);
    }

    public function test_magic_link_rejects_invalid_token(): void
    {
        $response = $this->get(route('invitations.accept', ['token' => 'invalid-token-xyz']));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
    }

    public function test_magic_link_rejects_expired_invitation(): void
    {
        $invitee = User::factory()->create(['email_verified_at' => now()]);
        $token = Str::random(64);

        BusinessInvitation::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'invited_email' => $invitee->email,
            'invited_user_id' => $invitee->id,
            'role' => 'staff',
            'token' => $token,
            'invited_by_user_id' => $this->owner->id,
            'status' => BusinessInvitation::STATUS_PENDING,
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->get(route('invitations.accept', ['token' => $token]));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
    }

    // -------------------------------------------------------------------------
    // In-app accept / decline
    // -------------------------------------------------------------------------

    public function test_in_app_accept_creates_membership(): void
    {
        $invitee = User::factory()->create(['email_verified_at' => now()]);
        $this->giveUserOwnBusiness($invitee);
        $token = Str::random(64);

        BusinessInvitation::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'invited_email' => $invitee->email,
            'invited_user_id' => $invitee->id,
            'role' => 'guest',
            'token' => $token,
            'invited_by_user_id' => $this->owner->id,
            'status' => BusinessInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(14),
        ]);

        $response = $this->actingAs($invitee)
            ->post(route('invitations.accept-in-app'), ['token' => $token]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('memberships', [
            'business_id' => $this->business->id,
            'user_id' => $invitee->id,
            'role' => 'guest',
        ]);
    }

    public function test_in_app_decline_marks_invitation_declined(): void
    {
        $invitee = User::factory()->create(['email_verified_at' => now()]);
        $this->giveUserOwnBusiness($invitee);
        $token = Str::random(64);

        $invitation = BusinessInvitation::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'invited_email' => $invitee->email,
            'invited_user_id' => $invitee->id,
            'role' => 'staff',
            'token' => $token,
            'invited_by_user_id' => $this->owner->id,
            'status' => BusinessInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(14),
        ]);

        $response = $this->actingAs($invitee)
            ->post(route('invitations.decline'), ['token' => $token]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('business_invitations', [
            'id' => $invitation->id,
            'status' => BusinessInvitation::STATUS_DECLINED,
        ]);
    }

    // -------------------------------------------------------------------------
    // Revoke invitation
    // -------------------------------------------------------------------------

    public function test_owner_can_revoke_pending_invitation(): void
    {
        $invitee = User::factory()->create(['email_verified_at' => now()]);

        $invitation = BusinessInvitation::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'invited_email' => $invitee->email,
            'invited_user_id' => $invitee->id,
            'role' => 'staff',
            'token' => Str::random(64),
            'invited_by_user_id' => $this->owner->id,
            'status' => BusinessInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(14),
        ]);

        $response = $this->actingAs($this->owner)
            ->delete(route('memberships.invitations.revoke', ['invitation' => $invitation->id]));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('business_invitations', [
            'id' => $invitation->id,
            'status' => BusinessInvitation::STATUS_REVOKED,
        ]);
    }

    public function test_user_cannot_revoke_invitation_from_another_business(): void
    {
        $otherBusiness = Business::factory()->create();
        $invitee = User::factory()->create(['email_verified_at' => now()]);

        $invitation = BusinessInvitation::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $otherBusiness->id,
            'invited_email' => $invitee->email,
            'invited_user_id' => $invitee->id,
            'role' => 'staff',
            'token' => Str::random(64),
            'invited_by_user_id' => $this->owner->id,
            'status' => BusinessInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(14),
        ]);

        // BusinessScope filters by $this->business — invitation from another business returns 404.
        $response = $this->actingAs($this->owner)
            ->delete(route('memberships.invitations.revoke', ['invitation' => $invitation->id]));

        $response->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // Update role
    // -------------------------------------------------------------------------

    public function test_owner_can_change_member_role(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $membership = Membership::create([
            'user_id' => $member->id,
            'business_id' => $this->business->id,
            'role' => 'staff',
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($this->owner)
            ->patch(route('memberships.update-role', ['membership' => $membership->id]), [
                'role' => 'guest',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('memberships', [
            'id' => $membership->id,
            'role' => 'guest',
        ]);
    }

    public function test_owner_can_set_member_role_to_none(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $membership = Membership::create([
            'user_id' => $member->id,
            'business_id' => $this->business->id,
            'role' => 'staff',
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($this->owner)
            ->patch(route('memberships.update-role', ['membership' => $membership->id]), [
                'role' => 'none',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('memberships', [
            'id' => $membership->id,
            'role' => 'none',
        ]);
    }

    public function test_none_role_member_can_be_reinstated(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $membership = Membership::create([
            'user_id' => $member->id,
            'business_id' => $this->business->id,
            'role' => 'none',
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($this->owner)
            ->patch(route('memberships.update-role', ['membership' => $membership->id]), [
                'role' => 'staff',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('memberships', [
            'id' => $membership->id,
            'role' => 'staff',
        ]);
    }

    public function test_demoting_last_owner_flashes_error(): void
    {
        $ownerMembership = $this->ownerMembership();

        $response = $this->actingAs($this->owner)
            ->patch(route('memberships.update-role', ['membership' => $ownerMembership->id]), [
                'role' => 'staff',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('memberships', [
            'id' => $ownerMembership->id,
            'role' => 'owner',
        ]);
    }

    // -------------------------------------------------------------------------
    // Remove member
    // -------------------------------------------------------------------------

    public function test_owner_can_remove_member(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $membership = Membership::create([
            'user_id' => $member->id,
            'business_id' => $this->business->id,
            'role' => 'staff',
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($this->owner)
            ->delete(route('memberships.destroy', ['membership' => $membership->id]));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('memberships', ['id' => $membership->id]);
    }

    public function test_removing_last_owner_flashes_error(): void
    {
        $ownerMembership = $this->ownerMembership();

        $response = $this->actingAs($this->owner)
            ->delete(route('memberships.destroy', ['membership' => $ownerMembership->id]));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('memberships', [
            'id' => $ownerMembership->id,
            'deleted_at' => null,
        ]);
    }

    // -------------------------------------------------------------------------
    // Acknowledge (dismiss status notice)
    // -------------------------------------------------------------------------

    public function test_acknowledge_sets_acknowledged_at(): void
    {
        $invitee = User::factory()->create(['email_verified_at' => now()]);
        $this->giveUserOwnBusiness($invitee);

        $invitation = BusinessInvitation::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'invited_email' => $invitee->email,
            'invited_user_id' => $invitee->id,
            'role' => 'staff',
            'token' => Str::random(64),
            'invited_by_user_id' => $this->owner->id,
            'status' => BusinessInvitation::STATUS_ACCEPTED,
            'expires_at' => now()->addDays(14),
        ]);

        $this->assertNull($invitation->acknowledged_at);

        $response = $this->actingAs($invitee)
            ->post(route('invitations.acknowledge'), ['invitation_id' => $invitation->id]);

        $response->assertRedirect();
        $this->assertNotNull($invitation->fresh()->acknowledged_at);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function ownerMembership(): Membership
    {
        return Membership::withoutGlobalScope(BusinessScope::class)
            ->where('user_id', $this->owner->id)
            ->where('business_id', $this->business->id)
            ->firstOrFail();
    }

    /** Give a user their own business so EnsureHasBusiness middleware passes. */
    private function giveUserOwnBusiness(User $user): Business
    {
        $business = Business::factory()->create();
        Membership::create([
            'user_id' => $user->id,
            'business_id' => $business->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        return $business;
    }
}
