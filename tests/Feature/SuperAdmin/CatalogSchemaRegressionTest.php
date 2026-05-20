<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\BrandGs1Prefix;
use App\Models\Color;
use App\Models\ColorFamily;
use App\Models\Material;
use App\Models\Shape;
use App\Models\Size;
use App\Models\Sku;
use App\Models\Texture;
use App\Models\TextureFamily;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CatalogSchemaRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdmin()->create([
            'email_verified_at' => now(),
        ]);
    }

    public function test_textures_table_has_texture_family_id_not_texture_family(): void
    {
        $this->assertTrue(Schema::hasColumn('textures', 'texture_family_id'));
        $this->assertFalse(Schema::hasColumn('textures', 'texture_family'));
    }

    public function test_brands_table_drops_brand_color_hex(): void
    {
        $this->assertFalse(Schema::hasColumn('brands', 'brand_color_hex'));
        $this->assertTrue(Schema::hasColumn('brands', 'primary_color_hex'));
        $this->assertTrue(Schema::hasColumn('brands', 'secondary_color_hex'));
    }

    public function test_catalog_index_loads_without_referencing_dropped_columns(): void
    {
        $family = TextureFamily::factory()->create(['name' => 'Crystal Family']);
        $texture = Texture::factory()->create(['texture_family_id' => $family->id]);
        Sku::factory()->create(['texture_id' => $texture->id]);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.catalog.skus'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('textureFamilies', 1, fn ($t) => $t
                ->where('id', $family->id)
                ->where('name', 'Crystal Family')
                ->etc()
            )
        );
    }

    public function test_catalog_create_form_groups_textures_by_family_via_relation(): void
    {
        $family = TextureFamily::factory()->create(['name' => 'Metallic Family']);
        Texture::factory()->create(['texture_family_id' => $family->id]);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.catalog.skus.create'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('textures', 1, fn ($t) => $t
                ->where('texture_family.name', 'Metallic Family')
                ->etc()
            )
        );
    }

    public function test_computed_name_is_populated_on_create_without_eager_loading(): void
    {
        $brand = Brand::factory()->create(['abbreviation' => 'Q']);
        $latex = Material::factory()->create();
        $size = Size::factory()->create();
        $shape = Shape::factory()->create(['name' => 'Round']);
        $balloonSize = BalloonSize::factory()->create([
            'brand_id' => $brand->id,
            'material_id' => $latex->id,
            'size_id' => $size->id,
            'shape_id' => $shape->id,
            'name' => '11-inch',
        ]);
        $color = Color::factory()->create(['name' => 'Fashion Red']);

        $sku = Sku::factory()->create([
            'brand_id' => $brand->id,
            'balloon_size_id' => $balloonSize->id,
            'shape_id' => $shape->id,
            'color_id' => $color->id,
            'default_count_per_bag' => 100,
        ]);

        $this->assertNotEmpty($sku->computed_name);
        $this->assertStringContainsString('11-inch', $sku->computed_name);
        $this->assertStringContainsString('Fashion Red', $sku->computed_name);
        $this->assertStringContainsString('Q', $sku->computed_name);
        $this->assertStringContainsString('Round', $sku->computed_name);
        $this->assertStringContainsString('100ct', $sku->computed_name);
    }

    public function test_upc_normalization_treats_na_and_empty_as_null(): void
    {
        $brand = Brand::factory()->create();

        $skuNa = Sku::factory()->create(['brand_id' => $brand->id, 'upc' => 'na']);
        $skuNA = Sku::factory()->create(['brand_id' => $brand->id, 'upc' => 'N/A']);
        $skuEmpty = Sku::factory()->create(['brand_id' => $brand->id, 'upc' => '']);
        $skuReal = Sku::factory()->create(['brand_id' => $brand->id, 'upc' => '030625530125']);

        $this->assertNull($skuNa->fresh()->upc);
        $this->assertNull($skuNA->fresh()->upc);
        $this->assertNull($skuEmpty->fresh()->upc);
        $this->assertSame('030625530125', $skuReal->fresh()->upc);
    }

    public function test_gs1_prefix_is_derived_from_upc_on_save(): void
    {
        $brand = Brand::factory()->create();
        BrandGs1Prefix::factory()->create(['brand_id' => $brand->id, 'prefix' => '719784']);

        $sku = Sku::factory()->create([
            'brand_id' => $brand->id,
            'upc' => '7197841234567',
        ]);

        $this->assertSame('719784', $sku->fresh()->gs1_prefix);

        $skuNoMatch = Sku::factory()->create([
            'brand_id' => $brand->id,
            'upc' => '9999999999999',
        ]);

        $this->assertNull($skuNoMatch->fresh()->gs1_prefix);
    }

    public function test_link_identical_is_symmetric_and_rejects_self(): void
    {
        $brand = Brand::factory()->create();
        $a = Sku::factory()->create(['brand_id' => $brand->id]);
        $b = Sku::factory()->create(['brand_id' => $brand->id]);

        $a->linkIdentical($b);

        $this->assertTrue($a->fresh()->identicalSkus->contains('id', $b->id));
        $this->assertTrue($b->fresh()->identicalSkus->contains('id', $a->id));

        $a->unlinkIdentical($b);

        $this->assertFalse($a->fresh()->identicalSkus->contains('id', $b->id));
        $this->assertFalse($b->fresh()->identicalSkus->contains('id', $a->id));

        $this->expectException(\InvalidArgumentException::class);
        $a->linkIdentical($a);
    }

    public function test_color_families_renames_color_hex_to_fallback(): void
    {
        $this->assertFalse(Schema::hasColumn('color_families', 'color_hex'));
        $this->assertTrue(Schema::hasColumn('color_families', 'fallback_color_hex'));
    }

    public function test_balloon_sizes_uses_file_path_naming(): void
    {
        $this->assertTrue(Schema::hasColumn('balloon_sizes', 'single_image_file_path'));
        $this->assertTrue(Schema::hasColumn('balloon_sizes', 'cluster_image_file_path'));
        $this->assertFalse(Schema::hasColumn('balloon_sizes', 'single_image_path'));
        $this->assertFalse(Schema::hasColumn('balloon_sizes', 'cluster_image_path'));
    }

    public function test_lookup_tables_no_longer_enforce_global_unique_name(): void
    {
        $latex = Material::factory()->create();
        $foil = Material::factory()->create();
        $brandA = Brand::factory()->create();
        $brandB = Brand::factory()->create();

        Texture::factory()->create(['name' => 'Standard', 'material_id' => $latex->id, 'brand_id' => $brandA->id]);
        Texture::factory()->create(['name' => 'Standard', 'material_id' => $latex->id, 'brand_id' => $brandB->id]);
        $this->assertSame(2, Texture::where('name', 'Standard')->count());

        Shape::factory()->create(['name' => 'Round', 'material_id' => $latex->id]);
        Shape::factory()->create(['name' => 'Round', 'material_id' => $foil->id]);
        $this->assertSame(2, Shape::where('name', 'Round')->count());

        ColorFamily::factory()->create(['name' => 'Reds', 'material_id' => $latex->id]);
        ColorFamily::factory()->create(['name' => 'Reds', 'material_id' => $foil->id]);
        $this->assertSame(2, ColorFamily::where('name', 'Reds')->count());
    }

    public function test_discontinued_at_is_auto_managed_on_is_active_toggle(): void
    {
        $brand = Brand::factory()->create();
        $sku = Sku::factory()->create(['brand_id' => $brand->id, 'is_active' => true]);
        $this->assertNull($sku->fresh()->discontinued_at);

        $sku->update(['is_active' => false]);
        $this->assertNotNull($sku->fresh()->discontinued_at);

        $previous = $sku->fresh()->discontinued_at;
        $sku->update(['is_active' => true]);
        $this->assertNull($sku->fresh()->discontinued_at);
        $this->assertNotNull($previous);
    }
}
