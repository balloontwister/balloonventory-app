<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/account')->assertRedirect('/login');
    }

    public function test_user_without_business_is_sent_to_onboarding(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/account')
            ->assertRedirect(route('onboarding.welcome'));
    }

    public function test_user_with_business_can_view_account_hub(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        Membership::create([
            'user_id' => $user->id,
            'business_id' => $business->id,
            'role' => 'owner',
            'business_badge_color' => '#6366F1',
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/account');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Account/Index')
            ->where('auth.user.id', $user->id)
            ->where('business.id', $business->id)
        );
    }
}
