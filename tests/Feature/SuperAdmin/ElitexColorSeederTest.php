<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Brand;
use App\Models\Color;
use App\Models\Texture;
use Database\Seeders\BrandSeeder;
use Database\Seeders\ColorFamilySeeder;
use Database\Seeders\ElitexColorSeeder;
use Database\Seeders\ElitexTextureSeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\TextureFamilySeeder;
use Database\Seeders\TextureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ElitexColorSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Http::fake(['*' => Http::response('fake-png-bytes', 200, ['Content-Type' => 'image/png'])]);

        $this->seed(BrandSeeder::class);
        $this->seed(MaterialSeeder::class);
        $this->seed(TextureFamilySeeder::class);
        $this->seed(TextureSeeder::class);
        $this->seed(ColorFamilySeeder::class);
        $this->seed(ElitexTextureSeeder::class);
        $this->seed(ElitexColorSeeder::class);
    }

    public function test_seeder_inserts_all_elitex_colors(): void
    {
        $elitex = Brand::where('name', 'Elitex')->firstOrFail();

        $this->assertSame(64, Color::where('brand_id', $elitex->id)->count());
    }

    public function test_elitex_textures_exist_with_correct_brand(): void
    {
        $elitex = Brand::where('name', 'Elitex')->firstOrFail();

        $expected = [
            'Standard (E)',
            'Metallic & Pearl (E)',
            'Pastel Rainbow (E)',
            'Smoothies (E)',
            'Super Glow (E)',
            'Confetti (E)',
        ];

        foreach ($expected as $name) {
            $this->assertTrue(
                Texture::where('name', $name)->where('brand_id', $elitex->id)->exists(),
                "Texture '{$name}' should exist with Elitex brand_id",
            );
        }
    }

    public function test_colors_use_correct_brand_specific_textures(): void
    {
        $elitex = Brand::where('name', 'Elitex')->firstOrFail();

        $cases = [
            ['name' => 'Red',             'texture' => 'Standard (E)'],
            ['name' => 'Gray',            'texture' => 'Standard (E)'],
            ['name' => 'Metallic Gold',   'texture' => 'Metallic & Pearl (E)'],
            ['name' => 'Pink Pearl',      'texture' => 'Metallic & Pearl (E)'],
            ['name' => 'Coral',           'texture' => 'Pastel Rainbow (E)'],
            ['name' => 'Ivory',           'texture' => 'Pastel Rainbow (E)'],
            ['name' => 'Mango',           'texture' => 'Smoothies (E)'],
            ['name' => 'Gold Superglow',  'texture' => 'Super Glow (E)'],
            ['name' => 'Confetti Red',    'texture' => 'Confetti (E)'],
            ['name' => 'Confetti Silver', 'texture' => 'Confetti (E)'],
        ];

        foreach ($cases as $case) {
            $texture = Texture::where('name', $case['texture'])->where('brand_id', $elitex->id)->firstOrFail();
            $color = Color::where('name', $case['name'])->where('brand_id', $elitex->id)->first();

            $this->assertNotNull($color, "Color '{$case['name']}' not found");
            $this->assertSame($texture->id, $color->texture_id, "'{$case['name']}' should use {$case['texture']}");
        }
    }

    public function test_hex_values_are_stored(): void
    {
        $elitex = Brand::where('name', 'Elitex')->firstOrFail();

        $cases = [
            ['name' => 'Black',        'hex' => '#0B0D11'],
            ['name' => 'Metallic Gold', 'hex' => '#D4A333'],
            ['name' => 'Baby Blue',    'hex' => '#88BBDD'],
            ['name' => 'Blueberry',    'hex' => '#CAABEA'],
            ['name' => 'Gold Superglow', 'hex' => '#866E53'],
            ['name' => 'Confetti Blue', 'hex' => '#4E7F9F'],
        ];

        foreach ($cases as $case) {
            $color = Color::where('name', $case['name'])->where('brand_id', $elitex->id)->first();
            $this->assertNotNull($color, "Color '{$case['name']}' not found");
            $this->assertSame($case['hex'], $color->color_hex);
        }
    }

    public function test_images_are_downloaded_to_public_disk(): void
    {
        $elitex = Brand::where('name', 'Elitex')->firstOrFail();

        $red = Color::where('name', 'Red')->where('brand_id', $elitex->id)->firstOrFail();

        $this->assertSame('color-images/elitex/red.png', $red->single_image_file_path);
        Storage::disk('public')->assertExists($red->single_image_file_path);
    }

    public function test_no_pms_values_are_set(): void
    {
        $elitex = Brand::where('name', 'Elitex')->firstOrFail();

        $this->assertSame(
            0,
            Color::where('brand_id', $elitex->id)->whereNotNull('pms_value')->count(),
        );
    }

    public function test_seeder_is_idempotent(): void
    {
        $elitex = Brand::where('name', 'Elitex')->firstOrFail();

        $this->seed(ElitexColorSeeder::class);

        $this->assertSame(64, Color::where('brand_id', $elitex->id)->count());
    }
}
