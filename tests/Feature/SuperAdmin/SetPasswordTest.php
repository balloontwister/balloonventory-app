<?php

namespace Tests\Feature\SuperAdmin;

use App\Mail\TemplatedMailable;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SetPasswordTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $siteAdmin;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdmin()->create([
            'email_verified_at' => now(),
        ]);

        $this->siteAdmin = User::factory()->siteAdmin()->create([
            'email_verified_at' => now(),
        ]);

        $this->regularUser = User::factory()->create([
            'email' => 'regular@example.com',
            'email_verified_at' => now(),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Seed an active password_changed_by_admin template. */
    private function seedActivePasswordTemplate(): EmailTemplate
    {
        return EmailTemplate::create([
            'key' => 'password_changed_by_admin',
            'label' => 'Password Changed by Admin',
            'trigger_description' => 'Test template.',
            'subject' => 'Your password was changed',
            'body_html' => '<p>Hi {{user_name}}, your password was changed.</p>',
            'body_text' => 'Hi {{user_name}}, your password was changed.',
            'is_active' => true,
        ]);
    }

    // ── Happy paths ───────────────────────────────────────────────────────────

    public function test_super_admin_can_set_a_regular_users_password(): void
    {
        $oldHash = $this->regularUser->password;

        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.users.set-password', $this->regularUser), [
                'password' => 'NewSecurePassword1!',
                'password_confirmation' => 'NewSecurePassword1!',
                'notify' => false,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->regularUser->refresh();
        $this->assertNotEquals($oldHash, $this->regularUser->password);
        $this->assertTrue(Hash::check('NewSecurePassword1!', $this->regularUser->password));
    }

    public function test_site_admin_can_set_a_regular_users_password(): void
    {
        $oldHash = $this->regularUser->password;

        $response = $this->actingAs($this->siteAdmin)
            ->post(route('admin.users.set-password', $this->regularUser), [
                'password' => 'AnotherSecret99!',
                'password_confirmation' => 'AnotherSecret99!',
                'notify' => false,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->regularUser->refresh();
        $this->assertTrue(Hash::check('AnotherSecret99!', $this->regularUser->password));
    }

    public function test_new_password_can_be_used_to_authenticate(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('admin.users.set-password', $this->regularUser), [
                'password' => 'LoginPassword1!',
                'password_confirmation' => 'LoginPassword1!',
                'notify' => false,
            ]);

        // Log out and attempt login with the new password.
        $this->post(route('login'), [
            'email' => $this->regularUser->email,
            'password' => 'LoginPassword1!',
        ])->assertSessionHasNoErrors();

        $this->assertAuthenticated();
    }

    public function test_remember_token_is_rotated_on_set_password(): void
    {
        $oldToken = $this->regularUser->remember_token;

        $this->actingAs($this->superAdmin)
            ->post(route('admin.users.set-password', $this->regularUser), [
                'password' => 'TokenRotate1!',
                'password_confirmation' => 'TokenRotate1!',
                'notify' => false,
            ]);

        $this->regularUser->refresh();
        $this->assertNotEquals($oldToken, $this->regularUser->remember_token);
    }

    // ── Notify behaviour ──────────────────────────────────────────────────────

    public function test_notify_true_sends_password_changed_mail_to_user(): void
    {
        Mail::fake();
        $this->seedActivePasswordTemplate();

        $this->actingAs($this->superAdmin)
            ->post(route('admin.users.set-password', $this->regularUser), [
                'password' => 'NotifyEnabled1!',
                'password_confirmation' => 'NotifyEnabled1!',
                'notify' => true,
            ]);

        Mail::assertQueued(TemplatedMailable::class, function ($mail) {
            return $mail->hasTo($this->regularUser->email);
        });
    }

    public function test_notify_true_flashes_notified_success_message(): void
    {
        Mail::fake();
        $this->seedActivePasswordTemplate();

        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.users.set-password', $this->regularUser), [
                'password' => 'NotifyMsg1!',
                'password_confirmation' => 'NotifyMsg1!',
                'notify' => true,
            ]);

        $response->assertSessionHas('success');
    }

    public function test_notify_false_sends_no_mail(): void
    {
        Mail::fake();

        $this->actingAs($this->superAdmin)
            ->post(route('admin.users.set-password', $this->regularUser), [
                'password' => 'NoNotify1!',
                'password_confirmation' => 'NoNotify1!',
                'notify' => false,
            ]);

        Mail::assertNothingQueued();
        Mail::assertNothingSent();
    }

    public function test_notify_true_with_no_email_sends_nothing_but_still_succeeds(): void
    {
        Mail::fake();

        $userNoEmail = User::factory()->create([
            'email_verified_at' => null,
        ]);
        // Clear email using DB directly — the column is NOT NULL at schema level,
        // but the controller still guards against empty/falsy email values.
        DB::table('users')->where('id', $userNoEmail->id)->update(['email' => '']);
        $userNoEmail->refresh();

        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.users.set-password', $userNoEmail), [
                'password' => 'NoEmail1!',
                'password_confirmation' => 'NoEmail1!',
                'notify' => true,
            ]);

        Mail::assertNothingQueued();
        Mail::assertNothingSent();
        $response->assertRedirect();
        $response->assertSessionHas('warning');

        // Password was still changed.
        $userNoEmail->refresh();
        $this->assertTrue(Hash::check('NoEmail1!', $userNoEmail->password));
    }

    public function test_notify_true_with_inactive_template_sends_nothing_but_still_succeeds(): void
    {
        Mail::fake();

        // Seed an INACTIVE template (no active template means forKey returns null).
        EmailTemplate::create([
            'key' => 'password_changed_by_admin',
            'label' => 'Password Changed by Admin',
            'trigger_description' => 'Test.',
            'subject' => 'Your password was changed',
            'body_html' => '<p>Hi {{user_name}}.</p>',
            'body_text' => 'Hi {{user_name}}.',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.users.set-password', $this->regularUser), [
                'password' => 'InactiveTemplate1!',
                'password_confirmation' => 'InactiveTemplate1!',
                'notify' => true,
            ]);

        Mail::assertNothingQueued();
        Mail::assertNothingSent();
        $response->assertRedirect();
        $response->assertSessionHas('warning');

        // Password was still changed.
        $this->regularUser->refresh();
        $this->assertTrue(Hash::check('InactiveTemplate1!', $this->regularUser->password));
    }

    // ── Logout sessions behaviour ─────────────────────────────────────────────

    public function test_logout_sessions_true_deletes_target_user_sessions(): void
    {
        DB::table('sessions')->insert([
            'id' => 'test-session-abc123',
            'user_id' => $this->regularUser->id,
            'ip_address' => null,
            'user_agent' => null,
            'payload' => '',
            'last_activity' => time(),
        ]);

        $this->assertEquals(
            1,
            DB::table('sessions')->where('user_id', $this->regularUser->id)->count(),
        );

        $this->actingAs($this->superAdmin)
            ->post(route('admin.users.set-password', $this->regularUser), [
                'password' => 'LogoutSessions1!',
                'password_confirmation' => 'LogoutSessions1!',
                'notify' => false,
                'logout_sessions' => true,
            ]);

        $this->assertEquals(
            0,
            DB::table('sessions')->where('user_id', $this->regularUser->id)->count(),
        );
    }

    public function test_logout_sessions_false_preserves_target_user_sessions(): void
    {
        DB::table('sessions')->insert([
            'id' => 'test-session-preserve123',
            'user_id' => $this->regularUser->id,
            'ip_address' => null,
            'user_agent' => null,
            'payload' => '',
            'last_activity' => time(),
        ]);

        $this->actingAs($this->superAdmin)
            ->post(route('admin.users.set-password', $this->regularUser), [
                'password' => 'PreserveSessions1!',
                'password_confirmation' => 'PreserveSessions1!',
                'notify' => false,
                'logout_sessions' => false,
            ]);

        $this->assertEquals(
            1,
            DB::table('sessions')->where('user_id', $this->regularUser->id)->count(),
        );
    }

    public function test_logout_sessions_omitted_preserves_target_user_sessions(): void
    {
        DB::table('sessions')->insert([
            'id' => 'test-session-omit123',
            'user_id' => $this->regularUser->id,
            'ip_address' => null,
            'user_agent' => null,
            'payload' => '',
            'last_activity' => time(),
        ]);

        $this->actingAs($this->superAdmin)
            ->post(route('admin.users.set-password', $this->regularUser), [
                'password' => 'OmitSessions1!',
                'password_confirmation' => 'OmitSessions1!',
                'notify' => false,
            ]);

        $this->assertEquals(
            1,
            DB::table('sessions')->where('user_id', $this->regularUser->id)->count(),
        );
    }

    // ── Authorization / 422 guards ────────────────────────────────────────────

    public function test_admin_cannot_set_their_own_password_via_this_endpoint(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.users.set-password', $this->superAdmin), [
                'password' => 'SelfSet1!',
                'password_confirmation' => 'SelfSet1!',
                'notify' => false,
            ]);

        $response->assertStatus(422);
    }

    public function test_cannot_set_password_for_a_site_admin(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.users.set-password', $this->siteAdmin), [
                'password' => 'AdminSet1!',
                'password_confirmation' => 'AdminSet1!',
                'notify' => false,
            ]);

        $response->assertStatus(422);
    }

    public function test_cannot_set_password_for_a_super_admin(): void
    {
        $anotherSuperAdmin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.users.set-password', $anotherSuperAdmin), [
                'password' => 'SuperSet1!',
                'password_confirmation' => 'SuperSet1!',
                'notify' => false,
            ]);

        $response->assertStatus(422);
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function test_mismatched_confirmation_is_rejected(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.users.set-password', $this->regularUser), [
                'password' => 'CorrectPass1!',
                'password_confirmation' => 'WrongConfirm1!',
                'notify' => false,
            ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_too_short_password_is_rejected(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.users.set-password', $this->regularUser), [
                'password' => 'short',
                'password_confirmation' => 'short',
                'notify' => false,
            ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_unauthenticated_request_is_redirected_to_login(): void
    {
        $response = $this->post(route('admin.users.set-password', $this->regularUser), [
            'password' => 'GuestAttempt1!',
            'password_confirmation' => 'GuestAttempt1!',
            'notify' => false,
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_regular_user_cannot_access_set_password_endpoint(): void
    {
        $target = User::factory()->create();

        $response = $this->actingAs($this->regularUser)
            ->post(route('admin.users.set-password', $target), [
                'password' => 'RegularSet1!',
                'password_confirmation' => 'RegularSet1!',
                'notify' => false,
            ]);

        $response->assertForbidden();
    }
}
