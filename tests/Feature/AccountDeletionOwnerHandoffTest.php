<?php

namespace Tests\Feature;

use App\Enums\BusinessFrozenReason;
use App\Mail\TemplatedMailable;
use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\Membership;
use App\Models\User;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountDeletionOwnerHandoffTest extends TestCase
{
    use RefreshDatabase;

    private function makeMember(Business $business, string $role): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        Membership::create([
            'user_id' => $user->id,
            'business_id' => $business->id,
            'role' => $role,
            'joined_at' => now(),
        ]);

        return $user;
    }

    private function membershipFor(User $user): Membership
    {
        return Membership::withoutGlobalScopes()->where('user_id', $user->id)->firstOrFail();
    }

    private function invitationFor(Business $business, User $user): ?BusinessInvitation
    {
        return BusinessInvitation::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('invited_user_id', $user->id)
            ->first();
    }

    public function test_co_owned_business_is_left_untouched(): void
    {
        $business = Business::factory()->create();
        $owner = $this->makeMember($business, 'owner');
        $coOwner = $this->makeMember($business, 'owner');

        $this->actingAs($owner)
            ->delete('/profile', ['password' => 'password'])
            ->assertRedirect('/');

        $business->refresh();
        $this->assertNull($business->frozen_at, 'A co-owned business must not be frozen.');
        $this->assertSame('owner', $this->membershipFor($coOwner)->role);
        $this->assertSoftDeleted($owner);
        $this->assertSoftDeleted('memberships', ['user_id' => $owner->id]);
    }

    public function test_nominating_a_successor_freezes_and_invites_them(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        Mail::fake();

        $business = Business::factory()->create();
        $owner = $this->makeMember($business, 'owner');
        $staff = $this->makeMember($business, 'staff');

        $this->actingAs($owner)
            ->delete('/profile', [
                'password' => 'password',
                'handoffs' => [$business->id => $staff->id],
            ])
            ->assertRedirect('/');

        $business->refresh();
        $this->assertNotNull($business->frozen_at, 'A pending handoff must freeze the business.');
        $this->assertSame(BusinessFrozenReason::OwnershipTransfer, $business->frozen_reason);

        // The successor is invited, not yet promoted — they must accept first.
        $this->assertSame('staff', $this->membershipFor($staff)->role);
        $invitation = $this->invitationFor($business, $staff);
        $this->assertNotNull($invitation);
        $this->assertSame('owner', $invitation->role);
        $this->assertSame(BusinessInvitation::STATUS_PENDING, $invitation->status);

        // The nominee is emailed the ownership-transfer template (queued mailable).
        Mail::assertQueued(
            TemplatedMailable::class,
            fn (TemplatedMailable $mail) => $mail->hasTo($staff->email),
        );

        $this->assertSoftDeleted($owner);
    }

    public function test_accepting_the_transfer_promotes_the_successor_and_thaws_the_business(): void
    {
        Mail::fake();
        Notification::fake();

        $business = Business::factory()->create();
        $owner = $this->makeMember($business, 'owner');
        $staff = $this->makeMember($business, 'staff');

        $this->actingAs($owner)->delete('/profile', [
            'password' => 'password',
            'handoffs' => [$business->id => $staff->id],
        ]);

        $token = $this->invitationFor($business, $staff)->token;

        // The successor follows the magic link (logs them in) and accepts.
        $this->get(route('invitations.accept', ['token' => $token]))
            ->assertRedirect(route('dashboard'));

        $business->refresh();
        $this->assertNull($business->frozen_at, 'Accepting the transfer must thaw the business.');
        $this->assertNull($business->frozen_reason);
        $this->assertSame('owner', $this->membershipFor($staff)->role, 'The successor must become owner.');
    }

    public function test_declined_handoff_freezes_the_business_as_ownerless(): void
    {
        $business = Business::factory()->create();
        $owner = $this->makeMember($business, 'owner');
        $staff = $this->makeMember($business, 'staff');

        $this->actingAs($owner)
            ->delete('/profile', [
                'password' => 'password',
                'handoffs' => [$business->id => ''],
            ])
            ->assertRedirect('/');

        $business->refresh();
        $this->assertNotNull($business->frozen_at);
        $this->assertSame(BusinessFrozenReason::Ownerless, $business->frozen_reason);
        $this->assertSame('staff', $this->membershipFor($staff)->role, 'Declining must not promote anyone.');
        $this->assertNull($this->invitationFor($business, $staff), 'Declining must not create an invitation.');
        $this->assertSoftDeleted($owner);
    }

    public function test_solo_owned_business_is_frozen_as_ownerless(): void
    {
        $business = Business::factory()->create();
        $owner = $this->makeMember($business, 'owner');

        $this->actingAs($owner)
            ->delete('/profile', ['password' => 'password'])
            ->assertRedirect('/');

        $business->refresh();
        $this->assertNotNull($business->frozen_at);
        $this->assertSame(BusinessFrozenReason::Ownerless, $business->frozen_reason);
        $this->assertSoftDeleted($owner);
    }

    public function test_ownership_cannot_be_handed_to_a_non_member(): void
    {
        $business = Business::factory()->create();
        $owner = $this->makeMember($business, 'owner');
        $this->makeMember($business, 'staff');
        $outsider = User::factory()->create();

        $this->actingAs($owner)
            ->delete('/profile', [
                'password' => 'password',
                'handoffs' => [$business->id => $outsider->id],
            ])
            ->assertRedirect('/');

        $business->refresh();
        // Fail-safe: a tampered id grants nothing and the business is frozen instead.
        $this->assertSame(BusinessFrozenReason::Ownerless, $business->frozen_reason);
        $this->assertNull($this->invitationFor($business, $outsider));
        $this->assertSame(0, Membership::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('user_id', $outsider->id)
            ->count(), 'A non-member must never receive a membership.');
    }

    public function test_admin_force_delete_freezes_sole_owned_business(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $business = Business::factory()->create();
        $owner = $this->makeMember($business, 'owner');

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $owner))
            ->assertSessionHasNoErrors();

        $business->refresh();
        $this->assertNotNull($business->frozen_at);
        $this->assertSame(BusinessFrozenReason::Ownerless, $business->frozen_reason);
        $this->assertSoftDeleted($owner);
        $this->assertSoftDeleted('memberships', ['user_id' => $owner->id]);
    }

    public function test_admin_force_delete_leaves_co_owned_business_active(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $business = Business::factory()->create();
        $owner = $this->makeMember($business, 'owner');
        $coOwner = $this->makeMember($business, 'owner');

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $owner))
            ->assertSessionHasNoErrors();

        $business->refresh();
        $this->assertNull($business->frozen_at);
        $this->assertSame('owner', $this->membershipFor($coOwner)->role);
    }

    public function test_deleting_an_account_frees_the_email_for_reregistration(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'reuse@example.com']);

        $this->actingAs($user)
            ->delete('/profile', ['password' => 'password'])
            ->assertRedirect('/');

        // The deleted row tombstones the address and preserves the original.
        $deleted = User::withTrashed()->find($user->id);
        $this->assertSame($user->id.'@deleted.invalid', $deleted->email);
        $this->assertSame('reuse@example.com', $deleted->original_email);

        // The freed address now registers cleanly (previously a duplicate-key 500).
        $this->post('/register', [
            'name' => 'Fresh Start',
            'email' => 'reuse@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => true,
        ])->assertSessionHasNoErrors();

        $fresh = User::where('email', 'reuse@example.com')->whereNull('deleted_at')->first();
        $this->assertNotNull($fresh);
        $this->assertNotSame($user->id, $fresh->id, 'Re-registration must create a brand-new account, not reactivate the old one.');
    }

    public function test_admin_force_delete_also_frees_the_email(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $user = User::factory()->create(['email' => 'gone@example.com']);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $user))
            ->assertSessionHasNoErrors();

        $deleted = User::withTrashed()->find($user->id);
        $this->assertSame($user->id.'@deleted.invalid', $deleted->email);
        $this->assertSame('gone@example.com', $deleted->original_email);
    }

    public function test_profile_page_exposes_sole_owner_handoff_data(): void
    {
        $business = Business::factory()->create(['name' => 'Balloon Co']);
        $owner = $this->makeMember($business, 'owner');
        $staff = $this->makeMember($business, 'staff');

        $this->actingAs($owner)
            ->get('/profile')
            ->assertInertia(fn (Assert $page) => $page
                ->has('accountDeletion.soleOwnerBusinesses', 1)
                ->where('accountDeletion.soleOwnerBusinesses.0.name', 'Balloon Co')
                ->where('accountDeletion.soleOwnerBusinesses.0.candidates.0.userId', $staff->id)
                ->where('accountDeletion.soleOwnerBusinesses.0.candidates.0.role', 'staff')
            );
    }
}
