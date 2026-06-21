<?php

namespace Tests\Feature\Console;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Color;
use App\Models\ColorFamily;
use App\Models\Material;
use App\Models\Shape;
use App\Models\Size;
use App\Models\Sku;
use App\Models\Texture;
use Database\Seeders\BrandSeeder;
use Database\Seeders\ColorFamilySeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\PackagingTypeSeeder;
use Database\Seeders\ShapeSeeder;
use Database\Seeders\SizeSeeder;
use Database\Seeders\TextureFamilySeeder;
use Database\Seeders\TextureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportQualatexSkusTest extends TestCase
{
    use RefreshDatabase;

    private string $jsonPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BrandSeeder::class);
        $this->seed(MaterialSeeder::class);
        $this->seed(ShapeSeeder::class);
        $this->seed(SizeSeeder::class);
        $this->seed(PackagingTypeSeeder::class);
        $this->seed(TextureFamilySeeder::class);
        $this->seed(TextureSeeder::class);
        $this->seed(ColorFamilySeeder::class);

        $this->seedQualatexReferenceData();

        $this->jsonPath = storage_path('app/test_qualatex_normalized.json');
        file_put_contents($this->jsonPath, json_encode($this->fixtureRows(), JSON_PRETTY_PRINT));
    }

    protected function tearDown(): void
    {
        if (file_exists($this->jsonPath)) {
            unlink($this->jsonPath);
        }

        parent::tearDown();
    }

    /**
     * Seed the minimal Qualatex reference data the fixture FKs against. Mirrors
     * production: balloon-size names carry the "(Q)" suffix.
     */
    private function seedQualatexReferenceData(): void
    {
        $qualatex = Brand::where('name', 'Qualatex')->firstOrFail();
        $latex = Material::where('name', 'Latex')->firstOrFail();
        $round = Shape::where('name', 'Round')->firstOrFail();
        $nonRound = Shape::where('name', 'Non-round')->firstOrFail();
        $link = Shape::where('name', 'Link')->firstOrFail();
        $families = ColorFamily::pluck('id', 'name');

        $texture = Texture::firstOrCreate(
            ['name' => 'Standard (Q)', 'brand_id' => $qualatex->id],
            ['material_id' => $latex->id, 'sort_order' => 1],
        );

        $sizes = [
            ['5-inch (Q)', $round, '5-inch'],
            ['11-inch (Q)', $round, '11-inch'],
            ['160 (Q)', $nonRound, '160'],
            ['260Q (Q)', $nonRound, '260'],
            ['QL-12 (Q)', $link, '11-inch'],
        ];

        foreach ($sizes as [$name, $shape, $sizeFamily]) {
            BalloonSize::firstOrCreate(
                ['name' => $name, 'brand_id' => $qualatex->id],
                [
                    'material_id' => $latex->id,
                    'size_id' => Size::where('name', $sizeFamily)->firstOrFail()->id,
                    'shape_id' => $shape->id,
                    'sort_order' => 0,
                ],
            );
        }

        foreach ([
            ['Orange', 'Oranges'], ['Goldenrod', 'Yellows'], ['Emerald Green', 'Greens'],
            ['Wild Berry', 'Purples'], ['Gold', 'Golds'],
        ] as [$name, $family]) {
            Color::firstOrCreate(
                ['name' => $name, 'brand_id' => $qualatex->id],
                [
                    'color_family_id' => $families[$family] ?? null,
                    'material_id' => $latex->id,
                    'texture_id' => $texture->id,
                    'sort_order' => 0,
                ],
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fixtureRows(): array
    {
        return [
            ['warehouse_sku' => '43570', 'upc' => '071444435703', 'raw_name' => '5" Round Orange',
                'balloon_size' => '5-inch (Q)', 'packaging' => 'Loose', 'color_resolved' => 'Orange', 'count_per_bag' => 100],
            ['warehouse_sku' => '43942', 'upc' => '071444439428', 'raw_name' => '260Q Goldenrod',
                'balloon_size' => '260Q (Q)', 'packaging' => 'Loose', 'color_resolved' => 'Goldenrod', 'count_per_bag' => 100],
            ['warehouse_sku' => '43909', 'upc' => '071444439091', 'raw_name' => '160Q Emerald Green',
                'balloon_size' => '160 (Q)', 'packaging' => 'Loose', 'color_resolved' => 'Emerald Green', 'count_per_bag' => 100],
            // Same size+color as the next row → identical_skus pair
            ['warehouse_sku' => '255721', 'upc' => '071444255721', 'raw_name' => '11" Round Wild Berry',
                'balloon_size' => '11-inch (Q)', 'packaging' => 'Loose', 'color_resolved' => 'Wild Berry', 'count_per_bag' => 100],
            ['warehouse_sku' => '259996', 'upc' => '071444259996', 'raw_name' => '11" Round Wild Berry',
                'balloon_size' => '11-inch (Q)', 'packaging' => 'Loose', 'color_resolved' => 'Wild Berry', 'count_per_bag' => 25],
            // Link shape path
            ['warehouse_sku' => 'QL99', 'upc' => '071444437493', 'raw_name' => '11" Q-Link Gold',
                'balloon_size' => 'QL-12 (Q)', 'packaging' => 'Loose', 'color_resolved' => 'Gold', 'count_per_bag' => 50],
        ];
    }

    public function test_dry_run_reports_inserts_without_writing(): void
    {
        $brand = Brand::where('name', 'Qualatex')->firstOrFail();
        $before = Sku::where('brand_id', $brand->id)->count();

        $this->artisan('catalog:import-qualatex', ['--path' => $this->jsonPath])
            ->expectsOutputToContain('Inserts: 6')
            ->assertSuccessful();

        $this->assertSame($before, Sku::where('brand_id', $brand->id)->count());
    }

    public function test_execute_writes_all_rows_with_upc(): void
    {
        $this->artisan('catalog:import-qualatex', ['--path' => $this->jsonPath, '--execute' => true])
            ->assertSuccessful();

        $sku = Sku::where('warehouse_sku', '43570')->firstOrFail();
        $this->assertSame('071444435703', $sku->upc);
        $this->assertNull($sku->ean, '12-digit barcode routes to upc, not ean');
        $this->assertSame(100, (int) $sku->default_count_per_bag);
    }

    public function test_fk_resolution_round_twisting_link(): void
    {
        $this->artisan('catalog:import-qualatex', ['--path' => $this->jsonPath, '--execute' => true])
            ->assertSuccessful();

        $round = Sku::where('warehouse_sku', '43570')->firstOrFail();
        $this->assertSame('5-inch (Q)', $round->balloonSize->name);
        $this->assertSame('Round', $round->balloonSize->shape->name);

        $twist = Sku::where('warehouse_sku', '43942')->firstOrFail();
        $this->assertSame('Non-round', $twist->balloonSize->shape->name);

        $link = Sku::where('warehouse_sku', 'QL99')->firstOrFail();
        $this->assertSame('Link', $link->balloonSize->shape->name);
    }

    public function test_identical_skus_links_pack_variants(): void
    {
        $this->artisan('catalog:import-qualatex', ['--path' => $this->jsonPath, '--execute' => true])
            ->assertSuccessful();

        $a = Sku::where('warehouse_sku', '255721')->firstOrFail();
        $b = Sku::where('warehouse_sku', '259996')->firstOrFail();

        $this->assertTrue($a->identicalSkus()->where('skus.id', $b->id)->exists());
        $this->assertTrue($b->identicalSkus()->where('skus.id', $a->id)->exists());
    }

    public function test_execute_is_idempotent(): void
    {
        $brand = Brand::where('name', 'Qualatex')->firstOrFail();

        $this->artisan('catalog:import-qualatex', ['--path' => $this->jsonPath, '--execute' => true])->assertSuccessful();
        $countAfterFirst = Sku::where('brand_id', $brand->id)->count();

        $this->artisan('catalog:import-qualatex', ['--path' => $this->jsonPath, '--execute' => true])->assertSuccessful();

        $this->assertSame($countAfterFirst, Sku::where('brand_id', $brand->id)->count());
        $this->assertSame(1, Sku::where('warehouse_sku', '255721')->firstOrFail()->identicalSkus()->count());
    }

    public function test_unresolved_color_is_warned_and_skipped(): void
    {
        $rows = $this->fixtureRows();
        $rows[] = ['warehouse_sku' => '900001', 'upc' => '071444437493', 'raw_name' => '11" Round Unicorn Sparkle',
            'balloon_size' => '11-inch (Q)', 'packaging' => 'Loose', 'color_resolved' => 'Unicorn Sparkle', 'count_per_bag' => 100];
        // give it a distinct valid UPC so the only warning is the color
        $rows[6]['upc'] = '071444489553';
        file_put_contents($this->jsonPath, json_encode($rows));

        $this->artisan('catalog:import-qualatex', ['--path' => $this->jsonPath, '--execute' => true])
            ->expectsOutputToContain("Color not found 'Unicorn Sparkle'")
            ->expectsConfirmation('There are 1 warnings. Continue anyway?', 'yes')
            ->assertSuccessful();

        $this->assertNull(Sku::where('warehouse_sku', '900001')->first());
    }

    public function test_duplicate_upc_in_feed_is_dropped_not_clobbered(): void
    {
        $rows = $this->fixtureRows();
        // A second row carrying an already-seen UPC: the SKU still imports, but
        // without the duplicate barcode.
        $rows[] = ['warehouse_sku' => '900002', 'upc' => '071444435703', 'raw_name' => '5" Round Orange dup',
            'balloon_size' => '5-inch (Q)', 'packaging' => 'Loose', 'color_resolved' => 'Orange', 'count_per_bag' => 50];
        file_put_contents($this->jsonPath, json_encode($rows));

        $this->artisan('catalog:import-qualatex', ['--path' => $this->jsonPath, '--execute' => true])
            ->expectsOutputToContain('Duplicate UPC 071444435703')
            ->assertSuccessful();

        $dup = Sku::where('warehouse_sku', '900002')->firstOrFail();
        $this->assertNull($dup->upc, 'duplicate barcode is not stored on the second SKU');
        $this->assertSame('071444435703', Sku::where('warehouse_sku', '43570')->firstOrFail()->upc);
    }

    public function test_invalid_upc_check_digit_is_rejected(): void
    {
        $rows = $this->fixtureRows();
        $rows[0]['upc'] = '071444435704'; // corrupt check digit (was ...703)
        file_put_contents($this->jsonPath, json_encode($rows));

        $this->artisan('catalog:import-qualatex', ['--path' => $this->jsonPath, '--execute' => true])
            ->expectsConfirmation('There are 1 warnings. Continue anyway?', 'yes')
            ->assertSuccessful();

        $sku = Sku::where('warehouse_sku', '43570')->firstOrFail();
        $this->assertNull($sku->upc);
    }

    public function test_fails_cleanly_on_invalid_json(): void
    {
        file_put_contents($this->jsonPath, 'not valid json {');

        $this->artisan('catalog:import-qualatex', ['--path' => $this->jsonPath, '--execute' => true])
            ->expectsOutputToContain('empty or invalid JSON')
            ->assertFailed();
    }
}
