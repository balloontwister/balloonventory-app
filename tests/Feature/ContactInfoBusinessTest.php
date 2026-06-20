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

class ContactInfoBusinessTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $staff;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->owner = User::factory()->create(['email_verified_at' => now()]);
        $this->business = Business::factory()->create();

        Membership::create([
            'user_id' => $this->owner->id,
            'business_id' => $this->business->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $this->staff = User::factory()->create(['email_verified_at' => now()]);
        Membership::create([
            'user_id' => $this->staff->id,
            'business_id' => $this->business->id,
            'role' => 'staff',
            'joined_at' => now(),
        ]);

        BusinessContext::set($this->business->id);
    }

    protected function tearDown(): void
    {
        BusinessContext::clear();
        parent::tearDown();
    }

    public function test_owner_can_save_business_contact_fields(): void
    {
        $response = $this->actingAs($this->owner)->patch(route('settings.businesses.update'), [
            'name' => $this->business->name,
            'phone' => '800-555-0100',
            'address_line1' => '1 Balloon Way',
            'address_line2' => 'Unit B',
            'city' => 'Festivalton',
            'state_region' => 'TX',
            'postal_code' => '78701',
            'country' => 'US',
            'website_url' => 'https://balloonshop.example',
            'website_url_2' => 'https://events.example',
            'contact_email' => 'hello@balloonshop.example',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $this->business->refresh();

        $this->assertSame('800-555-0100', $this->business->phone);
        $this->assertSame('1 Balloon Way', $this->business->address_line1);
        $this->assertSame('Unit B', $this->business->address_line2);
        $this->assertSame('Festivalton', $this->business->city);
        $this->assertSame('TX', $this->business->state_region);
        $this->assertSame('78701', $this->business->postal_code);
        $this->assertSame('US', $this->business->country);
        $this->assertSame('https://balloonshop.example', $this->business->website_url);
        $this->assertSame('https://events.example', $this->business->website_url_2);
        $this->assertSame('hello@balloonshop.example', $this->business->contact_email);
    }

    public function test_staff_cannot_save_business_contact_fields(): void
    {
        $this->actingAs($this->staff)
            ->patch(route('settings.businesses.update'), [
                'name' => $this->business->name,
                'city' => 'Sneakyville',
            ])
            ->assertForbidden();
    }

    public function test_business_contact_email_validation(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('settings.businesses.update'), [
                'name' => $this->business->name,
                'contact_email' => 'not-an-email',
            ])
            ->assertSessionHasErrors('contact_email');
    }

    public function test_business_country_code_is_validated(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('settings.businesses.update'), [
                'name' => $this->business->name,
                'country' => 'ZZ',
            ])
            ->assertSessionHasErrors('country');
    }

    public function test_business_website_url_is_normalized(): void
    {
        $this->actingAs($this->owner)->patch(route('settings.businesses.update'), [
            'name' => $this->business->name,
            'website_url' => 'myshop.com',
        ])->assertSessionHasNoErrors();

        $this->assertSame('https://myshop.com', $this->business->fresh()->website_url);
    }
}
