<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Brand;
use App\Models\Color;
use App\Models\Texture;
use Database\Seeders\BrandSeeder;
use Database\Seeders\BritetexColorSeeder;
use Database\Seeders\BritetexTextureSeeder;
use Database\Seeders\ColorFamilySeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\TextureFamilySeeder;
use Database\Seeders\TextureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BritetexColorSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BrandSeeder::class);
        $this->seed(MaterialSeeder::class);
        $this->seed(TextureFamilySeeder::class);
        $this->seed(TextureSeeder::class);
        $this->seed(ColorFamilySeeder::class);
        $this->seed(BritetexTextureSeeder::class);
        $this->seed(BritetexColorSeeder::class);
    }

    public function test_seeder_inserts_all_britetex_colors(): void
    {
        $britetex = Brand::where('name', 'Britetex')->firstOrFail();

        $this->assertSame(16, Color::where('brand_id', $britetex->id)->count());
    }

    public function test_every_distributor_seen_color_name_exists(): void
    {
        $britetex = Brand::where('name', 'Britetex')->firstOrFail();

        // The exact colour names the matcher resolves against (Larocks "Color"
        // values, slash-split). Each must exist for the pending proposals to flip
        // from partial to full.
        $expected = [
            'Black', 'Blue', 'Blush', 'Brown', 'Clear', 'Gold', 'Gray', 'Green', 'Nude',
            'Orange', 'Pink', 'Purple', 'Red', 'Silver', 'White', 'Yellow',
        ];

        foreach ($expected as $name) {
            $this->assertTrue(
                Color::where('name', $name)->where('brand_id', $britetex->id)->exists(),
                "Britetex colour '{$name}' should exist",
            );
        }
    }

    public function test_britetex_textures_exist_with_correct_brand(): void
    {
        $britetex = Brand::where('name', 'Britetex')->firstOrFail();

        foreach (['Standard (B)', 'Crystal (B)', 'Macaron (B)', 'Chrome (B)'] as $name) {
            $this->assertTrue(
                Texture::where('name', $name)->where('brand_id', $britetex->id)->exists(),
                "Texture '{$name}' should exist with Britetex brand_id",
            );
        }
    }

    public function test_colors_use_the_standard_brand_texture(): void
    {
        $britetex = Brand::where('name', 'Britetex')->firstOrFail();
        $standard = Texture::where('name', 'Standard (B)')->where('brand_id', $britetex->id)->firstOrFail();

        Color::where('brand_id', $britetex->id)->get()->each(function (Color $color) use ($standard) {
            $this->assertSame($standard->id, $color->texture_id, "'{$color->name}' should use Standard (B)");
        });
    }

    public function test_hex_values_are_stored(): void
    {
        $britetex = Brand::where('name', 'Britetex')->firstOrFail();

        $red = Color::where('name', 'Red')->where('brand_id', $britetex->id)->firstOrFail();

        $this->assertSame('#E0202C', $red->color_hex);
    }

    public function test_seeder_is_idempotent(): void
    {
        $britetex = Brand::where('name', 'Britetex')->firstOrFail();

        $this->seed(BritetexColorSeeder::class);

        $this->assertSame(16, Color::where('brand_id', $britetex->id)->count());
    }
}
