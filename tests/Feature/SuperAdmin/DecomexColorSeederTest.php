<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Color;
use App\Models\ColorFamily;
use App\Models\Texture;
use Database\Seeders\BrandSeeder;
use Database\Seeders\ColorFamilySeeder;
use Database\Seeders\DecomexBalloonSizeSeeder;
use Database\Seeders\DecomexColorSeeder;
use Database\Seeders\DecomexTextureSeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\ShapeSeeder;
use Database\Seeders\SizeSeeder;
use Database\Seeders\TextureFamilySeeder;
use Database\Seeders\TextureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DecomexColorSeederTest extends TestCase
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
        $this->seed(SizeSeeder::class);
        $this->seed(ShapeSeeder::class);
        $this->seed(DecomexTextureSeeder::class);
        $this->seed(DecomexBalloonSizeSeeder::class);
        $this->seed(DecomexColorSeeder::class);
    }

    public function test_seeder_inserts_all_decomex_colors(): void
    {
        $decomex = Brand::where('name', 'Decomex')->firstOrFail();

        $this->assertSame(130, Color::where('brand_id', $decomex->id)->count());
    }

    public function test_texture_seeder_adds_decomex_textures(): void
    {
        $decomex = Brand::where('name', 'Decomex')->firstOrFail();

        $expected = [
            'Standard (D)',
            'Pastel Deco (D)',
            'Jewel Crystal (D)',
            'Pearl/Metallic (D)',
            'Luster (D)',
        ];

        foreach ($expected as $name) {
            $this->assertTrue(
                Texture::where('name', $name)->where('brand_id', $decomex->id)->exists(),
                "Texture '{$name}' should exist with Decomex brand_id",
            );
        }
    }

    public function test_balloon_size_seeder_adds_decomex_sizes(): void
    {
        $decomex = Brand::where('name', 'Decomex')->firstOrFail();

        $sizes = BalloonSize::where('brand_id', $decomex->id)->get();

        $this->assertCount(16, $sizes);
    }

    public function test_colors_use_brand_specific_decomex_textures(): void
    {
        $decomex = Brand::where('name', 'Decomex')->firstOrFail();

        $cases = [
            // Standard
            ['name' => '100 - Standard White',         'texture' => 'Standard (D)'],
            ['name' => '110 - Standard Red',           'texture' => 'Standard (D)'],
            ['name' => '180 - Standard Black',         'texture' => 'Standard (D)'],
            // Pastel Deco
            ['name' => '201 - Pastel Deco Grey',       'texture' => 'Pastel Deco (D)'],
            ['name' => '211 - Pastel Deco Fuchsia',    'texture' => 'Pastel Deco (D)'],
            ['name' => '217 - Pastel Deco Deep Rose',  'texture' => 'Pastel Deco (D)'],
            ['name' => '263 - Matte Mint Green',       'texture' => 'Pastel Deco (D)'],
            // Jewel Crystal
            ['name' => '300 - Jewel Crystal Clear',    'texture' => 'Jewel Crystal (D)'],
            ['name' => '311 - Jewel Chilli Red',       'texture' => 'Jewel Crystal (D)'],
            // Pearl/Metallic
            ['name' => '400 - Pearl Metallic White',   'texture' => 'Pearl/Metallic (D)'],
            ['name' => '442 - Metallic Gold',          'texture' => 'Pearl/Metallic (D)'],
            // Luster
            ['name' => '501 - Luster Silver',          'texture' => 'Luster (D)'],
            ['name' => '508 - Luster Green',           'texture' => 'Luster (D)'],
        ];

        foreach ($cases as $case) {
            $texture = Texture::where('name', $case['texture'])->where('brand_id', $decomex->id)->firstOrFail();
            $color = Color::where('name', $case['name'])->where('brand_id', $decomex->id)->first();

            $this->assertNotNull($color, "Color '{$case['name']}' not found");
            $this->assertSame($texture->id, $color->texture_id, "'{$case['name']}' should use {$case['texture']}");
        }
    }

    public function test_colors_are_in_correct_families(): void
    {
        $decomex = Brand::where('name', 'Decomex')->firstOrFail();

        $cases = [
            ['name' => '100 - Standard White',            'family' => 'Whites'],
            ['name' => '110 - Standard Red',              'family' => 'Reds'],
            ['name' => '120 - Standard Pink',             'family' => 'Pinks'],
            ['name' => '130 - Standard Orange',           'family' => 'Oranges'],
            ['name' => '140 - Standard Yellow',           'family' => 'Yellows'],
            ['name' => '160 - Standard Green',            'family' => 'Greens'],
            ['name' => '170 - Standard Medium Blue',      'family' => 'Blues'],
            ['name' => '150 - Standard Lavender',         'family' => 'Purples'],
            ['name' => '180 - Standard Black',            'family' => 'Blacks'],
            ['name' => '202 - Pastel Deco Sand',          'family' => 'Browns'],
            ['name' => '199 - Standard Assorted',         'family' => 'Assortment'],
            ['name' => '300 - Jewel Crystal Clear',       'family' => 'Clears'],
            ['name' => '254 - Pastel Deco Chocolate Brown', 'family' => 'Browns'],
            ['name' => '253 - Pastel Deco Burgundy',      'family' => 'Purples'],
        ];

        foreach ($cases as $case) {
            $family = ColorFamily::where('name', $case['family'])->firstOrFail();
            $color = Color::where('name', $case['name'])->where('brand_id', $decomex->id)->first();

            $this->assertNotNull($color, "Color '{$case['name']}' not found");
            $this->assertSame($family->id, $color->color_family_id, "'{$case['name']}' should be in {$case['family']}");
        }
    }

    public function test_color_counts_by_texture(): void
    {
        $decomex = Brand::where('name', 'Decomex')->firstOrFail();

        $counts = [
            'Standard (D)' => 19,
            'Pastel Deco (D)' => 62,
            'Jewel Crystal (D)' => 6,
            'Pearl/Metallic (D)' => 36,
            'Luster (D)' => 7,
        ];

        foreach ($counts as $textureName => $expectedCount) {
            $texture = Texture::where('name', $textureName)->where('brand_id', $decomex->id)->firstOrFail();

            $actual = Color::where('brand_id', $decomex->id)
                ->where('texture_id', $texture->id)
                ->count();

            $this->assertSame(
                $expectedCount,
                $actual,
                "Expected {$expectedCount} colors for '{$textureName}', got {$actual}",
            );
        }
    }

    public function test_color_names_follow_number_dash_name_convention(): void
    {
        $decomex = Brand::where('name', 'Decomex')->firstOrFail();

        $colors = Color::where('brand_id', $decomex->id)->get();

        foreach ($colors as $color) {
            $this->assertMatchesRegularExpression(
                '/^\d{3} - /',
                $color->name,
                "Color '{$color->name}' should start with a 3-digit number followed by ' - '",
            );
        }
    }

    public function test_assortment_and_clear_colors_have_no_hex(): void
    {
        $decomex = Brand::where('name', 'Decomex')->firstOrFail();

        // Assorted has no hex
        $assorted = Color::where('name', '199 - Standard Assorted')->where('brand_id', $decomex->id)->firstOrFail();
        $this->assertSame('Assortment', $assorted->colorFamily->name);
        $this->assertNull($assorted->color_hex);

        // Crystal Clear is transparent — hex is sampled but it's essentially clear
        $clear = Color::where('name', '300 - Jewel Crystal Clear')->where('brand_id', $decomex->id)->firstOrFail();
        $this->assertSame('Clears', $clear->colorFamily->name);
        $this->assertNotNull($clear->color_hex, 'Crystal Clear should have a hex value (sampled from image)');
    }

    public function test_seeder_is_idempotent(): void
    {
        $decomex = Brand::where('name', 'Decomex')->firstOrFail();

        $this->seed(DecomexColorSeeder::class);

        $this->assertSame(130, Color::where('brand_id', $decomex->id)->count());
    }

    public function test_pastel_deco_256_pink_blush_is_only_entry(): void
    {
        // Verify the "Pink Blush" / "Skin Blush" duplicate was resolved
        $decomex = Brand::where('name', 'Decomex')->firstOrFail();

        $colors256 = Color::where('brand_id', $decomex->id)
            ->where('name', 'like', '256 - %')
            ->get();

        $this->assertCount(1, $colors256, 'There should be exactly one color with number 256');
        $this->assertSame('256 - Pastel Deco Pink Blush', $colors256->first()->name);
    }
}
