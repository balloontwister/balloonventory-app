<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Brand;
use App\Models\Color;
use App\Models\ColorFamily;
use App\Models\Material;
use App\Models\Texture;
use Database\Seeders\BrandSeeder;
use Database\Seeders\ColorFamilySeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\QualatexColorSeeder;
use Database\Seeders\TextureFamilySeeder;
use Database\Seeders\TextureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QualatexColorSeederTest extends TestCase
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
        $this->seed(QualatexColorSeeder::class);
    }

    public function test_seeder_inserts_all_qualatex_colors(): void
    {
        $qualatex = Brand::where('name', 'Qualatex')->firstOrFail();

        $this->assertSame(53, Color::where('brand_id', $qualatex->id)->count());
    }

    public function test_qualatex_colors_belong_to_latex_material(): void
    {
        $qualatex = Brand::where('name', 'Qualatex')->firstOrFail();
        $latex = Material::where('name', 'Latex')->firstOrFail();

        $nonLatex = Color::where('brand_id', $qualatex->id)
            ->where('material_id', '!=', $latex->id)
            ->count();

        $this->assertSame(0, $nonLatex);
    }

    public function test_jewel_colors_use_crystal_texture(): void
    {
        $qualatex = Brand::where('name', 'Qualatex')->firstOrFail();
        $crystal = Texture::where('name', 'Crystal')->whereNull('brand_id')->firstOrFail();

        $jewel = ['Ruby Red', 'Goldenrod', 'Wild Berry', "Robin's Egg", 'Sapphire Blue', 'Emerald Green'];

        foreach ($jewel as $colorName) {
            $color = Color::where('name', $colorName)->where('brand_id', $qualatex->id)->first();
            $this->assertNotNull($color, "Color '{$colorName}' not found");
            $this->assertSame($crystal->id, $color->texture_id, "'{$colorName}' should use Crystal texture");
        }
    }

    public function test_colors_have_hex_and_correct_family(): void
    {
        $qualatex = Brand::where('name', 'Qualatex')->firstOrFail();

        $assertions = [
            ['name' => 'Wild Berry',  'hex' => '#7C4D79', 'family' => 'Purples'],
            ['name' => 'Ruby Red',    'hex' => '#BE2035', 'family' => 'Reds'],
            ['name' => 'Goldenrod',   'hex' => '#FFCD00', 'family' => 'Yellows'],
            ['name' => 'Gold',        'hex' => '#C8A951', 'family' => 'Golds'],
            ['name' => 'Pearl White', 'hex' => '#F5F0E8', 'family' => 'Whites'],
            ['name' => 'Neon Green',  'hex' => '#39FF14', 'family' => 'Greens'],
        ];

        foreach ($assertions as $data) {
            $color = Color::where('name', $data['name'])
                ->where('brand_id', $qualatex->id)
                ->with('colorFamily')
                ->first();

            $this->assertNotNull($color, "Color '{$data['name']}' not found");
            $this->assertSame($data['hex'], $color->color_hex);
            $this->assertSame($data['family'], $color->colorFamily->name);
        }
    }

    public function test_jewel_colors_store_pms_values(): void
    {
        $qualatex = Brand::where('name', 'Qualatex')->firstOrFail();

        $pmsAssertions = [
            'Wild Berry'   => 'PMS 256 C',
            'Sapphire Blue' => 'PMS 286 C',
            'Magenta'      => 'PMS 226 C',
        ];

        foreach ($pmsAssertions as $name => $expectedPms) {
            $color = Color::where('name', $name)->where('brand_id', $qualatex->id)->first();
            $this->assertSame($expectedPms, $color->pms_value, "PMS mismatch for '{$name}'");
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        $qualatex = Brand::where('name', 'Qualatex')->firstOrFail();

        $this->seed(QualatexColorSeeder::class);

        $this->assertSame(53, Color::where('brand_id', $qualatex->id)->count());
    }
}
