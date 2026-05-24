<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Brand;
use App\Models\Color;
use App\Models\Texture;
use Database\Seeders\BrandSeeder;
use Database\Seeders\ColorFamilySeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\SempertexColorSeeder;
use Database\Seeders\SempertexTextureSeeder;
use Database\Seeders\TextureFamilySeeder;
use Database\Seeders\TextureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SempertexColorSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The seeder downloads single-balloon images from cdn.shopify.com; fake
        // both the HTTP layer and the public disk so the import path runs
        // without real network or filesystem writes.
        Storage::fake('public');
        Http::fake(['*' => Http::response('fake-jpg-bytes', 200, ['Content-Type' => 'image/jpeg'])]);

        $this->seed(BrandSeeder::class);
        $this->seed(MaterialSeeder::class);
        $this->seed(TextureFamilySeeder::class);
        $this->seed(TextureSeeder::class);
        $this->seed(ColorFamilySeeder::class);
        $this->seed(SempertexTextureSeeder::class);
        $this->seed(SempertexColorSeeder::class);
    }

    public function test_seeder_inserts_all_sempertex_colors(): void
    {
        $sempertex = Brand::where('name', 'Sempertex')->firstOrFail();

        $this->assertSame(126, Color::where('brand_id', $sempertex->id)->count());
    }

    public function test_texture_seeder_adds_sempertex_textures(): void
    {
        $sempertex = Brand::where('name', 'Sempertex')->firstOrFail();

        $expected = [
            'Fashion (S)',
            'Deluxe (S)',
            'Crystal (S)',
            'Neon (S)',
            'Pastel Matte (S)',
            'Pastel Dusk (S)',
            'Pearl (S)',
            'Reflex (S)',
            'Silk (S)',
            'Satin (S)',
            'Metallic (S)',
        ];

        foreach ($expected as $name) {
            $this->assertTrue(
                Texture::where('name', $name)->where('brand_id', $sempertex->id)->exists(),
                "Texture '{$name}' should exist with Sempertex brand_id",
            );
        }
    }

    public function test_colors_use_brand_specific_sempertex_textures(): void
    {
        $sempertex = Brand::where('name', 'Sempertex')->firstOrFail();

        $cases = [
            ['name' => 'Fashion Red',          'texture' => 'Fashion (S)'],
            ['name' => 'Deluxe Rosewood',      'texture' => 'Deluxe (S)'],
            ['name' => 'Crystal Clear',        'texture' => 'Crystal (S)'],
            ['name' => 'Neon Magenta',         'texture' => 'Neon (S)'],
            ['name' => 'Pastel Matte Pink',    'texture' => 'Pastel Matte (S)'],
            ['name' => 'Pastel Dusk Cream',    'texture' => 'Pastel Dusk (S)'],
            ['name' => 'Pearl White',          'texture' => 'Pearl (S)'],
            ['name' => 'Reflex Gold',          'texture' => 'Reflex (S)'],
            ['name' => 'Silk Cream Pearl',     'texture' => 'Silk (S)'],
            ['name' => 'Metallic Rose Gold',   'texture' => 'Metallic (S)'],
        ];

        foreach ($cases as $case) {
            $texture = Texture::where('name', $case['texture'])->where('brand_id', $sempertex->id)->firstOrFail();
            $color = Color::where('name', $case['name'])->where('brand_id', $sempertex->id)->first();

            $this->assertNotNull($color, "Color '{$case['name']}' not found");
            $this->assertSame($texture->id, $color->texture_id, "'{$case['name']}' should use {$case['texture']}");
        }
    }

    public function test_pdf_pantone_values_are_preserved(): void
    {
        $sempertex = Brand::where('name', 'Sempertex')->firstOrFail();

        $cases = [
            ['name' => 'Fashion Red',           'pms' => '185'],
            ['name' => 'Deluxe Imperial Red',   'pms' => '200'],
            ['name' => 'Reflex Gold',           'pms' => '10344'],
            ['name' => 'Silk Light Amethyst',   'pms' => '10220'],
            ['name' => 'Pastel Matte Yellow',   'pms' => 'YC - 25%'],
            ['name' => 'Fashion Royal Blue',    'pms' => 'Reflex Blue'],
        ];

        foreach ($cases as $case) {
            $color = Color::where('name', $case['name'])->where('brand_id', $sempertex->id)->first();
            $this->assertNotNull($color, "Color '{$case['name']}' not found");
            $this->assertSame($case['pms'], $color->pms_value, "PMS mismatch for '{$case['name']}'");
        }
    }

    public function test_crystal_clear_has_no_hex(): void
    {
        $sempertex = Brand::where('name', 'Sempertex')->firstOrFail();

        $clear = Color::where('name', 'Crystal Clear')->where('brand_id', $sempertex->id)->firstOrFail();

        $this->assertNull($clear->color_hex);
    }

    public function test_cluster_image_paths_are_set(): void
    {
        $sempertex = Brand::where('name', 'Sempertex')->firstOrFail();

        $red = Color::where('name', 'Fashion Red')->where('brand_id', $sempertex->id)->firstOrFail();

        $this->assertSame('color-images/sempertex/clusters/fashion-red.jpg', $red->cluster_image_file_path);
    }

    public function test_single_images_are_downloaded_to_public_disk(): void
    {
        $sempertex = Brand::where('name', 'Sempertex')->firstOrFail();

        // Fashion Red has a Shopify match in the manifest, so the seeder should
        // download the single image to the fake public disk.
        $red = Color::where('name', 'Fashion Red')->where('brand_id', $sempertex->id)->firstOrFail();

        $this->assertNotNull($red->single_image_file_path);
        Storage::disk('public')->assertExists($red->single_image_file_path);
    }

    public function test_seeder_is_idempotent(): void
    {
        $sempertex = Brand::where('name', 'Sempertex')->firstOrFail();

        $this->seed(SempertexColorSeeder::class);

        $this->assertSame(126, Color::where('brand_id', $sempertex->id)->count());
    }
}
