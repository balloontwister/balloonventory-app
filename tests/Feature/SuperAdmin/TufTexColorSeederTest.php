<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Brand;
use App\Models\Color;
use App\Models\Texture;
use Database\Seeders\BrandSeeder;
use Database\Seeders\ColorFamilySeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\TextureFamilySeeder;
use Database\Seeders\TextureSeeder;
use Database\Seeders\TufTexColorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TufTexColorSeederTest extends TestCase
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
        $this->seed(TufTexColorSeeder::class);
    }

    public function test_seeder_inserts_all_tuftex_colors(): void
    {
        $tuftex = Brand::where('name', 'TufTex')->firstOrFail();

        $this->assertSame(79, Color::where('brand_id', $tuftex->id)->count());
    }

    public function test_texture_seeder_adds_designer_and_effects(): void
    {
        $designer = Texture::where('name', 'Designer')->whereNull('brand_id')->first();
        $effects = Texture::where('name', 'Effects')->whereNull('brand_id')->first();

        $this->assertNotNull($designer, 'Designer texture should exist with brand_id = null');
        $this->assertNotNull($effects, 'Effects texture should exist with brand_id = null');
    }

    public function test_designer_colors_use_designer_texture(): void
    {
        $tuftex = Brand::where('name', 'TufTex')->firstOrFail();
        $designer = Texture::where('name', 'Designer')->whereNull('brand_id')->firstOrFail();

        $designerColors = ['Lime Green', 'Navy', 'Pixie', 'Turquoise', 'Lace', 'Samba', 'Peri'];

        foreach ($designerColors as $colorName) {
            $color = Color::where('name', $colorName)->where('brand_id', $tuftex->id)->first();
            $this->assertNotNull($color, "Color '{$colorName}' not found");
            $this->assertSame($designer->id, $color->texture_id, "'{$colorName}' should use Designer texture");
        }
    }

    public function test_effects_colors_use_effects_texture(): void
    {
        $tuftex = Brand::where('name', 'TufTex')->firstOrFail();
        $effects = Texture::where('name', 'Effects')->whereNull('brand_id')->firstOrFail();

        foreach (['Shadow', 'Golden', 'Rockstar Pink', 'Silvery'] as $colorName) {
            $color = Color::where('name', $colorName)->where('brand_id', $tuftex->id)->first();
            $this->assertNotNull($color, "Effects color '{$colorName}' not found");
            $this->assertSame($effects->id, $color->texture_id, "'{$colorName}' should use Effects texture");
        }
    }

    public function test_colors_have_correct_hex_and_pms(): void
    {
        $tuftex = Brand::where('name', 'TufTex')->firstOrFail();

        $assertions = [
            ['name' => 'Turquoise',    'hex' => '#009EC4', 'pms' => 'PMS 312 C'],
            ['name' => 'Scarlett',     'hex' => '#C8102E', 'pms' => 'PMS 194 C'],
            ['name' => 'Starfire Red', 'hex' => '#BE2035', 'pms' => 'PMS 193 C'],
            ['name' => 'Shadow',       'hex' => '#555859', 'pms' => 'PMS 426 C'],
            ['name' => 'Crystal Yellow', 'hex' => '#EEE020', 'pms' => 'PMS 3945 C'],
            ['name' => 'Coral',        'hex' => '#F07163', 'pms' => 'PMS 177 C'],
        ];

        foreach ($assertions as $data) {
            $color = Color::where('name', $data['name'])->where('brand_id', $tuftex->id)->first();
            $this->assertNotNull($color, "Color '{$data['name']}' not found");
            $this->assertSame($data['hex'], $color->color_hex, "Hex mismatch for '{$data['name']}'");
            $this->assertSame($data['pms'], $color->pms_value, "PMS mismatch for '{$data['name']}'");
        }
    }

    public function test_lace_conflict_resolved_with_pearl_lace(): void
    {
        $tuftex = Brand::where('name', 'TufTex')->firstOrFail();
        $designer = Texture::where('name', 'Designer')->whereNull('brand_id')->firstOrFail();
        $pearl = Texture::where('name', 'Pearl')->whereNull('brand_id')->firstOrFail();

        $lace = Color::where('name', 'Lace')->where('brand_id', $tuftex->id)->first();
        $pearlLace = Color::where('name', 'Pearl Lace')->where('brand_id', $tuftex->id)->first();

        $this->assertNotNull($lace, '"Lace" (Designer) should exist');
        $this->assertNotNull($pearlLace, '"Pearl Lace" should exist for the Pearl variant');
        $this->assertSame($designer->id, $lace->texture_id, '"Lace" should use Designer texture');
        $this->assertSame($pearl->id, $pearlLace->texture_id, '"Pearl Lace" should use Pearl texture');
    }

    public function test_clear_has_no_hex(): void
    {
        $tuftex = Brand::where('name', 'TufTex')->firstOrFail();

        $clear = Color::where('name', 'Clear')->where('brand_id', $tuftex->id)->firstOrFail();

        $this->assertNull($clear->color_hex);
    }

    public function test_seeder_is_idempotent(): void
    {
        $tuftex = Brand::where('name', 'TufTex')->firstOrFail();

        $this->seed(TufTexColorSeeder::class);

        $this->assertSame(79, Color::where('brand_id', $tuftex->id)->count());
    }
}
