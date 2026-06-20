<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Membership;
use App\Models\User;
use App\Support\BusinessContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ContactInfoAdminShowTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->superAdmin = User::factory()->superAdmin()->create(['email_verified_at' => now()]);

        $business = Business::factory()->create();
        Membership::create([
            'user_id' => $this->superAdmin->id,
            'business_id' => $business->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        BusinessContext::set($business->id);
    }

    protected function tearDown(): void
    {
        BusinessContext::clear();
        parent::tearDown();
    }

    public function test_admin_show_includes_user_contact_fields(): void
    {
        $user = User::factory()->create([
            'phone' => '555-9999',
            'address_line1' => '42 Elm St',
            'address_line2' => null,
            'city' => 'Shelbyville',
            'state_region' => 'MO',
            'postal_code' => '65101',
            'country' => 'US',
            'website_url' => 'https://user.example',
            'website_url_2' => null,
        ]);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.users.show', $user))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SuperAdmin/Users/Show')
                ->where('user.phone', '555-9999')
                ->where('user.address_line1', '42 Elm St')
                ->where('user.address_line2', null)
                ->where('user.city', 'Shelbyville')
                ->where('user.state_region', 'MO')
                ->where('user.postal_code', '65101')
                ->where('user.country', 'United States')
                ->where('user.website_url', 'https://user.example')
                ->where('user.website_url_2', null)
            );
    }

    public function test_admin_show_includes_business_contact_fields(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create([
            'phone' => '800-123-4567',
            'city' => 'Balloon City',
            'country' => 'MX',
            'contact_email' => 'biz@example.mx',
        ]);

        Membership::create([
            'user_id' => $user->id,
            'business_id' => $business->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.users.show', $user))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SuperAdmin/Users/Show')
                ->where('businesses.0.contact.phone', '800-123-4567')
                ->where('businesses.0.contact.city', 'Balloon City')
                ->where('businesses.0.contact.country', 'Mexico')
                ->where('businesses.0.contact.contact_email', 'biz@example.mx')
            );
    }

    public function test_country_code_is_resolved_to_display_name(): void
    {
        $user = User::factory()->create(['country' => 'GB']);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.users.show', $user))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('user.country', 'United Kingdom')
            );
    }
}
