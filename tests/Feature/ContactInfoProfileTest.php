<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactInfoProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_fields_are_saved_on_profile_update(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '555-1234',
            'address_line1' => '123 Main St',
            'address_line2' => 'Suite 4',
            'city' => 'Springfield',
            'state_region' => 'IL',
            'postal_code' => '62701',
            'country' => 'US',
            'website_url' => 'https://example.com',
            'website_url_2' => 'https://example2.com',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('555-1234', $user->phone);
        $this->assertSame('123 Main St', $user->address_line1);
        $this->assertSame('Suite 4', $user->address_line2);
        $this->assertSame('Springfield', $user->city);
        $this->assertSame('IL', $user->state_region);
        $this->assertSame('62701', $user->postal_code);
        $this->assertSame('US', $user->country);
        $this->assertSame('https://example.com', $user->website_url);
        $this->assertSame('https://example2.com', $user->website_url_2);
    }

    public function test_website_url_is_normalized_with_https_scheme(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'website_url' => 'example.com',
            'website_url_2' => 'shop.example.org',
        ])->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame('https://example.com', $user->website_url);
        $this->assertSame('https://shop.example.org', $user->website_url_2);
    }

    public function test_invalid_url_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'website_url' => 'not a url at all !!',
        ])->assertSessionHasErrors('website_url');
    }

    public function test_invalid_country_code_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'country' => 'XX',
        ])->assertSessionHasErrors('country');
    }

    public function test_valid_country_code_is_accepted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'country' => 'CA',
        ])->assertSessionHasNoErrors();

        $this->assertSame('CA', $user->fresh()->country);
    }

    public function test_existing_name_and_email_behavior_is_unchanged(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->patch('/profile', [
            'name' => 'New Name',
            'email' => $user->email,
        ])->assertSessionHasNoErrors()->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('New Name', $user->name);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_contact_fields_are_nullable(): void
    {
        $user = User::factory()->create([
            'phone' => '555-0000',
            'city' => 'Old City',
        ]);

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '',
            'city' => '',
        ])->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertNull($user->phone);
        $this->assertNull($user->city);
    }
}
