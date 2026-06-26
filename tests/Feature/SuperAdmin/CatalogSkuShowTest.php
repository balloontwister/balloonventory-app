<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Business;
use App\Models\Color;
use App\Models\ColorFamily;
use App\Models\ColorTranslation;
use App\Models\Distributor;
use App\Models\DistributorSkuUrl;
use App\Models\Material;
use App\Models\MaterialTranslation;
use App\Models\Shape;
use App\Models\ShapeTranslation;
use App\Models\Size;
use App\Models\Sku;
use App\Models\Texture;
use App\Models\TextureFamily;
use App\Models\TextureTranslation;
use App\Models\Theme;
use App\Models\ThemeTranslation;
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

        $this->brand = Brand::factory()->create(['name' => 'TufTex', 'abbreviation' => 'TT']);

        $this->sku = Sku::factory()->create([
            'name' => '11" Turquoise',
            'brand_id' => $this->brand->id,
            'warehouse_sku' => 'TT-11-TQ',
        ]);
    }

    public function test_show_page_renders_for_a_sku(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('admin.catalog.skus.show', $this->sku))
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
        $family = TextureFamily::factory()->create();
        $texture = Texture::factory()->create(['name' => 'Designer', 'texture_family_id' => $family->id]);
        $material = Material::factory()->create(['name' => 'Latex']);
        $colorFamily = ColorFamily::factory()->create();
        $color = Color::factory()->create(['name' => 'Turquoise', 'color_family_id' => $colorFamily->id, 'brand_id' => $this->brand->id, 'texture_id' => $texture->id]);

        $sku = Sku::factory()->create([
            'brand_id' => $this->brand->id,
            'material_id' => $material->id,
            'color_id' => $color->id,
        ]);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.catalog.skus.show', $sku))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('sku.brand.name', 'TufTex')
                    ->where('sku.color.texture.name', 'Designer')
                    ->where('sku.material.name', 'Latex')
                    ->where('sku.color.name', 'Turquoise'),
            );
    }

    public function test_show_page_returns_translated_names_for_es_locale(): void
    {
        $family = TextureFamily::factory()->create();
        $texture = Texture::factory()->create(['texture_family_id' => $family->id]);
        TextureTranslation::factory()->create(['texture_id' => $texture->id, 'locale' => 'es', 'name' => 'Cristal']);

        $material = Material::factory()->create();
        MaterialTranslation::factory()->create(['material_id' => $material->id, 'locale' => 'es', 'name' => 'Látex']);

        $shape = Shape::factory()->create();
        ShapeTranslation::factory()->create(['shape_id' => $shape->id, 'locale' => 'es', 'name' => 'Redondo']);

        $colorFamily = ColorFamily::factory()->create();
        $color = Color::factory()->create(['color_family_id' => $colorFamily->id, 'texture_id' => $texture->id]);
        ColorTranslation::factory()->create(['color_id' => $color->id, 'locale' => 'es', 'name' => 'Rojo']);

        $theme = Theme::factory()->create();
        ThemeTranslation::factory()->create(['theme_id' => $theme->id, 'locale' => 'es', 'name' => 'Cumpleaños']);

        $size = Size::factory()->create();
        $balloonSize = BalloonSize::factory()->create(['shape_id' => $shape->id, 'size_id' => $size->id]);

        $sku = Sku::factory()->create([
            'brand_id' => $this->brand->id,
            'material_id' => $material->id,
            'balloon_size_id' => $balloonSize->id,
            'color_id' => $color->id,
        ]);
        $sku->themes()->attach($theme->id);

        $this->app->setLocale('es');

        $this->actingAs($this->superAdmin)
            ->get(route('admin.catalog.skus.show', $sku))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('sku.color.texture.name', 'Cristal')
                    ->where('sku.material.name', 'Látex')
                    ->where('sku.balloon_size.shape.name', 'Redondo')
                    ->where('sku.color.name', 'Rojo')
                    ->where('sku.themes.0.name', 'Cumpleaños'),
            );
    }

    public function test_show_page_is_inaccessible_to_guests(): void
    {
        $this->get(route('admin.catalog.skus.show', $this->sku))
            ->assertRedirect(route('login'));
    }

    public function test_show_page_returns_403_for_business_owned_sku(): void
    {
        $business = Business::factory()->create();

        $sku = Sku::factory()->create([
            'brand_id' => $this->brand->id,
            'owned_by_business_id' => $business->id,
        ]);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.catalog.skus.show', $sku))
            ->assertForbidden();
    }

    public function test_show_page_forwards_return_query_for_back_link(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('admin.catalog.skus.show', $this->sku).'?return='.urlencode('?brand=abc&page=2'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('returnQuery', '?brand=abc&page=2'));
    }

    public function test_show_page_return_query_defaults_to_empty_string(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('admin.catalog.skus.show', $this->sku))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('returnQuery', ''));
    }

    public function test_edit_page_renders_with_form_data(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('admin.catalog.skus.edit', $this->sku))
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
            ->patch(route('admin.catalog.skus.update', $this->sku), [
                'name' => '11" Turquoise',
                'brand_id' => $this->brand->id,
                'theme_ids' => [],
                'print_color_ids' => [],
                'print_side_ids' => [],
                'is_printed' => false,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.catalog.skus.show', $this->sku));
    }

    public function test_store_redirects_to_show_page(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.catalog.skus.store'), [
                'name' => 'Brand New SKU',
                'brand_id' => $this->brand->id,
            ]);

        $sku = Sku::where('name', 'Brand New SKU')->firstOrFail();
        $response->assertRedirect(route('admin.catalog.skus.show', $sku));
    }

    public function test_show_page_includes_identical_skus_when_linked(): void
    {
        $size = Size::factory()->create();
        $balloonSize = BalloonSize::factory()->create(['size_id' => $size->id]);
        $color = Color::factory()->create(['brand_id' => $this->brand->id]);

        $skuA = Sku::factory()->create([
            'brand_id' => $this->brand->id,
            'balloon_size_id' => $balloonSize->id,
            'color_id' => $color->id,
            'default_count_per_bag' => 12,
        ]);

        $skuB = Sku::factory()->create([
            'brand_id' => $this->brand->id,
            'balloon_size_id' => $balloonSize->id,
            'color_id' => $color->id,
            'default_count_per_bag' => 50,
        ]);

        $skuA->linkIdentical($skuB);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.catalog.skus.show', $skuA))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->has('sku.identical_skus', 1)
                    ->where('sku.identical_skus.0.id', $skuB->id)
                    ->where('sku.identical_skus.0.default_count_per_bag', 50),
            );
    }

    public function test_show_page_identical_skus_empty_when_none_linked(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('admin.catalog.skus.show', $this->sku))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->has('sku.identical_skus', 0),
            );
    }

    public function test_skus_index_exposes_sku_ids_used_to_build_show_links(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('admin.catalog.skus'))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('SuperAdmin/Catalog/Index')
                    ->has(
                        'skus.data',
                        1,
                        fn ($sku) => $sku->where('id', $this->sku->id)->etc(),
                    ),
            );
    }

    public function test_show_page_includes_tracked_distributor_urls(): void
    {
        $larocks = Distributor::factory()->create(['name' => 'Larocks']);
        $bargain = Distributor::factory()->create(['name' => 'BargainBalloons']);

        DistributorSkuUrl::factory()->withPrice(4.50)->inStock()->create([
            'distributor_id' => $larocks->id,
            'sku_id' => $this->sku->id,
            'url' => 'https://larocks.test/p/1',
        ]);
        DistributorSkuUrl::factory()->outOfStock()->create([
            'distributor_id' => $bargain->id,
            'sku_id' => $this->sku->id,
        ]);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.catalog.skus.show', $this->sku))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->has('distributorUrls', 2)
                    // Sorted by distributor name: BargainBalloons before Larocks.
                    ->where('distributorUrls.0.distributor.name', 'BargainBalloons')
                    ->where('distributorUrls.0.in_stock', false)
                    ->where('distributorUrls.1.distributor.name', 'Larocks')
                    ->where('distributorUrls.1.url', 'https://larocks.test/p/1')
                    ->where('distributorUrls.1.in_stock', true),
            );
    }

    public function test_show_page_excludes_inactive_distributor_urls(): void
    {
        $inactive = Distributor::factory()->create(['name' => 'Gone', 'is_active' => false]);

        DistributorSkuUrl::factory()->create([
            'distributor_id' => $inactive->id,
            'sku_id' => $this->sku->id,
        ]);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.catalog.skus.show', $this->sku))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('distributorUrls', 0));
    }

    public function test_show_page_distributor_urls_empty_when_none(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('admin.catalog.skus.show', $this->sku))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('distributorUrls', []));
    }

    public function test_skus_index_counts_active_distributor_urls_per_sku(): void
    {
        $active = Distributor::factory()->create();
        $inactive = Distributor::factory()->create(['is_active' => false]);

        DistributorSkuUrl::factory()->create([
            'distributor_id' => $active->id,
            'sku_id' => $this->sku->id,
        ]);
        DistributorSkuUrl::factory()->create([
            'distributor_id' => $inactive->id,
            'sku_id' => $this->sku->id,
        ]);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.catalog.skus'))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page->has(
                    'skus.data',
                    1,
                    // Only the active distributor's link is counted.
                    fn ($sku) => $sku->where('distributor_urls_count', 1)->etc(),
                ),
            );
    }
}
