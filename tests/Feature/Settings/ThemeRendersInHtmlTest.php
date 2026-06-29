<?php

namespace Tests\Feature\Settings;

use App\Models\Business;
use App\Models\Membership;
use App\Models\User;
use App\Support\BusinessContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ThemeRendersInHtmlTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithTheme(string $theme): User
    {
        $this->seed(PermissionSeeder::class);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'locale' => 'en',
            'theme' => $theme,
        ]);

        $business = Business::factory()->create();

        Membership::create([
            'user_id' => $user->id,
            'business_id' => $business->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        BusinessContext::set($business->id);

        return $user;
    }

    public function test_dark_theme_renders_dark_class_on_html(): void
    {
        $user = $this->makeUserWithTheme('dark');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('class="dark"', false);
    }

    public function test_light_theme_does_not_render_dark_class(): void
    {
        $user = $this->makeUserWithTheme('light');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('class="dark"', false);
    }
}
