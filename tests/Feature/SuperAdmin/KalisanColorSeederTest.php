<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Brand;
use App\Models\Color;
use App\Models\Texture;
use Database\Seeders\BrandSeeder;
use Database\Seeders\ColorFamilySeeder;
use Database\Seeders\KalisanColorSeeder;
use Database\Seeders\KalisanTextureSeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\TextureFamilySeeder;
use Database\Seeders\TextureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KalisanColorSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Intercept the swatch downloads — the seeder fetches from Kalisan's
        // CDN; in tests we serve fake bytes so the import path is exercised
        // without making real network calls.
        Storage::fake('public');
        Http::fake(['*' => Http::response('fake-png-bytes', 200, ['Content-Type' => 'image/png'])]);

        $this->seed(BrandSeeder::class);
        $this->seed(MaterialSeeder::class);
        $this->seed(TextureFamilySeeder::class);
        $this->seed(TextureSeeder::class);
        $this->seed(ColorFamilySeeder::class);
        $this->seed(KalisanTextureSeeder::class);
        $this->seed(KalisanColorSeeder::class);
    }

    public function test_seeder_inserts_all_kalisan_colors(): void
    {
        $kalisan = Brand::where('name', 'Kalisan')->firstOrFail();

        $this->assertSame(103, Color::where('brand_id', $kalisan->id)->count());
    }

    public function test_texture_seeder_adds_kalisan_textures(): void
    {
        $kalisan = Brand::where('name', 'Kalisan')->firstOrFail();

        $expected = [
            'Standard (K)',
            'Retro (K)',
            'Macaron (K)',
            'Opaque Satin (K)',
            'Metallic (K)',
            'Pearl (K)',
            'Crystal (K)',
            'Mirror (K)',
        ];

        foreach ($expected as $name) {
            $this->assertTrue(
                Texture::where('name', $name)->where('brand_id', $kalisan->id)->exists(),
                "Texture '{$name}' should exist with Kalisan brand_id",
            );
        }
    }

    public function test_colors_use_brand_specific_kalisan_textures(): void
    {
        $kalisan = Brand::where('name', 'Kalisan')->firstOrFail();

        $cases = [
            ['name' => 'Red',                     'texture' => 'Standard (K)'],
            ['name' => 'Retro Wild Berry',        'texture' => 'Retro (K)'],
            ['name' => 'Macaron Pistachio',       'texture' => 'Macaron (K)'],
            ['name' => 'Mirror Rose Gold',        'texture' => 'Mirror (K)'],
            ['name' => 'Pearl Lilac',             'texture' => 'Pearl (K)'],
            ['name' => 'Crystal Burgundy',        'texture' => 'Crystal (K)'],
            ['name' => 'Metallic Gold',           'texture' => 'Metallic (K)'],
            ['name' => 'Opaque Satin Snow White', 'texture' => 'Opaque Satin (K)'],
        ];

        foreach ($cases as $case) {
            $texture = Texture::where('name', $case['texture'])->where('brand_id', $kalisan->id)->firstOrFail();
            $color = Color::where('name', $case['name'])->where('brand_id', $kalisan->id)->first();

            $this->assertNotNull($color, "Color '{$case['name']}' not found");
            $this->assertSame($texture->id, $color->texture_id, "'{$case['name']}' should use {$case['texture']}");
        }
    }

    public function test_pdf_pantone_values_are_preserved(): void
    {
        $kalisan = Brand::where('name', 'Kalisan')->firstOrFail();

        $cases = [
            ['name' => 'Red',              'hex' => '#C5302A', 'pms' => 'PMS 2035 CP'],
            ['name' => 'Turquoise',        'hex' => '#009CA6', 'pms' => 'PMS 3125 C'],
            ['name' => 'Flamingo Pink',    'hex' => '#F5A7C4', 'pms' => 'PMS Red 0331 U'],
            ['name' => 'Retro Wild Berry', 'hex' => '#B1357E', 'pms' => 'PMS 676 U'],
            ['name' => 'Macaron Pink',     'hex' => '#F4C2C2', 'pms' => 'PMS 12-1813 TCX'],
        ];

        foreach ($cases as $case) {
            $color = Color::where('name', $case['name'])->where('brand_id', $kalisan->id)->first();
            $this->assertNotNull($color, "Color '{$case['name']}' not found");
            $this->assertSame($case['hex'], $color->color_hex, "Hex mismatch for '{$case['name']}'");
            $this->assertSame($case['pms'], $color->pms_value, "PMS mismatch for '{$case['name']}'");
        }
    }

    public function test_clear_transparent_has_no_hex(): void
    {
        $kalisan = Brand::where('name', 'Kalisan')->firstOrFail();

        $clear = Color::where('name', 'Clear Transparent')->where('brand_id', $kalisan->id)->firstOrFail();

        $this->assertNull($clear->color_hex);
    }

    public function test_images_are_downloaded_to_public_disk(): void
    {
        $kalisan = Brand::where('name', 'Kalisan')->firstOrFail();

        $black = Color::where('name', 'Black')->where('brand_id', $kalisan->id)->firstOrFail();

        $this->assertSame('color-images/kalisan/black.png', $black->single_image_file_path);
        Storage::disk('public')->assertExists($black->single_image_file_path);
    }

    public function test_seeder_is_idempotent(): void
    {
        $kalisan = Brand::where('name', 'Kalisan')->firstOrFail();

        $this->seed(KalisanColorSeeder::class);

        $this->assertSame(103, Color::where('brand_id', $kalisan->id)->count());
    }
}
