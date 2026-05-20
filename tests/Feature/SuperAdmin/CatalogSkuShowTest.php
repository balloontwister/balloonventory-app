<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Brand;
use App\Models\Business;
use App\Models\Color;
use App\Models\ColorFamily;
use App\Models\Material;
use App\Models\Sku;
use App\Models\Texture;
use App\Models\TextureFamily;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSkuShowTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Brand $brand;

    private Sku $sku;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdmin()->create([
            'email_verified_at' => now(),
        ]);

        $this->brand = Brand::create(['name' => 'TufTex', 'abbreviation' => 'TT', 'sort_order' => 1]);

        $this->sku = Sku::create([
            'name' => '11" Turquoise',
            'brand_id' => $this->brand->id,
            'warehouse_sku' => 'TT-11-TQ',
        ]);
    }

    public function test_show_page_renders_for_a_sku(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.catalog.skus.show', $this->sku))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('SuperAdmin/Catalog/SkuShow')
                    ->has('sku')
                    ->where('sku.name', '11" Turquoise')
                    ->where('sku.warehouse_sku', 'TT-11-TQ'),
            );
    }

    public function test_show_page_includes_related_data(): void
    {
        $family = TextureFamily::create(['name' => 'Standard', 'sort_order' => 1]);
        $texture = Texture::create(['name' => 'Designer', 'sort_order' => 1, 'texture_family_id' => $family->id]);
        $material = Material::create(['name' => 'Latex', 'sort_order' => 1]);
        $colorFamily = ColorFamily::create(['name' => 'Blues', 'sort_order' => 1]);
        $color = Color::create(['name' => 'Turquoise', 'color_family_id' => $colorFamily->id, 'brand_id' => $this->brand->id]);

        $sku = Sku::create([
            'name' => '11" Turquoise',
            'brand_id' => $this->brand->id,
            'texture_id' => $texture->id,
            'material_id' => $material->id,
            'color_id' => $color->id,
        ]);

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.catalog.skus.show', $sku))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('sku.brand.name', 'TufTex')
                    ->where('sku.texture.name', 'Designer')
                    ->where('sku.material.name', 'Latex')
                    ->where('sku.color.name', 'Turquoise'),
            );
    }

    public function test_show_page_is_inaccessible_to_guests(): void
    {
        $this->get(route('super-admin.catalog.skus.show', $this->sku))
            ->assertRedirect(route('login'));
    }

    public function test_show_page_returns_403_for_business_owned_sku(): void
    {
        $business = Business::factory()->create();

        $sku = Sku::create([
            'name' => 'Custom SKU',
            'brand_id' => $this->brand->id,
            'owned_by_business_id' => $business->id,
        ]);

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.catalog.skus.show', $sku))
            ->assertForbidden();
    }

    public function test_edit_page_renders_with_form_data(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.catalog.skus.edit', $this->sku))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('SuperAdmin/Catalog/SkuForm')
                    ->has('sku')
                    ->where('sku.name', '11" Turquoise'),
            );
    }

    public function test_update_redirects_to_show_page(): void
    {
        $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.catalog.skus.update', $this->sku), [
                'name' => '11" Turquoise',
                'brand_id' => $this->brand->id,
                'theme_ids' => [],
                'print_color_ids' => [],
                'print_side_ids' => [],
                'is_printed' => false,
                'is_active' => true,
            ])
            ->assertRedirect(route('super-admin.catalog.skus.show', $this->sku));
    }

    public function test_skus_index_links_to_show_route(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.catalog.skus'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('SuperAdmin/Catalog/Index'));

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.catalog.skus.show', $this->sku))
            ->assertOk();
    }
}
