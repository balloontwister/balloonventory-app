<?php

namespace Tests\Feature\Catalog;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Color;
use App\Models\PackagingType;
use App\Models\Sku;
use App\Models\Texture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillKalisanUpcsTest extends TestCase
{
    use RefreshDatabase;

    private Brand $brand;

    private PackagingType $loosePackaging;

    private Texture $standardTexture;

    private Texture $macaronTexture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->brand = Brand::factory()->create(['name' => 'Kalisan']);
        $this->loosePackaging = PackagingType::factory()->create(['name' => 'Loose']);
        PackagingType::factory()->create(['name' => 'Nozzle Up']);
        $this->standardTexture = Texture::factory()->create(['name' => 'Standard (K)']);
        $this->macaronTexture = Texture::factory()->create(['name' => 'Macaron (K)']);
        Texture::factory()->create(['name' => 'Mirror (K)']);
    }

    // ── Source file handling ─────────────────────────────────────────────────

    public function test_fails_when_source_file_missing(): void
    {
        $this->artisan('catalog:backfill-kalisan-upcs', [
            '--source' => '/nonexistent/path.json',
        ])
            ->assertFailed()
            ->expectsOutputToContain('Source file not found');
    }

    public function test_fails_when_source_file_is_empty_json(): void
    {
        $path = $this->writeTempJson([]);

        $this->artisan('catalog:backfill-kalisan-upcs', ['--source' => $path])
            ->assertFailed()
            ->expectsOutputToContain('empty or invalid JSON');
    }

    // ── Barcode validation ───────────────────────────────────────────────────

    public function test_invalid_check_digit_is_reported_and_skipped(): void
    {
        $size = BalloonSize::factory()->create(['brand_id' => $this->brand->id, 'name' => '260K']);
        $color = Color::factory()->create([
            'brand_id' => $this->brand->id,
            'texture_id' => $this->standardTexture->id,
            'name' => 'White',
        ]);
        $sku = $this->makeSku($size, $color, 100);

        $path = $this->writeTempJson([[
            'title' => '260K Standard White Balloons Kalisan 100ct',
            'barcode' => '8693296101549',   // bad check digit (last digit changed)
            'size' => '260K',
            'texture' => 'Standard (K)',
            'color' => 'White',
            'count' => 100,
            'packaging' => 'Loose',
            'parse_ok' => true,
        ]]);

        $this->artisan('catalog:backfill-kalisan-upcs', ['--source' => $path])
            ->assertSuccessful()
            ->expectsOutputToContain('invalid-barcode=1');

        $this->assertNull($sku->fresh()->ean);
    }

    // ── Warehouse SKU match ─────────────────────────────────────────────────

    public function test_warehouse_sku_match_writes_ean(): void
    {
        $size = BalloonSize::factory()->create(['brand_id' => $this->brand->id, 'name' => '260K']);
        $color = Color::factory()->create([
            'brand_id' => $this->brand->id,
            'texture_id' => $this->macaronTexture->id,
            'name' => 'Macaron Lilac',
        ]);
        $sku = $this->makeSku($size, $color, 100, warehouseSku: '10230031');

        // Real valid EAN-13 from spec: 8693296864283
        $path = $this->writeTempJson([[
            'title' => '260K Macaron Lilac Balloons Kalisan 100ct',
            'barcode' => '8693296864283',
            'size' => '260K',
            'texture' => 'Macaron (K)',
            'color' => 'Lilac',
            'count' => 100,
            'packaging' => 'Loose',
            'warehouse_sku' => '10230031',
            'parse_ok' => true,
        ]]);

        $this->artisan('catalog:backfill-kalisan-upcs', [
            '--source' => $path,
            '--apply' => true,
        ])->assertSuccessful()->expectsOutputToContain('Applied 1');

        $this->assertSame('8693296864283', $sku->fresh()->ean);
    }

    // ── 12-digit barcode routes to upc ──────────────────────────────────────

    public function test_twelve_digit_barcode_routes_to_upc(): void
    {
        $size = BalloonSize::factory()->create(['brand_id' => $this->brand->id, 'name' => '260K']);
        $color = Color::factory()->create([
            'brand_id' => $this->brand->id,
            'texture_id' => $this->standardTexture->id,
            'name' => 'White',
        ]);
        $sku = $this->makeSku($size, $color, 100);

        // Use a valid 12-digit UPC: 030625510028 (Sempertex UPC — still valid GTIN)
        $path = $this->writeTempJson([[
            'title' => '260K Standard White Balloons Kalisan 100ct',
            'barcode' => '030625510028',
            'size' => '260K',
            'texture' => 'Standard (K)',
            'color' => 'White',
            'count' => 100,
            'packaging' => 'Loose',
            'parse_ok' => true,
        ]]);

        $this->artisan('catalog:backfill-kalisan-upcs', [
            '--source' => $path,
            '--apply' => true,
        ])->assertSuccessful()->expectsOutputToContain('Applied 1');

        $this->assertSame('030625510028', $sku->fresh()->upc);
        $this->assertNull($sku->fresh()->ean);
    }

    // ── Attribute match ─────────────────────────────────────────────────────

    public function test_resolves_by_attributes_and_matches_sku_in_dry_run(): void
    {
        $size = BalloonSize::factory()->create(['brand_id' => $this->brand->id, 'name' => '260K']);
        $color = Color::factory()->create([
            'brand_id' => $this->brand->id,
            'texture_id' => $this->macaronTexture->id,
            'name' => 'Macaron Lilac',
        ]);
        $sku = $this->makeSku($size, $color, 100);

        $path = $this->writeTempJson([[
            'title' => '260K Macaron Lilac Balloons Kalisan 100ct',
            'barcode' => '8693296864283',
            'size' => '260K',
            'texture' => 'Macaron (K)',
            'color' => 'Lilac',
            'count' => 100,
            'packaging' => 'Loose',
            'warehouse_sku' => null,
            'parse_ok' => true,
        ]]);

        $this->artisan('catalog:backfill-kalisan-upcs', ['--source' => $path])
            ->assertSuccessful()
            ->expectsOutputToContain('matched=1');

        // Dry-run: no DB write
        $this->assertNull($sku->fresh()->ean);
    }

    public function test_apply_flag_writes_ean_to_database(): void
    {
        $size = BalloonSize::factory()->create(['brand_id' => $this->brand->id, 'name' => '260K']);
        $color = Color::factory()->create([
            'brand_id' => $this->brand->id,
            'texture_id' => $this->macaronTexture->id,
            'name' => 'Macaron Blue',
        ]);
        $sku = $this->makeSku($size, $color, 100);

        $path = $this->writeTempJson([[
            'title' => '260K Macaron Blue Balloons Kalisan 100ct',
            'barcode' => '8693296101036',
            'size' => '260K',
            'texture' => 'Macaron (K)',
            'color' => 'Blue',
            'count' => 100,
            'packaging' => 'Loose',
            'parse_ok' => true,
        ]]);

        $this->artisan('catalog:backfill-kalisan-upcs', [
            '--source' => $path,
            '--apply' => true,
        ])->assertSuccessful()->expectsOutputToContain('Applied 1');

        $this->assertSame('8693296101036', $sku->fresh()->ean);
    }

    // ── Standard-texture colour resolution ──────────────────────────────────

    public function test_resolves_standard_texture_color_without_prefix(): void
    {
        $size = BalloonSize::factory()->create(['brand_id' => $this->brand->id, 'name' => '260K']);
        $color = Color::factory()->create([
            'brand_id' => $this->brand->id,
            'texture_id' => $this->standardTexture->id,
            'name' => 'White',   // Standard colours have NO "Standard" prefix
        ]);
        $sku = $this->makeSku($size, $color, 100);

        $path = $this->writeTempJson([[
            'title' => '260K Standard White Balloons Kalisan 100ct',
            'barcode' => '8693296101548',
            'size' => '260K',
            'texture' => 'Standard (K)',
            'color' => 'White',
            'count' => 100,
            'packaging' => 'Loose',
            'parse_ok' => true,
        ]]);

        $this->artisan('catalog:backfill-kalisan-upcs', [
            '--source' => $path,
            '--apply' => true,
        ])->assertSuccessful()->expectsOutputToContain('Applied 1');

        $this->assertSame('8693296101548', $sku->fresh()->ean);
    }

    // ── Duplicate barcode guard ──────────────────────────────────────────────

    public function test_duplicate_barcode_on_another_sku_is_skipped(): void
    {
        $size = BalloonSize::factory()->create(['brand_id' => $this->brand->id, 'name' => '260K']);
        $colorWhite = Color::factory()->create([
            'brand_id' => $this->brand->id,
            'texture_id' => $this->standardTexture->id,
            'name' => 'White',
        ]);
        $colorBlack = Color::factory()->create([
            'brand_id' => $this->brand->id,
            'texture_id' => $this->standardTexture->id,
            'name' => 'Black',
        ]);

        // Another SKU already has this EAN
        $otherSku = $this->makeSku($size, $colorBlack, 100, ean: '8693296864283');
        $targetSku = $this->makeSku($size, $colorWhite, 100);

        $path = $this->writeTempJson([[
            'title' => '260K Standard White Balloons Kalisan 100ct',
            'barcode' => '8693296864283',
            'size' => '260K',
            'texture' => 'Standard (K)',
            'color' => 'White',
            'count' => 100,
            'packaging' => 'Loose',
            'parse_ok' => true,
        ]]);

        $this->artisan('catalog:backfill-kalisan-upcs', [
            '--source' => $path,
            '--apply' => true,
        ])->assertSuccessful()->expectsOutputToContain('duplicate-code=1');

        $this->assertNull($targetSku->fresh()->ean);
    }

    // ── Already-set barcode ──────────────────────────────────────────────────

    public function test_already_set_same_barcode_is_reported_not_re_applied(): void
    {
        $size = BalloonSize::factory()->create(['brand_id' => $this->brand->id, 'name' => '260K']);
        $color = Color::factory()->create([
            'brand_id' => $this->brand->id,
            'texture_id' => $this->macaronTexture->id,
            'name' => 'Macaron Lilac',
        ]);
        $sku = $this->makeSku($size, $color, 100, ean: '8693296864283');

        $path = $this->writeTempJson([[
            'title' => '260K Macaron Lilac Balloons Kalisan 100ct',
            'barcode' => '8693296864283',
            'size' => '260K',
            'texture' => 'Macaron (K)',
            'color' => 'Lilac',
            'count' => 100,
            'packaging' => 'Loose',
            'parse_ok' => true,
        ]]);

        $this->artisan('catalog:backfill-kalisan-upcs', [
            '--source' => $path,
            '--apply' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('already-set=1');

        $this->assertSame('8693296864283', $sku->fresh()->ean);
    }

    // ── Parse fail ───────────────────────────────────────────────────────────

    public function test_parse_fail_is_reported(): void
    {
        $path = $this->writeTempJson([[
            'title' => 'Kalisan Color Book',
            'barcode' => '8693296841833',
            'size' => null,
            'texture' => null,
            'color' => null,
            'count' => null,
            'packaging' => 'Loose',
            'parse_ok' => false,
        ]]);

        $this->artisan('catalog:backfill-kalisan-upcs', ['--source' => $path])
            ->assertSuccessful()
            ->expectsOutputToContain('parse-fail=1');
    }

    // ── Multiple entries ─────────────────────────────────────────────────────

    public function test_multiple_entries_applied_in_single_run(): void
    {
        $size260K = BalloonSize::factory()->create(['brand_id' => $this->brand->id, 'name' => '260K']);
        $size160K = BalloonSize::factory()->create(['brand_id' => $this->brand->id, 'name' => '160K']);

        $colorLilac = Color::factory()->create([
            'brand_id' => $this->brand->id,
            'texture_id' => $this->macaronTexture->id,
            'name' => 'Macaron Lilac',
        ]);
        $colorWhite = Color::factory()->create([
            'brand_id' => $this->brand->id,
            'texture_id' => $this->standardTexture->id,
            'name' => 'White',
        ]);

        $skuA = $this->makeSku($size260K, $colorLilac, 100);
        $skuB = $this->makeSku($size160K, $colorWhite, 100);

        $path = $this->writeTempJson([
            [
                'title' => '260K Macaron Lilac Balloons Kalisan 100ct',
                'barcode' => '8693296864283',
                'size' => '260K',
                'texture' => 'Macaron (K)',
                'color' => 'Lilac',
                'count' => 100,
                'packaging' => 'Loose',
                'parse_ok' => true,
            ],
            [
                'title' => '160K Standard White Balloons Kalisan 100ct',
                'barcode' => '8693296101548',
                'size' => '160K',
                'texture' => 'Standard (K)',
                'color' => 'White',
                'count' => 100,
                'packaging' => 'Loose',
                'parse_ok' => true,
            ],
        ]);

        $this->artisan('catalog:backfill-kalisan-upcs', [
            '--source' => $path,
            '--apply' => true,
        ])->assertSuccessful()->expectsOutputToContain('Applied 2');

        $this->assertSame('8693296864283', $skuA->fresh()->ean);
        $this->assertSame('8693296101548', $skuB->fresh()->ean);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeSku(
        BalloonSize $size,
        Color $color,
        int $count,
        ?string $upc = null,
        ?string $ean = null,
        ?string $warehouseSku = null,
    ): Sku {
        return Sku::factory()->create([
            'brand_id' => $this->brand->id,
            'balloon_size_id' => $size->id,
            'color_id' => $color->id,
            'packaging_id' => $this->loosePackaging->id,
            'default_count_per_bag' => $count,
            'upc' => $upc,
            'ean' => $ean,
            'warehouse_sku' => $warehouseSku,
        ]);
    }

    /** @param array<int, array<string, mixed>> $data */
    private function writeTempJson(array $data): string
    {
        $path = tempnam(sys_get_temp_dir(), 'kalisan_upcs_').'.json';
        file_put_contents($path, json_encode($data));

        return $path;
    }
}
