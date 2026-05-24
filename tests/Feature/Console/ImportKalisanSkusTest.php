<?php

namespace Tests\Feature\Console;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\PackagingType;
use App\Models\Sku;
use Database\Seeders\BrandSeeder;
use Database\Seeders\ColorFamilySeeder;
use Database\Seeders\KalisanBalloonSizeSeeder;
use Database\Seeders\KalisanColorSeeder;
use Database\Seeders\KalisanTextureSeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\PackagingTypeSeeder;
use Database\Seeders\ShapeSeeder;
use Database\Seeders\SizeSeeder;
use Database\Seeders\TextureFamilySeeder;
use Database\Seeders\TextureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportKalisanSkusTest extends TestCase
{
    use RefreshDatabase;

    private string $jsonPath;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Http::fake(['*' => Http::response('fake-png-bytes', 200, ['Content-Type' => 'image/png'])]);

        $this->seed(BrandSeeder::class);
        $this->seed(MaterialSeeder::class);
        $this->seed(ShapeSeeder::class);
        $this->seed(SizeSeeder::class);
        $this->seed(PackagingTypeSeeder::class);
        $this->seed(TextureFamilySeeder::class);
        $this->seed(TextureSeeder::class);
        $this->seed(ColorFamilySeeder::class);
        $this->seed(KalisanTextureSeeder::class);
        $this->seed(KalisanBalloonSizeSeeder::class);
        $this->seed(KalisanColorSeeder::class);

        $this->jsonPath = storage_path('app/test_kalisan_normalized.json');
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
     * @return array<int, array<string, mixed>>
     */
    private function fixtureRows(): array
    {
        return [
            // Standard solid color
            ['warehouse_sku' => '10123121', 'raw_name' => '160M Standard White - 100CT', 'size_resolved' => '160K', 'texture_resolved' => 'Standard (K)', 'color_resolved' => 'White', 'packaging' => 'Loose', 'count_per_bag' => 100, 'is_assortment' => false],
            // Same physical balloon in smaller bag, + Nozzle Up variant — these three should link via identical_skus.
            ['warehouse_sku' => '10123122', 'raw_name' => '160M Standard White - 50CT', 'size_resolved' => '160K', 'texture_resolved' => 'Standard (K)', 'color_resolved' => 'White', 'packaging' => 'Loose', 'count_per_bag' => 50, 'is_assortment' => false],
            ['warehouse_sku' => '10123125', 'raw_name' => '160M Standard White - 50CT Nozzle Up', 'size_resolved' => '160K', 'texture_resolved' => 'Standard (K)', 'color_resolved' => 'White', 'packaging' => 'Nozzle Up', 'count_per_bag' => 50, 'is_assortment' => false],
            // Standard assortment
            ['warehouse_sku' => '10123081', 'raw_name' => '160M Standard Carnival Assortment - 100CT', 'size_resolved' => '160K', 'texture_resolved' => 'Standard (K)', 'color_resolved' => 'Standard Carnival Assortment', 'packaging' => 'Loose', 'count_per_bag' => 100, 'is_assortment' => true],
            // Macaron
            ['warehouse_sku' => '11230012', 'raw_name' => '12" Macaron Blue - 50CT', 'size_resolved' => '12-inch (K)', 'texture_resolved' => 'Macaron (K)', 'color_resolved' => 'Macaron Blue', 'packaging' => 'Loose', 'count_per_bag' => 50, 'is_assortment' => false],
            // Mirror
            ['warehouse_sku' => '11250012', 'raw_name' => '12" Mirror Gold - 50CT', 'size_resolved' => '12-inch (K)', 'texture_resolved' => 'Mirror (K)', 'color_resolved' => 'Mirror Gold', 'packaging' => 'Loose', 'count_per_bag' => 50, 'is_assortment' => false],
            // Retro
            ['warehouse_sku' => '10580092', 'raw_name' => '5" Retro Olive - 100CT', 'size_resolved' => '5-inch (K)', 'texture_resolved' => 'Retro (K)', 'color_resolved' => 'Retro Olive', 'packaging' => 'Loose', 'count_per_bag' => 100, 'is_assortment' => false],
            // Aura
            ['warehouse_sku' => '12470822', 'raw_name' => '24" Aura Ice Mint - 2CT', 'size_resolved' => '24-inch (K)', 'texture_resolved' => 'Aura (K)', 'color_resolved' => 'Aura Ice Mint', 'packaging' => 'Loose', 'count_per_bag' => 2, 'is_assortment' => false],
            // K-Link 12"
            ['warehouse_sku' => '31223122', 'raw_name' => '12" K-Link Standard White 50CT', 'size_resolved' => '12" K-Link', 'texture_resolved' => 'Standard (K)', 'color_resolved' => 'White', 'packaging' => 'Loose', 'count_per_bag' => 50, 'is_assortment' => false],
            // Heart 12"
            ['warehouse_sku' => '11323123', 'raw_name' => '12" Heart Standard White - 25CT', 'size_resolved' => '12-inch', 'texture_resolved' => 'Standard (K)', 'color_resolved' => 'White', 'packaging' => 'Loose', 'count_per_bag' => 25, 'is_assortment' => false],
            // 11" routed to 12-inch (K)
            ['warehouse_sku' => '11170022', 'raw_name' => '11" Metallic Gold - 50CT', 'size_resolved' => '12-inch (K)', 'texture_resolved' => 'Metallic (K)', 'color_resolved' => 'Metallic Gold', 'packaging' => 'Loose', 'count_per_bag' => 50, 'is_assortment' => false],
            // Crystal "Assortment" typo normalized to Crystal Assorted at normalize-time
            ['warehouse_sku' => '10260001', 'raw_name' => '260M Crystal Assortment - 100CT', 'size_resolved' => '260K', 'texture_resolved' => 'Crystal (K)', 'color_resolved' => 'Crystal Assorted', 'packaging' => 'Loose', 'count_per_bag' => 100, 'is_assortment' => true],
        ];
    }

    public function test_dry_run_reports_inserts_without_writing(): void
    {
        $this->assertSame(0, Sku::where('brand_id', $this->kalisan()->id)->count());

        $this->artisan('catalog:import-kalisan', ['--path' => $this->jsonPath])
            ->expectsOutputToContain('Inserts: 12')
            ->assertSuccessful();

        $this->assertSame(0, Sku::where('brand_id', $this->kalisan()->id)->count());
    }

    public function test_execute_writes_all_rows(): void
    {
        $this->artisan('catalog:import-kalisan', ['--path' => $this->jsonPath, '--execute' => true])
            ->assertSuccessful();

        $this->assertSame(12, Sku::where('brand_id', $this->kalisan()->id)->count());
    }

    public function test_packaging_and_count_are_mapped(): void
    {
        $this->artisan('catalog:import-kalisan', ['--path' => $this->jsonPath, '--execute' => true])
            ->assertSuccessful();

        $nozzleUp = PackagingType::where('name', 'Nozzle Up')->firstOrFail();
        $loose = PackagingType::where('name', 'Loose')->firstOrFail();

        $sku = Sku::where('warehouse_sku', '10123125')->firstOrFail();
        $this->assertSame($nozzleUp->id, $sku->packaging_id);
        $this->assertSame(50, (int) $sku->default_count_per_bag);

        $sku = Sku::where('warehouse_sku', '10123121')->firstOrFail();
        $this->assertSame($loose->id, $sku->packaging_id);
        $this->assertSame(100, (int) $sku->default_count_per_bag);
    }

    public function test_11_inch_skus_fk_to_12_inch_kalisan(): void
    {
        $this->artisan('catalog:import-kalisan', ['--path' => $this->jsonPath, '--execute' => true])
            ->assertSuccessful();

        $sku = Sku::where('warehouse_sku', '11170022')->firstOrFail();
        $size = BalloonSize::where('name', '12-inch (K)')->where('brand_id', $this->kalisan()->id)->firstOrFail();

        $this->assertSame($size->id, $sku->balloon_size_id);
        $this->assertSame('11" Metallic Gold - 50CT', $sku->name, 'Raw spreadsheet name preserved');
    }

    public function test_heart_and_k_link_resolve_to_their_shape_specific_sizes(): void
    {
        $this->artisan('catalog:import-kalisan', ['--path' => $this->jsonPath, '--execute' => true])
            ->assertSuccessful();

        $heart = BalloonSize::where('name', '12-inch')->where('brand_id', $this->kalisan()->id)->firstOrFail();
        $kLink = BalloonSize::where('name', '12" K-Link')->where('brand_id', $this->kalisan()->id)->firstOrFail();

        $this->assertSame($heart->id, Sku::where('warehouse_sku', '11323123')->firstOrFail()->balloon_size_id);
        $this->assertSame($kLink->id, Sku::where('warehouse_sku', '31223122')->firstOrFail()->balloon_size_id);
    }

    public function test_identical_skus_pivot_links_variants_of_same_balloon(): void
    {
        $this->artisan('catalog:import-kalisan', ['--path' => $this->jsonPath, '--execute' => true])
            ->assertSuccessful();

        $a = Sku::where('warehouse_sku', '10123121')->firstOrFail();
        $b = Sku::where('warehouse_sku', '10123122')->firstOrFail();
        $c = Sku::where('warehouse_sku', '10123125')->firstOrFail();

        $aSiblings = $a->identicalSkus()->pluck('skus.id')->all();
        sort($aSiblings);
        $expected = [$b->id, $c->id];
        sort($expected);

        $this->assertSame($expected, $aSiblings, 'SKU A should link to B and C');
        $this->assertTrue($b->identicalSkus()->where('skus.id', $a->id)->exists(), 'B → A reciprocal link');
        $this->assertTrue($c->identicalSkus()->where('skus.id', $a->id)->exists(), 'C → A reciprocal link');
    }

    public function test_execute_is_idempotent(): void
    {
        $this->artisan('catalog:import-kalisan', ['--path' => $this->jsonPath, '--execute' => true])->assertSuccessful();
        $this->artisan('catalog:import-kalisan', ['--path' => $this->jsonPath, '--execute' => true])->assertSuccessful();

        $this->assertSame(12, Sku::where('brand_id', $this->kalisan()->id)->count());

        $a = Sku::where('warehouse_sku', '10123121')->firstOrFail();
        $this->assertSame(2, $a->identicalSkus()->count(), 'No duplicate pivot rows after second run');
    }

    public function test_unresolved_color_is_warned_and_skipped(): void
    {
        $rows = $this->fixtureRows();
        $rows[] = [
            'warehouse_sku' => '99999999', 'raw_name' => '12" Standard Unicorn - 50CT',
            'size_resolved' => '12-inch (K)', 'texture_resolved' => 'Standard (K)',
            'color_resolved' => 'Unicorn', 'packaging' => 'Loose', 'count_per_bag' => 50, 'is_assortment' => false,
        ];
        file_put_contents($this->jsonPath, json_encode($rows));

        $this->artisan('catalog:import-kalisan', ['--path' => $this->jsonPath, '--execute' => true])
            ->expectsOutputToContain("Color not found 'Unicorn'")
            ->expectsConfirmation('There are 1 warnings. Continue anyway?', 'yes')
            ->assertSuccessful();

        $this->assertNull(Sku::where('warehouse_sku', '99999999')->first());
        $this->assertSame(12, Sku::where('brand_id', $this->kalisan()->id)->count());
    }

    private function kalisan(): Brand
    {
        return Brand::where('name', 'Kalisan')->firstOrFail();
    }
}
