<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\BusinessAccessGranted;
use App\Notifications\InvitationAccepted;
use App\Notifications\MemberLeftBusiness;
use App\Notifications\MemberRemoved;
use App\Notifications\MemberRoleChanged;
use App\Scopes\BusinessScope;
use App\Support\BusinessContext;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class NotificationTest extends TestCase
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

        $this->makeMembership($this->owner, 'owner');

        BusinessContext::set($this->business->id);
    }

    protected function tearDown(): void
    {
        BusinessContext::clear();
        parent::tearDown();
    }

    private function makeMembership(User $user, string $role): Membership
    {
        return Membership::create([
            'user_id' => $user->id,
            'business_id' => $this->business->id,
            'role' => $role,
            'joined_at' => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Dispatch
    // -------------------------------------------------------------------------

    public function test_member_leaving_notifies_every_owner(): void
    {
        Notification::fake();

        $secondOwner = User::factory()->create(['email_verified_at' => now()]);
        $this->makeMembership($secondOwner, 'owner');

        $staff = User::factory()->create(['email_verified_at' => now()]);
        $staffMembership = $this->makeMembership($staff, 'staff');

        $this->actingAs($staff)
            ->delete(route('memberships.leave', $staffMembership))
            ->assertRedirect();

        Notification::assertSentTo($this->owner, MemberLeftBusiness::class);
        Notification::assertSentTo($secondOwner, MemberLeftBusiness::class);
        Notification::assertNotSentTo($staff, MemberLeftBusiness::class);
    }

    public function test_role_change_notifies_the_affected_member(): void
    {
        Notification::fake();

        $staff = User::factory()->create(['email_verified_at' => now()]);
        $membership = $this->makeMembership($staff, 'staff');

        $this->actingAs($this->owner)
            ->patch(route('memberships.update-role', $membership), ['role' => 'guest'])
            ->assertRedirect();

        Notification::assertSentTo($staff, MemberRoleChanged::class);
    }

    public function test_removing_a_member_sends_email_only_notification(): void
    {
        Notification::fake();

        $staff = User::factory()->create(['email_verified_at' => now()]);
        $membership = $this->makeMembership($staff, 'staff');

        $this->actingAs($this->owner)
            ->delete(route('memberships.destroy', $membership))
            ->assertRedirect();

        Notification::assertSentTo(
            $staff,
            MemberRemoved::class,
            fn (MemberRemoved $notification, array $channels) => $channels === ['mail'],
        );
    }

    public function test_accepting_invitation_notifies_joiner_and_owner(): void
    {
        Notification::fake();

        // The in-app accept path requires the invitee to already belong to a
        // business of their own so they clear the EnsureHasBusiness middleware.
        $invitee = User::factory()->create(['email_verified_at' => now()]);
        $inviteeBusiness = Business::factory()->create();
        Membership::create([
            'user_id' => $invitee->id,
            'business_id' => $inviteeBusiness->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

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

        $this->actingAs($invitee)
            ->post(route('invitations.accept-in-app'), ['token' => $invitation->token])
            ->assertRedirect();

        Notification::assertSentTo($invitee, BusinessAccessGranted::class);
        Notification::assertSentTo($this->owner, InvitationAccepted::class);
    }

    // -------------------------------------------------------------------------
    // Channels + database notice
    // -------------------------------------------------------------------------

    public function test_member_left_uses_both_database_and_mail_when_template_active(): void
    {
        $notification = new MemberLeftBusiness($this->business->id, $this->business->name, 'Jordan');

        $this->assertSame(['database', 'mail'], $notification->via($this->owner));
    }

    public function test_leaving_writes_a_database_notice_for_the_owner(): void
    {
        $staff = User::factory()->create(['email_verified_at' => now()]);
        $staffMembership = $this->makeMembership($staff, 'staff');

        $this->actingAs($staff)
            ->delete(route('memberships.leave', $staffMembership))
            ->assertRedirect();

        $notice = $this->owner->fresh()->unreadNotifications()->first();

        $this->assertNotNull($notice);
        $this->assertSame('member_left', $notice->data['type']);
        $this->assertSame($this->business->id, $notice->data['business_id']);
    }

    // -------------------------------------------------------------------------
    // Dismiss
    // -------------------------------------------------------------------------

    public function test_dismissing_a_notification_deletes_it(): void
    {
        $this->owner->notify(new BusinessAccessGranted($this->business->id, $this->business->name, 'Artist'));

        $notice = $this->owner->notifications()->first();
        $this->assertNotNull($notice);

        $this->actingAs($this->owner)
            ->delete(route('notifications.destroy', $notice->id))
            ->assertRedirect();

        $this->assertSame(0, $this->owner->fresh()->notifications()->count());
    }

    public function test_a_read_notification_can_still_be_dismissed(): void
    {
        $this->owner->notify(new BusinessAccessGranted($this->business->id, $this->business->name, 'Artist'));
        $notice = $this->owner->notifications()->first();
        $notice->markAsRead();

        $this->actingAs($this->owner)
            ->delete(route('notifications.destroy', $notice->id))
            ->assertRedirect();

        $this->assertSame(0, $this->owner->fresh()->notifications()->count());
    }

    public function test_user_cannot_dismiss_another_users_notification(): void
    {
        $other = User::factory()->create(['email_verified_at' => now()]);
        $other->notify(new BusinessAccessGranted($this->business->id, $this->business->name, 'Artist'));

        $notice = $other->notifications()->first();

        $this->actingAs($this->owner)
            ->delete(route('notifications.destroy', $notice->id))
            ->assertRedirect();

        $this->assertSame(1, $other->fresh()->notifications()->count());
    }

    public function test_user_can_mark_all_notifications_read(): void
    {
        $this->owner->notify(new BusinessAccessGranted($this->business->id, $this->business->name, 'Artist'));
        $this->owner->notify(new BusinessAccessGranted($this->business->id, $this->business->name, 'Manager'));
        $this->assertSame(2, $this->owner->unreadNotifications()->count());

        $this->actingAs($this->owner)
            ->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, $this->owner->fresh()->unreadNotifications()->count());
    }

    public function test_bell_shares_unread_only_to_every_page(): void
    {
        // One unread, one read — the bell (recent) should surface only the unread.
        $this->owner->notify(new BusinessAccessGranted($this->business->id, $this->business->name, 'Artist'));
        $this->owner->notify(new BusinessAccessGranted($this->business->id, $this->business->name, 'Manager'));
        $this->owner->notifications()->first()->markAsRead();

        $this->actingAs($this->owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('notifications.unreadCount', 1)
                ->has('notifications.recent', 1)
                ->where('notifications.recent.0.read_at', null)
            );
    }

    public function test_index_page_lists_all_notifications(): void
    {
        $this->owner->notify(new BusinessAccessGranted($this->business->id, $this->business->name, 'Artist'));
        $this->owner->notify(new BusinessAccessGranted($this->business->id, $this->business->name, 'Manager'));

        $this->actingAs($this->owner)
            ->get(route('notifications.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Notifications/Index')
                ->where('filter', 'all')
                ->where('unreadCount', 2)
                ->has('notifications.data', 2)
            );
    }

    public function test_index_page_unread_filter_excludes_read_notifications(): void
    {
        $this->owner->notify(new BusinessAccessGranted($this->business->id, $this->business->name, 'Artist'));
        $this->owner->notify(new BusinessAccessGranted($this->business->id, $this->business->name, 'Manager'));
        $this->owner->notifications()->latest()->first()->markAsRead();

        $this->actingAs($this->owner)
            ->get(route('notifications.index', ['filter' => 'unread']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filter', 'unread')
                ->has('notifications.data', 1)
            );
    }
}
