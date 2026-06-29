<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Business;
use App\Models\Membership;
use App\Models\User;
use App\Support\AdminBusinessView;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AdminViewAsBusinessTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $siteAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class); // owner role → business permissions

        $this->superAdmin = User::factory()->superAdmin()->create(['email_verified_at' => now()]);
        $this->siteAdmin = User::factory()->siteAdmin()->create(['email_verified_at' => now()]);
    }

    public function test_super_admin_can_start_view_as(): void
    {
        $business = Business::factory()->create();

        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.businesses.view-as', $business->id));

        $response->assertRedirect(route('dashboard'));
        $this->assertSame($business->id, session(AdminBusinessView::SESSION_KEY));
    }

    public function test_stop_view_as_clears_session_and_returns_to_detail(): void
    {
        $business = Business::factory()->create();

        $response = $this->actingAs($this->superAdmin)
            ->withSession([AdminBusinessView::SESSION_KEY => $business->id])
            ->post(route('admin.businesses.stop-view'));

        $response->assertRedirect(route('admin.businesses.show', $business->id));
        $this->assertNull(session(AdminBusinessView::SESSION_KEY));
    }

    public function test_view_as_grants_owner_abilities_scoped_to_the_viewed_business(): void
    {
        $viewed = Business::factory()->create();
        $other = Business::factory()->create();

        $this->actingAs($this->superAdmin);
        session([AdminBusinessView::SESSION_KEY => $viewed->id]);

        // Full owner ability inside the viewed business...
        $this->assertTrue(Gate::forUser($this->superAdmin)->allows('inventory.view_counts', $viewed));
        $this->assertTrue(Gate::forUser($this->superAdmin)->allows('business.edit_settings', $viewed));
        // ...but nothing leaks to any other business.
        $this->assertFalse(Gate::forUser($this->superAdmin)->allows('inventory.view_counts', $other));
        $this->assertFalse(Gate::forUser($this->superAdmin)->allows('business.edit_settings', $other));
    }

    public function test_without_view_as_a_super_admin_has_no_business_abilities(): void
    {
        $business = Business::factory()->create();

        $this->assertFalse(Gate::forUser($this->superAdmin)->allows('inventory.view_counts', $business));
    }

    public function test_view_as_works_for_an_ownerless_business(): void
    {
        // A business with no members at all — the case impersonation can't handle.
        $business = Business::factory()->create();
        $this->assertSame(0, Membership::where('business_id', $business->id)->count());

        $this->actingAs($this->superAdmin)
            ->post(route('admin.businesses.view-as', $business->id))
            ->assertRedirect(route('dashboard'));

        // The admin can now reach a tenant route that gate-authorizes an ability.
        $this->actingAs($this->superAdmin)
            ->withSession([AdminBusinessView::SESSION_KEY => $business->id])
            ->get(route('inventory.index'))
            ->assertStatus(200);
    }

    public function test_site_admin_cannot_view_as(): void
    {
        $business = Business::factory()->create();

        $this->actingAs($this->siteAdmin)
            ->post(route('admin.businesses.view-as', $business->id))
            ->assertStatus(403);
    }

    public function test_regular_user_cannot_view_as(): void
    {
        $business = Business::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.businesses.view-as', $business->id))
            ->assertStatus(403);
    }

    public function test_cannot_view_as_while_impersonating(): void
    {
        $business = Business::factory()->create();

        $this->actingAs($this->superAdmin)
            ->withSession(['impersonator_id' => $this->superAdmin->id])
            ->post(route('admin.businesses.view-as', $business->id))
            ->assertStatus(422);
    }
}
