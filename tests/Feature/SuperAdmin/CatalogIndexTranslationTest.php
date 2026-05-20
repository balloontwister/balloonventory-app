<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Brand;
use App\Models\Color;
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

        $this->superAdmin = User::factory()->superAdmin()->create([
            'email_verified_at' => now(),
        ]);
    }

    public function test_index_returns_english_names_for_en_locale(): void
    {
        $texture = Texture::factory()->create(['name' => 'Crystal']);
        $material = Material::factory()->create(['name' => 'Latex']);
        $color = Color::factory()->create(['name' => 'Red', 'texture_id' => $texture->id]);
        $brand = Brand::factory()->create();

        Sku::factory()->create([
            'brand_id' => $brand->id,
            'material_id' => $material->id,
            'color_id' => $color->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.catalog.skus'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('skus.data', 1, fn ($sku) => $sku
                ->where('color.texture.name', 'Crystal')
                ->where('material.name', 'Latex')
                ->where('color.name', 'Red')
                ->etc()
            )
        );
    }

    public function test_index_returns_translated_names_for_es_locale(): void
    {
        $texture = Texture::factory()->create();
        TextureTranslation::factory()->create(['texture_id' => $texture->id, 'locale' => 'es', 'name' => 'Cristal']);

        $material = Material::factory()->create();
        MaterialTranslation::factory()->create(['material_id' => $material->id, 'locale' => 'es', 'name' => 'Látex']);

        $color = Color::factory()->create(['texture_id' => $texture->id]);
        ColorTranslation::factory()->create(['color_id' => $color->id, 'locale' => 'es', 'name' => 'Rojo']);

        $brand = Brand::factory()->create();
        Sku::factory()->create([
            'brand_id' => $brand->id,
            'material_id' => $material->id,
            'color_id' => $color->id,
        ]);

        $this->app->setLocale('es');

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.catalog.skus'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('skus.data', 1, fn ($sku) => $sku
                ->where('color.texture.name', 'Cristal')
                ->where('material.name', 'Látex')
                ->where('color.name', 'Rojo')
                ->etc()
            )
        );
    }

    public function test_index_falls_back_to_english_when_no_translation_exists(): void
    {
        $texture = Texture::factory()->create(['name' => 'Pearl']);
        $color = Color::factory()->create(['texture_id' => $texture->id]);
        $brand = Brand::factory()->create();
        Sku::factory()->create(['brand_id' => $brand->id, 'color_id' => $color->id]);

        $this->app->setLocale('es');

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.catalog.skus'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('skus.data', 1, fn ($sku) => $sku
                ->where('color.texture.name', 'Pearl')
                ->etc()
            )
        );
    }
}
