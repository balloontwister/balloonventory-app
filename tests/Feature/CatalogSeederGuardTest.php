<?php

namespace Tests\Feature;

use App\Models\ColorFamily;
use App\Models\Material;
use App\Models\Texture;
use App\Models\TextureTranslation;
use Database\Seeders\CatalogTranslationSeeder;
use Database\Seeders\ColorFamilySeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\TextureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Catalog reference seeders only plant starter data on a fresh install. Once a
 * table holds data, the seeder must skip — production catalog data is curated
 * by hand and must never be resurrected, duplicated, or overwritten.
 */
class CatalogSeederGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_texture_seeder_populates_an_empty_table(): void
    {
        $this->assertSame(0, Texture::count());

        $this->seed(TextureSeeder::class);

        $this->assertGreaterThan(0, Texture::count());
    }

    public function test_texture_seeder_skips_when_textures_already_exist(): void
    {
        Texture::create(['name' => 'My Custom Texture', 'sort_order' => 1]);

        $this->seed(TextureSeeder::class);

        $this->assertSame(1, Texture::count());
    }

    public function test_texture_seeder_does_not_resurrect_a_soft_deleted_texture(): void
    {
        $texture = Texture::create(['name' => 'Crystal', 'sort_order' => 1]);
        $texture->delete();

        $this->seed(TextureSeeder::class);

        $this->assertSame(0, Texture::count(), 'No active textures should exist.');
        $this->assertSame(
            1,
            Texture::withTrashed()->count(),
            'A soft-deleted texture must not be resurrected or duplicated by the seeder.',
        );
    }

    public function test_material_seeder_skips_when_materials_already_exist(): void
    {
        Material::create(['name' => 'My Custom Material', 'sort_order' => 1]);

        $this->seed(MaterialSeeder::class);

        $this->assertSame(1, Material::count());
    }

    public function test_color_family_seeder_does_not_overwrite_edited_rows(): void
    {
        ColorFamily::create([
            'name' => 'Reds',
            'sort_order' => 1,
            'fallback_color_hex' => '#123456',
        ]);

        $this->seed(ColorFamilySeeder::class);

        $this->assertSame(1, ColorFamily::count());
        $this->assertSame('#123456', ColorFamily::where('name', 'Reds')->value('fallback_color_hex'));
    }

    public function test_catalog_translation_seeder_does_not_overwrite_edited_translations(): void
    {
        $texture = Texture::create(['name' => 'Crystal', 'sort_order' => 1]);
        TextureTranslation::create([
            'texture_id' => $texture->id,
            'locale' => 'es',
            'name' => 'Mi Cristal Personalizado',
        ]);

        $this->seed(CatalogTranslationSeeder::class);

        $this->assertSame(
            'Mi Cristal Personalizado',
            TextureTranslation::where('texture_id', $texture->id)->where('locale', 'es')->value('name'),
        );
    }
}
