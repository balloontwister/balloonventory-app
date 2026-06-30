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

    public function test_spanish_locale_is_served_when_a_translation_exists(): void
    {
        $user = User::factory()->create(['locale' => 'es']);

        $this->actingAs($user)
            ->get('/terms')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('html', fn (string $html) => str_contains($html, 'términos'))
            );
    }

    public function test_untranslated_locale_falls_back_to_english(): void
    {
        // Capture the default (English) render… (full closure so the by-reference
        // capture propagates — an arrow fn would capture $englishHtml by value).
        $englishHtml = null;
        $this->get('/terms')->assertInertia(function (Assert $page) use (&$englishHtml) {
            return $page->where('html', function (string $html) use (&$englishHtml) {
                $englishHtml = $html;

                return true;
            });
        });

        // …a locale with no Markdown files (e.g. fr) renders identical English prose.
        $user = User::factory()->create(['locale' => 'fr']);

        $this->actingAs($user)
            ->get('/terms')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('html', fn (string $html) => $html === $englishHtml)
            );
    }

    public function test_unknown_policy_path_returns_404(): void
    {
        $this->get('/legal/does-not-exist')->assertNotFound();
        $this->get('/not-a-real-policy')->assertNotFound();
    }
}
