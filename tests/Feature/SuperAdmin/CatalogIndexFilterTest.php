<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Brand;
use App\Models\Color;
use App\Models\ColorFamily;
use App\Models\Material;
use App\Models\Sku;
use App\Models\Texture;
use App\Models\TextureFamily;
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
            ->get(route('super-admin.catalog.skus', ['texture_family' => $familyA->id]))
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
            ->get(route('super-admin.catalog.skus', ['color_family' => $familyA->id]))
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
            ->get(route('super-admin.catalog.skus', ['material' => $latex->id]))
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
            ->get(route('super-admin.catalog.skus', ['printed' => '1']))
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
            ->get(route('super-admin.catalog.skus', ['printed' => '0']))
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
            ->get(route('super-admin.catalog.skus', ['material' => $latex->id, 'printed' => '1']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('skus.data', 1, fn ($sku) => $sku->where('name', 'Latex Printed')->etc())
            );
    }

    public function test_array_valued_filter_param_is_rejected_without_a_server_error(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.catalog.skus').'?brand[]=x');

        $response->assertStatus(302);
        $response->assertSessionHasErrors('brand');
    }

    public function test_no_filters_returns_all_shared_skus(): void
    {
        Sku::factory()->create(['brand_id' => $this->brand->id]);
        Sku::factory()->create(['brand_id' => $this->brand->id]);

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.catalog.skus'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('skus.data', 2));
    }
}
