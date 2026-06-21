<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Color;
use App\Models\ColorFamily;
use App\Models\Material;
use App\Models\Shape;
use App\Models\Size;
use App\Models\Sku;
use App\Models\Texture;
use App\Models\TextureFamily;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogIndexFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdmin()->create([
            'email_verified_at' => now(),
        ]);

        $this->brand = Brand::factory()->create();
    }

    public function test_texture_family_filter_narrows_results_to_that_family(): void
    {
        $familyA = TextureFamily::factory()->create();
        $familyB = TextureFamily::factory()->create();
        $textureA = Texture::factory()->create(['texture_family_id' => $familyA->id]);
        $textureB = Texture::factory()->create(['texture_family_id' => $familyB->id]);

        $colorFamilyA = ColorFamily::factory()->create();
        $colorFamilyB = ColorFamily::factory()->create();
        $colorA = Color::factory()->create(['color_family_id' => $colorFamilyA->id, 'texture_id' => $textureA->id]);
        $colorB = Color::factory()->create(['color_family_id' => $colorFamilyB->id, 'texture_id' => $textureB->id]);

        Sku::factory()->create(['name' => 'In Family A', 'brand_id' => $this->brand->id, 'color_id' => $colorA->id]);
        Sku::factory()->create(['name' => 'In Family B', 'brand_id' => $this->brand->id, 'color_id' => $colorB->id]);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.catalog.skus', ['texture_family' => $familyA->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('skus.data', 1, fn ($sku) => $sku->where('name', 'In Family A')->etc())
            );
    }

    public function test_color_family_filter_narrows_results_to_that_family(): void
    {
        $familyA = ColorFamily::factory()->create();
        $familyB = ColorFamily::factory()->create();
        $colorA = Color::factory()->create(['color_family_id' => $familyA->id]);
        $colorB = Color::factory()->create(['color_family_id' => $familyB->id]);

        Sku::factory()->create(['name' => 'Red SKU', 'brand_id' => $this->brand->id, 'color_id' => $colorA->id]);
        Sku::factory()->create(['name' => 'Blue SKU', 'brand_id' => $this->brand->id, 'color_id' => $colorB->id]);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.catalog.skus', ['color_family' => $familyA->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('skus.data', 1, fn ($sku) => $sku->where('name', 'Red SKU')->etc())
            );
    }

    public function test_material_filter_narrows_results_to_that_material(): void
    {
        $latex = Material::factory()->create();
        $foil = Material::factory()->create();

        Sku::factory()->create(['name' => 'Latex SKU', 'brand_id' => $this->brand->id, 'material_id' => $latex->id]);
        Sku::factory()->create(['name' => 'Foil SKU', 'brand_id' => $this->brand->id, 'material_id' => $foil->id]);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.catalog.skus', ['material' => $latex->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('skus.data', 1, fn ($sku) => $sku->where('name', 'Latex SKU')->etc())
            );
    }

    public function test_printed_filter_shows_only_printed_skus(): void
    {
        Sku::factory()->create(['name' => 'Printed SKU', 'brand_id' => $this->brand->id, 'is_printed' => true]);
        Sku::factory()->create(['name' => 'Solid SKU', 'brand_id' => $this->brand->id, 'is_printed' => false]);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.catalog.skus', ['printed' => '1']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('skus.data', 1, fn ($sku) => $sku->where('name', 'Printed SKU')->etc())
            );
    }

    public function test_printed_filter_shows_only_solid_skus_when_zero(): void
    {
        Sku::factory()->create(['name' => 'Printed SKU', 'brand_id' => $this->brand->id, 'is_printed' => true]);
        Sku::factory()->create(['name' => 'Solid SKU', 'brand_id' => $this->brand->id, 'is_printed' => false]);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.catalog.skus', ['printed' => '0']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('skus.data', 1, fn ($sku) => $sku->where('name', 'Solid SKU')->etc())
            );
    }

    public function test_filters_combine_to_narrow_results(): void
    {
        $latex = Material::factory()->create();
        $foil = Material::factory()->create();

        Sku::factory()->create(['name' => 'Latex Printed', 'brand_id' => $this->brand->id, 'material_id' => $latex->id, 'is_printed' => true]);
        Sku::factory()->create(['name' => 'Latex Solid', 'brand_id' => $this->brand->id, 'material_id' => $latex->id, 'is_printed' => false]);
        Sku::factory()->create(['name' => 'Foil Printed', 'brand_id' => $this->brand->id, 'material_id' => $foil->id, 'is_printed' => true]);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.catalog.skus', ['material' => $latex->id, 'printed' => '1']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('skus.data', 1, fn ($sku) => $sku->where('name', 'Latex Printed')->etc())
            );
    }

    public function test_array_valued_filter_param_is_rejected_without_a_server_error(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.catalog.skus').'?brand[]=x');

        $response->assertStatus(302);
        $response->assertSessionHasErrors('brand');
    }

    public function test_shape_filter_narrows_results_to_that_shape(): void
    {
        $round = Shape::factory()->create();
        $heart = Shape::factory()->create();

        $roundSize = BalloonSize::factory()->create(['shape_id' => $round->id]);
        $heartSize = BalloonSize::factory()->create(['shape_id' => $heart->id]);

        Sku::factory()->create(['name' => 'Round SKU', 'brand_id' => $this->brand->id, 'balloon_size_id' => $roundSize->id]);
        Sku::factory()->create(['name' => 'Heart SKU', 'brand_id' => $this->brand->id, 'balloon_size_id' => $heartSize->id]);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.catalog.skus', ['shape' => $round->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('skus.data', 1, fn ($sku) => $sku->where('name', 'Round SKU')->etc())
            );
    }

    public function test_theme_filter_narrows_results_to_that_theme(): void
    {
        $birthday = Theme::factory()->create();
        $wedding = Theme::factory()->create();

        $birthdaySku = Sku::factory()->create(['name' => 'Birthday SKU', 'brand_id' => $this->brand->id]);
        $weddingSku = Sku::factory()->create(['name' => 'Wedding SKU', 'brand_id' => $this->brand->id]);
        // An untagged SKU must also be excluded by the theme filter.
        Sku::factory()->create(['name' => 'Untagged SKU', 'brand_id' => $this->brand->id]);

        $birthdaySku->themes()->attach($birthday->id);
        $weddingSku->themes()->attach($wedding->id);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.catalog.skus', ['theme' => $birthday->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('skus.data', 1, fn ($sku) => $sku->where('name', 'Birthday SKU')->etc())
            );
    }

    public function test_search_matches_a_partial_barcode(): void
    {
        // Admin catalog search now shares Sku::scopeMatchesSearch, so a UPC
        // fragment resolves the product just like the Scan / Inventory search.
        Sku::factory()->create(['name' => 'Fashion White R-5', 'brand_id' => $this->brand->id, 'upc' => '030625510028']);
        Sku::factory()->create(['name' => 'Some Other SKU', 'brand_id' => $this->brand->id, 'upc' => '012345678905']);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.catalog.skus', ['search' => '51002']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('skus.data', 1, fn ($sku) => $sku->where('name', 'Fashion White R-5')->etc())
            );
    }

    public function test_multiword_numeric_token_is_a_size_hint_not_a_barcode_fragment(): void
    {
        // "Green 360" should resolve to a green size-360 balloon, NOT every green
        // balloon whose UPC merely contains the digits "360".
        $green = Color::factory()->create(['name' => 'Green']);

        $bs360 = BalloonSize::factory()->create([
            'brand_id' => $this->brand->id,
            'size_id' => Size::factory()->create(['name' => '360'])->id,
            'shape_id' => Shape::factory()->create(['name' => 'Non-round'])->id,
        ]);
        $bs11 = BalloonSize::factory()->create([
            'brand_id' => $this->brand->id,
            'size_id' => Size::factory()->create(['name' => '11-inch'])->id,
            'shape_id' => Shape::factory()->create(['name' => 'Round'])->id,
        ]);

        Sku::factory()->create([
            'name' => 'Green Modeling',
            'brand_id' => $this->brand->id,
            'color_id' => $green->id,
            'balloon_size_id' => $bs360->id,
        ]);
        // Decoy: green, but round 11-inch whose UPC happens to contain "360".
        Sku::factory()->create([
            'name' => 'Green Round',
            'brand_id' => $this->brand->id,
            'color_id' => $green->id,
            'balloon_size_id' => $bs11->id,
            'upc' => '012360678905',
        ]);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.catalog.skus', ['search' => 'Green 360']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('skus.data', 1, fn ($sku) => $sku->where('name', 'Green Modeling')->etc())
            );
    }

    public function test_search_matches_brand_and_color_via_shared_scope(): void
    {
        // A multi-word query whose words live in different columns (brand + color)
        // resolves — behavior the old single-LIKE admin search couldn't do.
        $brand = Brand::factory()->create(['name' => 'Sempertex']);
        $color = Color::factory()->create(['name' => 'Fashion White']);

        Sku::factory()->create(['name' => 'R-5', 'brand_id' => $brand->id, 'color_id' => $color->id]);
        Sku::factory()->create(['name' => 'Unrelated', 'brand_id' => $this->brand->id]);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.catalog.skus', ['search' => 'Sempertex White']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('skus.data', 1, fn ($sku) => $sku->where('name', 'R-5')->etc())
            );
    }

    public function test_no_filters_returns_all_shared_skus(): void
    {
        Sku::factory()->create(['brand_id' => $this->brand->id]);
        Sku::factory()->create(['brand_id' => $this->brand->id]);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.catalog.skus'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('skus.data', 2));
    }
}
