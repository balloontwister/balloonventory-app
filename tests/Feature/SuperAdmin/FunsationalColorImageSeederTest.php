<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Brand;
use App\Models\Color;
use Database\Seeders\BrandSeeder;
use Database\Seeders\ColorFamilySeeder;
use Database\Seeders\FunsationalColorImageSeeder;
use Database\Seeders\FunsationalColorSeeder;
use Database\Seeders\FunsationalTextureSeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\TextureFamilySeeder;
use Database\Seeders\TextureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FunsationalColorImageSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        // Array-form fake — the closure form doesn't survive $this->seed().
        Http::fake(['*' => Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/jpeg'])]);

        $this->seed(BrandSeeder::class);
        $this->seed(MaterialSeeder::class);
        $this->seed(TextureFamilySeeder::class);
        $this->seed(TextureSeeder::class);
        $this->seed(ColorFamilySeeder::class);
        $this->seed(FunsationalTextureSeeder::class);
        $this->seed(FunsationalColorSeeder::class);
        $this->seed(FunsationalColorImageSeeder::class);
    }

    private function brand(): Brand
    {
        return Brand::where('name', 'Funsational')->firstOrFail();
    }

    public function test_matched_color_gets_an_image_path_and_file(): void
    {
        $babyBlue = Color::where('name', 'Baby Blue')->where('brand_id', $this->brand()->id)->firstOrFail();

        $this->assertSame('color-images/funsational/baby-blue.jpg', $babyBlue->single_image_file_path);
        Storage::disk('public')->assertExists($babyBlue->single_image_file_path);
    }

    public function test_many_colors_are_covered(): void
    {
        $withImage = Color::where('brand_id', $this->brand()->id)->whereNotNull('single_image_file_path')->count();

        // The committed map matches ~47 of 74 colours.
        $this->assertGreaterThan(40, $withImage);
    }

    public function test_unmatched_crystal_color_has_no_image(): void
    {
        $crystal = Color::where('name', 'Crystal Blue')->where('brand_id', $this->brand()->id)->firstOrFail();

        $this->assertNull($crystal->single_image_file_path);
    }

    public function test_is_idempotent(): void
    {
        $before = Color::where('brand_id', $this->brand()->id)->whereNotNull('single_image_file_path')->count();

        $this->seed(FunsationalColorImageSeeder::class);

        $after = Color::where('brand_id', $this->brand()->id)->whereNotNull('single_image_file_path')->count();
        $this->assertSame($before, $after);
    }
}
