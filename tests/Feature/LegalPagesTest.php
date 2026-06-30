<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_policy_page_is_reachable_while_unauthenticated(): void
    {
        $paths = ['/legal', '/terms', '/privacy', '/cookies', '/acceptable-use', '/refunds'];

        foreach ($paths as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_hub_lists_every_document(): void
    {
        $this->get('/legal')->assertInertia(fn (Assert $page) => $page
            ->component('Legal/Index')
            ->has('documents', 5)
            ->where('documents.0.doc', 'terms')
        );
    }

    public function test_policy_renders_markdown_to_html(): void
    {
        $this->get('/terms')->assertInertia(fn (Assert $page) => $page
            ->component('Legal/Show')
            ->where('doc', 'terms')
            ->where('title', 'Terms of Service')
            ->where('html', fn (string $html) => str_contains($html, '<h2')
                && str_contains($html, 'Acceptance of these terms'))
        );
    }

    public function test_missing_locale_falls_back_to_english(): void
    {
        // A Spanish-locale user with no es Markdown file still gets English prose.
        $user = User::factory()->create(['locale' => 'es']);

        $this->actingAs($user)
            ->get('/terms')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('html', fn (string $html) => str_contains($html, 'Acceptance of these terms'))
            );
    }

    public function test_unknown_policy_path_returns_404(): void
    {
        $this->get('/legal/does-not-exist')->assertNotFound();
        $this->get('/not-a-real-policy')->assertNotFound();
    }
}
