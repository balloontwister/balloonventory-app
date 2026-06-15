<?php

namespace Tests\Feature;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Material;
use App\Models\Shape;
use App\Models\Size;
use App\Models\Sku;
use Database\Seeders\BrandSeeder;
use Database\Seeders\FoilCatalogSeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\SizeSeeder;
use Database\Seeders\TufTexFoilBalloonSizeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TufTexFoilBalloonSizeSeederTest extends TestCase
{
    use RefreshDatabase;

    private Brand $tuftex;

    private Material $foil;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BrandSeeder::class);
        $this->seed(MaterialSeeder::class);
        $this->seed(SizeSeeder::class);
        $this->seed(FoilCatalogSeeder::class);

        $this->tuftex = Brand::where('name', 'TufTex')->firstOrFail();
        $this->foil = Material::where('name', 'Foil')->firstOrFail();
    }

    public function test_creates_the_twelve_canonical_combos(): void
    {
        $this->seed(TufTexFoilBalloonSizeSeeder::class);

        $this->assertSame(12, BalloonSize::where('brand_id', $this->tuftex->id)
            ->where('material_id', $this->foil->id)->count());

        // size_id and shape_id resolve correctly (the bug the legacy rows had).
        $combo = BalloonSize::where('name', '26-inch Foil Shaped')->with('shape')->firstOrFail();
        $this->assertSame(Size::where('name', '26-inch')->value('id'), $combo->size_id);
        $this->assertSame('Shaped', $combo->shape->name);
        $this->assertSame($this->foil->id, $combo->shape->material_id);
    }

    public function test_retires_legacy_scaffolding_rows_that_have_no_skus(): void
    {
        $round = Shape::where('name', 'Round')->where('material_id', $this->foil->id)->firstOrFail();
        $legacy = BalloonSize::create([
            'brand_id' => $this->tuftex->id,
            'material_id' => $this->foil->id,
            'size_id' => Size::where('name', '16-inch')->value('id'),
            'shape_id' => $round->id,
            'name' => '18-inch',
            'sort_order' => 1,
        ]);

        $this->seed(TufTexFoilBalloonSizeSeeder::class);

        $this->assertSoftDeleted('balloon_sizes', ['id' => $legacy->id]);
    }

    public function test_does_not_retire_a_legacy_row_that_carries_skus(): void
    {
        $round = Shape::where('name', 'Round')->where('material_id', $this->foil->id)->firstOrFail();
        $legacy = BalloonSize::create([
            'brand_id' => $this->tuftex->id,
            'material_id' => $this->foil->id,
            'size_id' => Size::where('name', '16-inch')->value('id'),
            'shape_id' => $round->id,
            'name' => '18-inch',
            'sort_order' => 1,
        ]);
        Sku::create([
            'name' => 'Legacy-attached foil',
            'brand_id' => $this->tuftex->id,
            'material_id' => $this->foil->id,
            'balloon_size_id' => $legacy->id,
            'is_active' => true,
        ]);

        $this->seed(TufTexFoilBalloonSizeSeeder::class);

        $this->assertNotSoftDeleted('balloon_sizes', ['id' => $legacy->id]);
    }

    public function test_is_idempotent(): void
    {
        $this->seed(TufTexFoilBalloonSizeSeeder::class);
        $this->seed(TufTexFoilBalloonSizeSeeder::class);

        $this->assertSame(12, BalloonSize::where('brand_id', $this->tuftex->id)
            ->where('material_id', $this->foil->id)->count());
    }
}
