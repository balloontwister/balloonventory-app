<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Brand;
use App\Models\Color;
use App\Models\ColorFamily;
use App\Models\ColorTranslation;
use App\Models\Material;
use App\Models\MaterialTranslation;
use App\Models\Sku;
use App\Models\Texture;
use App\Models\TextureTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogIndexTranslationTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'email_verified_at' => now(),
        ]);
    }

    public function test_index_returns_english_names_for_en_locale(): void
    {
        $texture = Texture::create(['name' => 'Crystal', 'sort_order' => 1]);
        $material = Material::create(['name' => 'Latex', 'sort_order' => 1]);
        $colorFamily = ColorFamily::create(['name' => 'Reds', 'sort_order' => 1]);
        $color = Color::create(['name' => 'Red', 'color_family_id' => $colorFamily->id, 'color_hex' => '#ff0000', 'sort_order' => 1]);
        $brand = Brand::create(['name' => 'Qualatex', 'abbreviation' => 'Q', 'sort_order' => 1]);

        Sku::create(['name' => 'Test Balloon', 'brand_id' => $brand->id, 'texture_id' => $texture->id, 'material_id' => $material->id, 'color_id' => $color->id]);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.catalog.skus'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('skus.data', 1, fn ($sku) => $sku
                ->where('texture.name', 'Crystal')
                ->where('material.name', 'Latex')
                ->where('color.name', 'Red')
                ->etc()
            )
        );
    }

    public function test_index_returns_translated_names_for_es_locale(): void
    {
        $texture = Texture::create(['name' => 'Crystal', 'sort_order' => 1]);
        TextureTranslation::create(['texture_id' => $texture->id, 'locale' => 'es', 'name' => 'Cristal']);

        $material = Material::create(['name' => 'Latex', 'sort_order' => 1]);
        MaterialTranslation::create(['material_id' => $material->id, 'locale' => 'es', 'name' => 'Látex']);

        $colorFamily = ColorFamily::create(['name' => 'Reds', 'sort_order' => 1]);
        $color = Color::create(['name' => 'Red', 'color_family_id' => $colorFamily->id, 'color_hex' => '#ff0000', 'sort_order' => 1]);
        ColorTranslation::create(['color_id' => $color->id, 'locale' => 'es', 'name' => 'Rojo']);

        $brand = Brand::create(['name' => 'Qualatex', 'abbreviation' => 'Q', 'sort_order' => 1]);
        Sku::create(['name' => 'Test Balloon', 'brand_id' => $brand->id, 'texture_id' => $texture->id, 'material_id' => $material->id, 'color_id' => $color->id]);

        $this->app->setLocale('es');

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.catalog.skus'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('skus.data', 1, fn ($sku) => $sku
                ->where('texture.name', 'Cristal')
                ->where('material.name', 'Látex')
                ->where('color.name', 'Rojo')
                ->etc()
            )
        );
    }

    public function test_index_falls_back_to_english_when_no_translation_exists(): void
    {
        $texture = Texture::create(['name' => 'Pearl', 'sort_order' => 1]);
        $brand = Brand::create(['name' => 'Qualatex', 'abbreviation' => 'Q', 'sort_order' => 1]);
        Sku::create(['name' => 'Test Balloon', 'brand_id' => $brand->id, 'texture_id' => $texture->id]);

        $this->app->setLocale('es');

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.catalog.skus'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('skus.data', 1, fn ($sku) => $sku
                ->where('texture.name', 'Pearl')
                ->etc()
            )
        );
    }
}
