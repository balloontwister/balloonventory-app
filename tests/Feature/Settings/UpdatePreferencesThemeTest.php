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

class UpdatePreferencesThemeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->user = User::factory()->create([
            'email_verified_at' => now(),
            'locale' => 'en',
        ]);

        $this->business = Business::factory()->create();

        Membership::create([
            'user_id' => $this->user->id,
            'business_id' => $this->business->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        BusinessContext::set($this->business->id);
    }

    public function test_user_can_set_theme_to_dark(): void
    {
        $this->actingAs($this->user)
            ->patch(route('settings.preferences.update'), [
                'locale' => 'en',
                'theme' => 'dark',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'theme' => 'dark',
        ]);
    }

    public function test_user_can_set_theme_to_light(): void
    {
        $this->actingAs($this->user)
            ->patch(route('settings.preferences.update'), [
                'locale' => 'en',
                'theme' => 'light',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'theme' => 'light',
        ]);
    }

    public function test_user_can_set_theme_to_system(): void
    {
        $this->actingAs($this->user)
            ->patch(route('settings.preferences.update'), [
                'locale' => 'en',
                'theme' => 'system',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'theme' => 'system',
        ]);
    }

    public function test_invalid_theme_value_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->patch(route('settings.preferences.update'), [
                'locale' => 'en',
                'theme' => 'vampire',
            ])
            ->assertSessionHasErrors('theme');
    }

    public function test_theme_is_required(): void
    {
        $this->actingAs($this->user)
            ->patch(route('settings.preferences.update'), [
                'locale' => 'en',
            ])
            ->assertSessionHasErrors('theme');
    }

    public function test_unauthenticated_user_cannot_update_theme(): void
    {
        $this->patch(route('settings.preferences.update'), [
            'locale' => 'en',
            'theme' => 'dark',
        ])
            ->assertRedirect(route('login'));
    }
}
