<?php

namespace Tests\Feature\SuperAdmin;

use App\Mail\AdminUserMessageMail;
use App\Models\AdminUserMessage;
use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminUserEmailControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_send_a_one_off_email_to_a_user(): void
    {
        Mail::fake();

        $admin = User::factory()->superAdmin()->create();
        $recipient = User::factory()->create(['email' => 'target@example.com', 'name' => 'Pat']);

        $this->actingAs($admin)
            ->post(route('admin.user-emails.store'), [
                'user_id' => $recipient->id,
                'subject' => 'A quick note',
                'body' => "Hello there\nthanks for using Balloonventory.",
                'template_key' => null,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertSent(AdminUserMessageMail::class, function (AdminUserMessageMail $mail) use ($recipient) {
            return $mail->hasTo($recipient->email)
                && $mail->subjectLine === 'A quick note';
        });

        $this->assertDatabaseHas('admin_user_messages', [
            'user_id' => $recipient->id,
            'sender_user_id' => $admin->id,
            'subject' => 'A quick note',
        ]);
    }

    public function test_site_admin_can_also_send_user_email(): void
    {
        Mail::fake();

        $admin = User::factory()->siteAdmin()->create();
        $recipient = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.user-emails.store'), [
                'user_id' => $recipient->id,
                'subject' => 'Hi',
                'body' => 'Body text',
            ])
            ->assertRedirect();

        Mail::assertSent(AdminUserMessageMail::class);
    }

    public function test_regular_user_cannot_send_user_email(): void
    {
        Mail::fake();

        $recipient = User::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.user-emails.store'), [
                'user_id' => $recipient->id,
                'subject' => 'Hi',
                'body' => 'Body',
            ])
            ->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_subject_and_body_are_required(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $recipient = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.user-emails.store'), [
                'user_id' => $recipient->id,
                'subject' => '',
                'body' => '',
            ])
            ->assertSessionHasErrors(['subject', 'body']);
    }

    public function test_unknown_recipient_is_rejected(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->post(route('admin.user-emails.store'), [
                'user_id' => 'does-not-exist',
                'subject' => 'Hi',
                'body' => 'Body',
            ])
            ->assertSessionHasErrors('user_id');
    }

    public function test_soft_deleted_recipient_is_rejected(): void
    {
        Mail::fake();

        $admin = User::factory()->superAdmin()->create();
        $recipient = User::factory()->create();
        $recipient->delete();

        $this->actingAs($admin)
            ->post(route('admin.user-emails.store'), [
                'user_id' => $recipient->id,
                'subject' => 'Hi',
                'body' => 'Body',
            ])
            ->assertSessionHasErrors('user_id');

        Mail::assertNothingSent();
    }

    public function test_user_search_returns_matches_as_json(): void
    {
        $admin = User::factory()->superAdmin()->create();
        User::factory()->create(['name' => 'Wendy Whisk', 'email' => 'wendy@example.com']);
        User::factory()->create(['name' => 'Carl Confetti', 'email' => 'carl@example.com']);

        $this->actingAs($admin)
            ->getJson(route('admin.users.search', ['q' => 'wendy']))
            ->assertOk()
            ->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.email', 'wendy@example.com');
    }

    public function test_user_search_is_admin_gated(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.users.search', ['q' => 'a']))
            ->assertForbidden();
    }

    public function test_sent_message_body_surfaces_on_user_detail_without_double_listing(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $recipient = User::factory()->create(['email' => 'shown@example.com']);

        AdminUserMessage::create([
            'user_id' => $recipient->id,
            'sender_user_id' => $admin->id,
            'subject' => 'Direct subject',
            'body' => 'The full body to review.',
        ]);

        // Simulate the auto-log row LogSentEmail would create for that send.
        EmailLog::create([
            'to' => $recipient->email,
            'subject' => 'Direct subject',
            'mailable' => 'AdminUserMessageMail',
            'user_id' => $recipient->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.show', $recipient->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SuperAdmin/Users/Show')
                ->has('emails', 1) // the duplicate AdminUserMessageMail EmailLog row is excluded
                ->where('emails.0.subject', 'Direct subject')
                ->where('emails.0.body', 'The full body to review.')
                ->where('emails.0.mailable', 'AdminUserMessageMail')
            );
    }
}
