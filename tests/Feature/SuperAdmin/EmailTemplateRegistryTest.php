<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\EmailTemplate;
use App\Support\EmailTemplateRegistry;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailTemplateRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmailTemplateSeeder::class);
    }

    /**
     * Guards against the registry drifting from shipped template copy: every
     * active template's subject/body may only use tokens the registry knows.
     * If this fails, an admin would be blocked from re-activating that template
     * after an edit (and the variable sidebar would be wrong).
     */
    public function test_active_seeded_templates_only_use_registered_tokens(): void
    {
        $active = EmailTemplate::where('is_active', true)->get();

        $this->assertNotEmpty($active, 'Expected at least one active seeded template.');

        foreach ($active as $template) {
            $unknown = EmailTemplateRegistry::unknownTokens(
                $template->key,
                $template->subject,
                $template->body_html,
                $template->body_text,
            );

            $this->assertSame(
                [],
                $unknown,
                "Template [{$template->key}] uses tokens not registered in EmailTemplateRegistry: "
                    .implode(', ', $unknown),
            );
        }
    }

    public function test_user_name_base_token_is_available_to_every_registered_template(): void
    {
        foreach (EmailTemplate::all() as $template) {
            $this->assertContains(
                'user_name',
                EmailTemplateRegistry::tokensFor($template->key),
                "Base token user_name missing from registered template [{$template->key}].",
            );
        }
    }

    public function test_unregistered_key_exposes_no_tokens(): void
    {
        $this->assertSame([], EmailTemplateRegistry::tokensFor('does_not_exist'));
        $this->assertSame([], EmailTemplateRegistry::variablesFor('does_not_exist'));
    }

    public function test_unknown_tokens_are_flagged(): void
    {
        $unknown = EmailTemplateRegistry::unknownTokens(
            'member_left_business',
            'Hi {{user_name}}',
            '{{actor_name}} left {{business_name}} — {{bogus_token}}',
            '',
        );

        $this->assertSame(['bogus_token'], $unknown);
    }
}
