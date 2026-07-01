<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Color;
use App\Models\Shape;
use App\Models\Texture;
use Database\Seeders\BrandSeeder;
use Database\Seeders\ColorFamilySeeder;
use Database\Seeders\FunsationalBalloonSizeSeeder;
use Database\Seeders\FunsationalColorSeeder;
use Database\Seeders\FunsationalTextureSeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\ShapeSeeder;
use Database\Seeders\SizeSeeder;
use Database\Seeders\TextureFamilySeeder;
use Database\Seeders\TextureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FunsationalReferenceSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BrandSeeder::class);
        $this->seed(MaterialSeeder::class);
        $this->seed(SizeSeeder::class);
        $this->seed(ShapeSeeder::class);
        $this->seed(TextureFamilySeeder::class);
        $this->seed(TextureSeeder::class);
        $this->seed(ColorFamilySeeder::class);
        $this->seed(FunsationalTextureSeeder::class);
        $this->seed(FunsationalBalloonSizeSeeder::class);
        $this->seed(FunsationalColorSeeder::class);
    }

    private function brand(): Brand
    {
        return Brand::where('name', 'Funsational')->firstOrFail();
    }

    public function test_creates_the_four_brand_textures(): void
    {
        foreach (['Standard (F)', 'Pearl (F)', 'Crystal (F)', 'Pastel (F)'] as $name) {
            $this->assertTrue(
                Texture::where('name', $name)->where('brand_id', $this->brand()->id)->exists(),
                "Texture '{$name}' should exist with the Funsational brand_id",
            );
        }
    }

    public function test_creates_the_three_round_sizes(): void
    {
        $round = Shape::where('name', 'Round')->firstOrFail();

        foreach (['7-inch (F)', '12-inch (F)', '17-inch (F)'] as $name) {
            $size = BalloonSize::where('name', $name)->where('brand_id', $this->brand()->id)->first();
            $this->assertNotNull($size, "Balloon size '{$name}' should exist");
            $this->assertSame($round->id, $size->shape_id);
        }
    }

    public function test_seeds_every_manifest_color(): void
    {
        $manifest = json_decode(file_get_contents(database_path('data/funsational_color_manifest.json')), true);

        $this->assertSame(
            count($manifest['colors']),
            Color::where('brand_id', $this->brand()->id)->count(),
        );
    }

    public function test_a_finish_color_links_to_its_texture_and_family(): void
    {
        $pearlGreen = Color::where('name', 'Pearl Mint Green')->where('brand_id', $this->brand()->id)->firstOrFail();
        $pearl = Texture::where('name', 'Pearl (F)')->where('brand_id', $this->brand()->id)->firstOrFail();

        $this->assertSame($pearl->id, $pearlGreen->texture_id);
        $this->assertSame('Greens', $pearlGreen->colorFamily->name);
        $this->assertNotNull($pearlGreen->color_hex);
    }

    public function test_standard_color_is_named_without_a_finish_prefix(): void
    {
        $red = Color::where('name', 'Red')->where('brand_id', $this->brand()->id)->firstOrFail();
        $standard = Texture::where('name', 'Standard (F)')->where('brand_id', $this->brand()->id)->firstOrFail();

        $this->assertSame($standard->id, $red->texture_id);
    }

    public function test_assortment_lands_in_the_assortment_family_without_hex(): void
    {
        $assortment = Color::where('name', 'Assortment')->where('brand_id', $this->brand()->id)->firstOrFail();

        $this->assertSame('Assortment', $assortment->colorFamily->name);
        $this->assertNull($assortment->color_hex);
    }

    public function test_seeders_are_idempotent(): void
    {
        $count = Color::where('brand_id', $this->brand()->id)->count();

        $this->seed(FunsationalTextureSeeder::class);
        $this->seed(FunsationalBalloonSizeSeeder::class);
        $this->seed(FunsationalColorSeeder::class);

        $this->assertSame($count, Color::where('brand_id', $this->brand()->id)->count());
        $this->assertSame(4, Texture::where('brand_id', $this->brand()->id)->count());
        $this->assertSame(3, BalloonSize::where('brand_id', $this->brand()->id)->count());
    }
}
