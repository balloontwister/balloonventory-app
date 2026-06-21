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
use Database\Seeders\QualatexColorBackfillSeeder;
use Database\Seeders\TextureFamilySeeder;
use Database\Seeders\TextureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QualatexColorBackfillSeederTest extends TestCase
{
    use RefreshDatabase;

    private Brand $qualatex;

    private Texture $standardQ;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BrandSeeder::class);
        $this->seed(MaterialSeeder::class);
        $this->seed(TextureFamilySeeder::class);
        $this->seed(TextureSeeder::class);
        $this->seed(ColorFamilySeeder::class);

        $this->qualatex = Brand::firstOrCreate(
            ['name' => 'Qualatex'],
            ['abbreviation' => 'QTX', 'is_active' => true],
        );

        $latex = Material::where('name', 'Latex')->firstOrFail();
        $this->standardQ = Texture::firstOrCreate(
            ['name' => 'Standard (Q)', 'brand_id' => $this->qualatex->id],
            ['material_id' => $latex->id, 'sort_order' => 1],
        );

        // Simulate the six standard colors stranded on a different texture.
        $other = Texture::where('name', 'Crystal')->whereNull('brand_id')->firstOrFail();
        $family = ColorFamily::firstOrFail();
        foreach (['White', 'Black', 'Red', 'Orange', 'Green', 'Blue'] as $name) {
            Color::create([
                'name' => $name,
                'brand_id' => $this->qualatex->id,
                'material_id' => $latex->id,
                'texture_id' => $other->id,
                'color_family_id' => $family->id,
            ]);
        }
    }

    public function test_seeder_adds_new_colors_and_repairs_stranded_six(): void
    {
        $before = Color::where('brand_id', $this->qualatex->id)->count();

        $this->seed(QualatexColorBackfillSeeder::class);

        $after = Color::where('brand_id', $this->qualatex->id)->count();
        $this->assertSame($before + 51, $after, 'adds the 51 backfill colors');

        // Repaired: the six standard colors now point at Standard (Q).
        foreach (['White', 'Black', 'Red', 'Orange', 'Green', 'Blue'] as $name) {
            $color = Color::where('brand_id', $this->qualatex->id)->where('name', $name)->firstOrFail();
            $this->assertSame($this->standardQ->id, $color->texture_id, "{$name} remapped to Standard (Q)");
        }
    }

    public function test_a_sample_new_color_resolves_texture_family_and_hex(): void
    {
        $this->seed(QualatexColorBackfillSeeder::class);

        $citrine = Color::where('brand_id', $this->qualatex->id)->where('name', 'Citrine Yellow')->firstOrFail();
        $this->assertSame('Crystal', $citrine->texture->name);
        $this->assertSame('Yellows', $citrine->colorFamily->name);
        $this->assertSame('#F6D108', $citrine->color_hex);

        $pearl = Color::where('brand_id', $this->qualatex->id)->where('name', 'Pearl Magenta')->firstOrFail();
        $this->assertSame('Pearl', $pearl->texture->name);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(QualatexColorBackfillSeeder::class);
        $count = Color::where('brand_id', $this->qualatex->id)->count();

        $this->seed(QualatexColorBackfillSeeder::class);

        $this->assertSame($count, Color::where('brand_id', $this->qualatex->id)->count());
    }
}
