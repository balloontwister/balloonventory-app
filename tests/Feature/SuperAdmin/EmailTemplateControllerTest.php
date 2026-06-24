<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\EmailTemplate;
use App\Models\User;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailTemplateControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmailTemplateSeeder::class);
        $this->admin = User::factory()->superAdmin()->create(['email_verified_at' => now()]);
    }

    private function activeTemplate(): EmailTemplate
    {
        return EmailTemplate::where('key', 'password_changed_by_admin')->firstOrFail(); // seeded active
    }

    private function draftTemplate(): EmailTemplate
    {
        return EmailTemplate::where('key', 'welcome')->firstOrFail(); // seeded inactive
    }

    public function test_saving_an_active_template_with_an_unknown_token_is_blocked(): void
    {
        $template = $this->activeTemplate();

        $this->actingAs($this->admin)
            ->patch(route('admin.email-templates.update', $template), [
                'subject' => $template->subject,
                'body_html' => $template->body_html.' {{bogus_token}}',
                'body_text' => $template->body_text,
                'action' => 'save',
            ])
            ->assertSessionHasErrors('body_html');

        $this->assertStringNotContainsString('bogus_token', $template->fresh()->body_html);
    }

    public function test_saving_an_active_template_with_known_tokens_succeeds(): void
    {
        $template = $this->activeTemplate();

        $this->actingAs($this->admin)
            ->patch(route('admin.email-templates.update', $template), [
                'subject' => $template->subject,
                'body_html' => '<p>Hi {{user_name}}, visit {{app_url}}.</p>',
                'body_text' => 'Hi {{user_name}}',
                'action' => 'save',
            ])
            ->assertSessionHasNoErrors();

        $this->assertStringContainsString('{{user_name}}', $template->fresh()->body_html);
        $this->assertTrue($template->fresh()->is_active);
    }

    public function test_saving_an_inactive_draft_with_an_unknown_token_is_allowed(): void
    {
        $template = $this->draftTemplate();

        $this->actingAs($this->admin)
            ->patch(route('admin.email-templates.update', $template), [
                'subject' => 'Hello',
                'body_html' => 'Hi {{user_name}} {{work_in_progress_token}}',
                'body_text' => 'Hi {{user_name}}',
                'action' => 'save',
            ])
            ->assertSessionHasNoErrors();

        $this->assertStringContainsString('work_in_progress_token', $template->fresh()->body_html);
        $this->assertFalse($template->fresh()->is_active);
    }

    public function test_activating_a_template_with_an_unknown_token_is_blocked(): void
    {
        $template = $this->draftTemplate();

        $this->actingAs($this->admin)
            ->patch(route('admin.email-templates.update', $template), [
                'subject' => 'Hello',
                'body_html' => 'Hi {{user_name}} {{bogus_token}}',
                'body_text' => 'Hi {{user_name}}',
                'action' => 'activate',
            ])
            ->assertSessionHasErrors('body_html');

        $this->assertFalse($template->fresh()->is_active);
    }
}
