<?php

namespace Tests\Feature\Console;

use App\Models\Brand;
use App\Models\Sku;
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

class ImportFunsationalSkusTest extends TestCase
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
        $this->seed(TextureFamilySeeder::class);
        $this->seed(TextureSeeder::class);
        $this->seed(ColorFamilySeeder::class);
        $this->seed(FunsationalTextureSeeder::class);
        $this->seed(FunsationalBalloonSizeSeeder::class);
        $this->seed(FunsationalColorSeeder::class);

        $this->jsonPath = storage_path('app/test_funsational_skus.json');
        file_put_contents($this->jsonPath, json_encode(['skus' => $this->fixtureRows()], JSON_PRETTY_PRINT));
    }

    protected function tearDown(): void
    {
        if (file_exists($this->jsonPath)) {
            unlink($this->jsonPath);
        }

        parent::tearDown();
    }

    /** @return array<int, array<string, mixed>> */
    private function fixtureRows(): array
    {
        return [
            ['warehouse_sku' => '57085', 'upc' => '071444570855', 'color_resolved' => 'Baby Blue', 'size_resolved' => '12-inch (F)', 'count_per_bag' => 50, 'is_assortment' => false, 'name' => '12in Funsational Baby Blue 50ct'],
            ['warehouse_sku' => '57064', 'upc' => '071444570640', 'color_resolved' => 'Baby Blue', 'size_resolved' => '12-inch (F)', 'count_per_bag' => 15, 'is_assortment' => false, 'name' => '12in Funsational Baby Blue 15ct'],
            ['warehouse_sku' => '58001', 'upc' => '071444580015', 'color_resolved' => 'Pearl Mint Green', 'size_resolved' => '12-inch (F)', 'count_per_bag' => 50, 'is_assortment' => false, 'name' => '12in Funsational Pearl Mint Green'],
            ['warehouse_sku' => '57102', 'upc' => '071444571029', 'color_resolved' => 'Assortment', 'size_resolved' => '12-inch (F)', 'count_per_bag' => null, 'is_assortment' => true, 'name' => '12in Funsational Standard Assortment'],
            ['warehouse_sku' => '88888', 'upc' => '071444888887', 'color_resolved' => 'Nonexistent Shade', 'size_resolved' => '12-inch (F)', 'count_per_bag' => 50, 'is_assortment' => false, 'name' => 'unresolvable'],
        ];
    }

    private function brand(): Brand
    {
        return Brand::where('name', 'Funsational')->firstOrFail();
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->artisan('catalog:import-funsational', ['--path' => $this->jsonPath])->assertExitCode(0);

        $this->assertSame(0, Sku::where('brand_id', $this->brand()->id)->count());
    }

    public function test_execute_inserts_resolvable_skus_with_upc(): void
    {
        $this->artisan('catalog:import-funsational', ['--execute' => true, '--path' => $this->jsonPath])
            ->expectsConfirmation('There are 1 warnings. Continue anyway?', 'yes')
            ->assertExitCode(0);

        // 4 of 5 resolve (the unknown colour warns and is skipped).
        $this->assertSame(4, Sku::where('brand_id', $this->brand()->id)->count());

        $babyBlue = Sku::where('warehouse_sku', '57085')->where('brand_id', $this->brand()->id)->firstOrFail();
        $this->assertSame('071444570855', $babyBlue->upc);      // UPC set → distributor match
        $this->assertSame(50, $babyBlue->default_count_per_bag);
        $this->assertFalse((bool) $babyBlue->is_printed);
        $this->assertNotNull($babyBlue->color_id);
        $this->assertNotNull($babyBlue->balloon_size_id);
    }

    public function test_null_count_is_allowed(): void
    {
        $this->artisan('catalog:import-funsational', ['--execute' => true, '--path' => $this->jsonPath])
            ->expectsConfirmation('There are 1 warnings. Continue anyway?', 'yes');

        $assortment = Sku::where('warehouse_sku', '57102')->where('brand_id', $this->brand()->id)->firstOrFail();
        $this->assertNull($assortment->default_count_per_bag);
    }

    public function test_links_identical_pack_size_siblings(): void
    {
        $this->artisan('catalog:import-funsational', ['--execute' => true, '--path' => $this->jsonPath])
            ->expectsConfirmation('There are 1 warnings. Continue anyway?', 'yes');

        $b50 = Sku::where('warehouse_sku', '57085')->firstOrFail();
        $b15 = Sku::where('warehouse_sku', '57064')->firstOrFail();

        $this->assertTrue($b50->identicalSkus->contains($b15));
        $this->assertTrue($b15->identicalSkus->contains($b50));
    }

    public function test_is_idempotent(): void
    {
        $run = fn () => $this->artisan('catalog:import-funsational', ['--execute' => true, '--path' => $this->jsonPath])
            ->expectsConfirmation('There are 1 warnings. Continue anyway?', 'yes');

        $run();
        $run();

        $this->assertSame(4, Sku::where('brand_id', $this->brand()->id)->count());
    }
}
