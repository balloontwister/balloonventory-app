<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Brand;
use App\Models\BrandGs1Prefix;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogBrandDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->superAdmin = User::factory()->superAdmin()->create([
            'email_verified_at' => now(),
        ]);
    }

    public function test_show_page_renders_brand_with_extended_fields(): void
    {
        $brand = Brand::factory()->create([
            'name' => 'Qualatex',
            'abbreviation' => 'QTX',
            'description' => 'A long-standing balloon manufacturer.',
            'url_1' => 'https://qualatex.com',
            'primary_color_hex' => '#ff0000',
            'secondary_color_hex' => '#00ff00',
            'is_active' => true,
            'sort_order' => 2,
        ]);
        BrandGs1Prefix::create(['brand_id' => $brand->id, 'prefix' => '071444']);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.catalog.brands.show', $brand));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('SuperAdmin/Catalog/BrandShow')
            ->where('brand.id', $brand->id)
            ->where('brand.description', 'A long-standing balloon manufacturer.')
            ->where('brand.url_1', 'https://qualatex.com')
            ->where('brand.secondary_color_hex', '#00ff00')
            ->where('brand.is_active', true)
            ->has('brand.gs1_prefixes', 1, fn ($p) => $p
                ->where('prefix', '071444')
                ->etc()
            )
        );
    }

    public function test_edit_page_renders_form_data(): void
    {
        $brand = Brand::factory()->create([
            'name' => 'Sempertex',
            'abbreviation' => 'SMP',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.catalog.brands.edit', $brand));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('SuperAdmin/Catalog/BrandEdit')
            ->where('brand.id', $brand->id)
        );
    }

    public function test_update_persists_extended_fields(): void
    {
        $brand = Brand::factory()->create([
            'name' => 'TufTex',
            'abbreviation' => 'TUF',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.catalog.brands.update', $brand->id), [
                'name' => 'TufTex',
                'abbreviation' => 'TUF',
                'description' => 'Latex specialist.',
                'url_1' => 'https://tuftex.com',
                'url_2' => 'https://example.com/catalog.pdf',
                'primary_color_hex' => '#112233',
                'secondary_color_hex' => '#445566',
                'is_active' => false,
                'sort_order' => 5,
            ]);

        $response->assertRedirect(route('super-admin.catalog.brands'));

        $fresh = $brand->fresh();
        $this->assertSame('Latex specialist.', $fresh->description);
        $this->assertSame('https://tuftex.com', $fresh->url_1);
        $this->assertSame('https://example.com/catalog.pdf', $fresh->url_2);
        $this->assertSame('#112233', $fresh->primary_color_hex);
        $this->assertSame('#445566', $fresh->secondary_color_hex);
        $this->assertFalse($fresh->is_active);
        $this->assertSame(5, $fresh->sort_order);
    }

    public function test_update_redirects_to_show_when_return_to_show_flag_set(): void
    {
        $brand = Brand::factory()->create([
            'name' => 'Betallic',
            'abbreviation' => 'BTL',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.catalog.brands.update', $brand->id), [
                'name' => 'Betallic',
                'abbreviation' => 'BTL',
                'sort_order' => 1,
                'return_to_show' => true,
            ]);

        $response->assertRedirect(route('super-admin.catalog.brands.show', $brand));
    }

    public function test_update_rejects_invalid_url(): void
    {
        $brand = Brand::factory()->create([
            'name' => 'BrandX',
            'abbreviation' => 'BXX',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.catalog.brands.update', $brand->id), [
                'name' => 'BrandX',
                'abbreviation' => 'BXX',
                'url_1' => 'not-a-url',
                'sort_order' => 1,
            ]);

        $response->assertSessionHasErrors('url_1');
    }

    public function test_gs1_prefix_can_be_added(): void
    {
        $brand = Brand::factory()->create();

        $response = $this->actingAs($this->superAdmin)
            ->post(route('super-admin.catalog.brands.gs1-prefixes.store', $brand), [
                'prefix' => '071444',
            ]);

        $response->assertRedirect(route('super-admin.catalog.brands.show', $brand));
        $this->assertDatabaseHas('brand_gs1_prefixes', [
            'brand_id' => $brand->id,
            'prefix' => '071444',
        ]);
    }

    public function test_gs1_prefix_must_be_numeric(): void
    {
        $brand = Brand::factory()->create();

        $response = $this->actingAs($this->superAdmin)
            ->post(route('super-admin.catalog.brands.gs1-prefixes.store', $brand), [
                'prefix' => 'ABC123',
            ]);

        $response->assertSessionHasErrors('prefix');
    }

    public function test_duplicate_gs1_prefix_for_same_brand_rejected(): void
    {
        $brand = Brand::factory()->create();
        BrandGs1Prefix::create(['brand_id' => $brand->id, 'prefix' => '071444']);

        $response = $this->actingAs($this->superAdmin)
            ->post(route('super-admin.catalog.brands.gs1-prefixes.store', $brand), [
                'prefix' => '071444',
            ]);

        $response->assertSessionHasErrors('prefix');
    }

    public function test_gs1_prefix_can_be_deleted(): void
    {
        $brand = Brand::factory()->create();
        $prefix = BrandGs1Prefix::create(['brand_id' => $brand->id, 'prefix' => '071444']);

        $response = $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.catalog.brands.gs1-prefixes.destroy', [$brand, $prefix]));

        $response->assertRedirect(route('super-admin.catalog.brands.show', $brand));
        $this->assertDatabaseMissing('brand_gs1_prefixes', ['id' => $prefix->id]);
    }

    public function test_cannot_delete_prefix_belonging_to_different_brand(): void
    {
        $brandA = Brand::factory()->create();
        $brandB = Brand::factory()->create();
        $prefix = BrandGs1Prefix::create(['brand_id' => $brandA->id, 'prefix' => '071444']);

        $response = $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.catalog.brands.gs1-prefixes.destroy', [$brandB, $prefix]));

        $response->assertStatus(404);
        $this->assertDatabaseHas('brand_gs1_prefixes', ['id' => $prefix->id]);
    }
}
