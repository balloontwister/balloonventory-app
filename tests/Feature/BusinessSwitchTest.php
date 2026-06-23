<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Membership;
use App\Models\User;
use App\Support\BusinessContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessSwitchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Business $businessA;

    private Business $businessB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        $this->user = User::factory()->create(['email_verified_at' => now()]);

        $this->businessA = Business::factory()->create();
        Membership::create([
            'user_id' => $this->user->id,
            'business_id' => $this->businessA->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $this->businessB = Business::factory()->create();
        Membership::create([
            'user_id' => $this->user->id,
            'business_id' => $this->businessB->id,
            'role' => 'staff',
            'joined_at' => now(),
        ]);

        BusinessContext::set($this->businessA->id);
    }

    protected function tearDown(): void
    {
        BusinessContext::clear();
        parent::tearDown();
    }

    public function test_user_can_switch_to_another_business(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('business.switch', ['business' => $this->businessB->id]));

        $response->assertRedirect();
        $response->assertSessionHas('current_business_id', $this->businessB->id);
    }

    public function test_user_cannot_switch_to_business_they_dont_belong_to(): void
    {
        $outsideBusiness = Business::factory()->create();

        $response = $this->actingAs($this->user)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('business.switch', ['business' => $outsideBusiness->id]));

        $response->assertForbidden();
    }

    public function test_switch_back_to_original_business_works(): void
    {
        BusinessContext::set($this->businessB->id);

        $response = $this->actingAs($this->user)
            ->withSession(['current_business_id' => $this->businessB->id])
            ->post(route('business.switch', ['business' => $this->businessA->id]));

        $response->assertRedirect();
        $response->assertSessionHas('current_business_id', $this->businessA->id);
    }

    public function test_user_cannot_switch_to_none_role_business(): void
    {
        $businessC = Business::factory()->create();
        Membership::create([
            'user_id' => $this->user->id,
            'business_id' => $businessC->id,
            'role' => 'none',
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('business.switch', ['business' => $businessC->id]));

        $response->assertForbidden();
    }

    public function test_middleware_skips_none_role_membership_when_session_points_to_it(): void
    {
        $businessC = Business::factory()->create();
        Membership::create([
            'user_id' => $this->user->id,
            'business_id' => $businessC->id,
            'role' => 'none',
            'joined_at' => now(),
        ]);

        // Session points to the none-role business — middleware should redirect context to an accessible one.
        $response = $this->actingAs($this->user)
            ->withSession(['current_business_id' => $businessC->id])
            ->get(route('dashboard'));

        $response->assertOk();
        $this->assertNotEquals($businessC->id, BusinessContext::currentId());
    }
}
