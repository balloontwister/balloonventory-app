<?php

namespace Tests\Feature\Catalog;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Color;
use App\Models\PackagingType;
use App\Models\Sku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillSempertexUpcsTest extends TestCase
{
    use RefreshDatabase;

    private Brand $brand;

    private PackagingType $loosePackaging;

    protected function setUp(): void
    {
        parent::setUp();

        $this->brand = Brand::factory()->create(['name' => 'Sempertex']);
        $this->loosePackaging = PackagingType::factory()->create(['name' => 'Loose']);
    }

    // ── Source file handling ─────────────────────────────────────────────────

    public function test_fails_when_source_file_missing(): void
    {
        $this->artisan('catalog:backfill-sempertex-upcs', [
            '--source' => '/nonexistent/path.json',
        ])
            ->assertFailed()
            ->expectsOutputToContain('Source file not found');
    }

    public function test_fails_when_source_file_is_empty_json(): void
    {
        $path = $this->writeTempJson([]);

        $this->artisan('catalog:backfill-sempertex-upcs', ['--source' => $path])
            ->assertFailed()
            ->expectsOutputToContain('empty or invalid JSON');
    }

    // ── UPC validation ───────────────────────────────────────────────────────

    public function test_invalid_upc_is_reported_and_skipped(): void
    {
        $size = BalloonSize::factory()->create(['brand_id' => $this->brand->id, 'name' => 'R-5']);
        $color = Color::factory()->create(['brand_id' => $this->brand->id, 'name' => 'Fashion White']);
        $sku = $this->makeSku($size, $color, 50);

        $path = $this->writeTempJson([[
            'raw_title' => 'Sempertex 5 Round Fashion White',
            'color_slug' => null,
            'size' => 'R-5',
            'count' => 50,
            'packaging' => 'Loose',
            'upc' => '030625510099',   // bad check digit
            'source' => 'betallic-us',
        ]]);

        $this->artisan('catalog:backfill-sempertex-upcs', ['--source' => $path])
            ->assertSuccessful()
            ->expectsOutputToContain('invalid-upc=1');

        $this->assertNull($sku->fresh()->upc);
    }

    // ── Color resolution via slug ────────────────────────────────────────────

    public function test_resolves_color_by_slug_and_matches_sku_in_dry_run(): void
    {
        $size = BalloonSize::factory()->create(['brand_id' => $this->brand->id, 'name' => '260-S']);
        $color = Color::factory()->create(['brand_id' => $this->brand->id, 'name' => 'Fashion Pink']);
        $sku = $this->makeSku($size, $color, 50);

        $path = $this->writeTempJson([[
            'raw_title' => 'Sempertex 260 Twisting Balloon Fashion Pink',
            'color_slug' => 'fashion-pink',
            'size' => '260-S',
            'count' => 50,
            'packaging' => 'Loose',
            'upc' => '030625590020',
            'source' => 'larock-old',
        ]]);

        $this->artisan('catalog:backfill-sempertex-upcs', ['--source' => $path])
            ->assertSuccessful()
            ->expectsOutputToContain('matched=1');

        // Dry-run: no DB write
        $this->assertNull($sku->fresh()->upc);
    }

    public function test_apply_flag_writes_upc_to_database(): void
    {
        $size = BalloonSize::factory()->create(['brand_id' => $this->brand->id, 'name' => '260-S']);
        $color = Color::factory()->create(['brand_id' => $this->brand->id, 'name' => 'Fashion Pink']);
        $sku = $this->makeSku($size, $color, 50);

        $path = $this->writeTempJson([[
            'raw_title' => 'Sempertex 260 Twisting Balloon Fashion Pink',
            'color_slug' => 'fashion-pink',
            'size' => '260-S',
            'count' => 50,
            'packaging' => 'Loose',
            'upc' => '030625590020',
            'source' => 'larock-old',
        ]]);

        $this->artisan('catalog:backfill-sempertex-upcs', [
            '--source' => $path,
            '--apply' => true,
        ])->assertSuccessful()->expectsOutputToContain('Applied 1');

        $this->assertSame('030625590020', $sku->fresh()->upc);
    }

    // ── Color slug aliases ───────────────────────────────────────────────────

    public function test_color_slug_alias_deluxe_gray_maps_to_deluxe_grey(): void
    {
        $size = BalloonSize::factory()->create(['brand_id' => $this->brand->id, 'name' => 'R-5']);
        $color = Color::factory()->create(['brand_id' => $this->brand->id, 'name' => 'Deluxe Grey']);
        $sku = $this->makeSku($size, $color, 50);

        $path = $this->writeTempJson([[
            'raw_title' => 'Sempertex 5 Round Deluxe Gray',
            'color_slug' => 'deluxe-gray',          // Larock uses US spelling
            'size' => 'R-5',
            'count' => 50,
            'packaging' => 'Loose',
            'upc' => '030625510028',
            'source' => 'larock-old',
        ]]);

        $this->artisan('catalog:backfill-sempertex-upcs', [
            '--source' => $path,
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame('030625510028', $sku->fresh()->upc);
    }

    // ── Betallic US raw_title substring matching ─────────────────────────────

    public function test_resolves_color_from_raw_title_substring_match(): void
    {
        $size = BalloonSize::factory()->create(['brand_id' => $this->brand->id, 'name' => 'R-5']);
        $color = Color::factory()->create(['brand_id' => $this->brand->id, 'name' => 'Fashion White']);
        $sku = $this->makeSku($size, $color, 50);

        $path = $this->writeTempJson([[
            'raw_title' => 'Sempertex-USA 5 Round Fashion White 50 Count',
            'color_slug' => null,             // Betallic US — no slug
            'size' => 'R-5',
            'count' => 50,
            'packaging' => 'Loose',
            'upc' => '030625510028',
            'source' => 'betallic-us',
        ]]);

        $this->artisan('catalog:backfill-sempertex-upcs', [
            '--source' => $path,
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame('030625510028', $sku->fresh()->upc);
    }

    public function test_substring_match_prefers_longer_color_name(): void
    {
        $size = BalloonSize::factory()->create(['brand_id' => $this->brand->id, 'name' => 'R-5']);
        // Create both so the shorter name would match if checked first
        Color::factory()->create(['brand_id' => $this->brand->id, 'name' => 'Fashion Blue']);
        $longer = Color::factory()->create(['brand_id' => $this->brand->id, 'name' => 'Fashion Caribbean Blue']);
        $sku = $this->makeSku($size, $longer, 50);

        $path = $this->writeTempJson([[
            'raw_title' => 'Sempertex 5 Round Fashion Caribbean Blue 50ct',
            'color_slug' => null,
            'size' => 'R-5',
            'count' => 50,
            'packaging' => 'Loose',
            'upc' => '030625510028',
            'source' => 'betallic-us',
        ]]);

        $this->artisan('catalog:backfill-sempertex-upcs', [
            '--source' => $path,
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame('030625510028', $sku->fresh()->upc);
    }

    // ── Betallatex suffix stripping ──────────────────────────────────────────

    public function test_strips_betallatex_suffix_from_color_slug(): void
    {
        $size = BalloonSize::factory()->create(['brand_id' => $this->brand->id, 'name' => 'R-5']);
        $color = Color::factory()->create(['brand_id' => $this->brand->id, 'name' => 'Deluxe Imperial Red']);
        $sku = $this->makeSku($size, $color, 50);

        $path = $this->writeTempJson([[
            'raw_title' => 'Sempertex 5 Round Deluxe Imperial Red',
            'color_slug' => 'deluxe-imperial-red-betallatex',
            'size' => 'R-5',
            'count' => 50,
            'packaging' => 'Loose',
            'upc' => '030625510028',
            'source' => 'larock-old',
        ]]);

        $this->artisan('catalog:backfill-sempertex-upcs', [
            '--source' => $path,
            '--apply' => true,
        ])->assertSuccessful()->expectsOutputToContain('Applied 1');

        $this->assertSame('030625510028', $sku->fresh()->upc);
    }

    public function test_strips_link_o_loon_betallatex_suffix_from_color_slug(): void
    {
        $size = BalloonSize::factory()->create(['brand_id' => $this->brand->id, 'name' => 'LOL-12']);
        $color = Color::factory()->create(['brand_id' => $this->brand->id, 'name' => 'Pastel Matte Green']);
        $sku = $this->makeSku($size, $color, 50);

        $path = $this->writeTempJson([[
            'raw_title' => '12 Inch Pastel Matte Green Link-O-Loon',
            'color_slug' => 'pastel-matte-green-link-o-loon-betallatex',
            'size' => 'LOL-12',
            'count' => 50,
            'packaging' => 'Loose',
            'upc' => '030625510028',
            'source' => 'larock-old',
        ]]);

        $this->artisan('catalog:backfill-sempertex-upcs', [
            '--source' => $path,
            '--apply' => true,
        ])->assertSuccessful()->expectsOutputToContain('Applied 1');

        $this->assertSame('030625510028', $sku->fresh()->upc);
    }

    public function test_strips_link_o_loon_betallatex_with_trailing_digit_from_color_slug(): void
    {
        $size = BalloonSize::factory()->create(['brand_id' => $this->brand->id, 'name' => 'LOL-12']);
        $color = Color::factory()->create(['brand_id' => $this->brand->id, 'name' => 'Pastel Matte Blue']);
        $sku = $this->makeSku($size, $color, 50);

        $path = $this->writeTempJson([[
            'raw_title' => '12 Inch Pastel Matte Blue Link-O-Loon',
            'color_slug' => 'pastel-matte-blue-link-o-loon-betallatex-5',
            'size' => 'LOL-12',
            'count' => 50,
            'packaging' => 'Loose',
            'upc' => '030625510028',
            'source' => 'larock-old',
        ]]);

        $this->artisan('catalog:backfill-sempertex-upcs', [
            '--source' => $path,
            '--apply' => true,
        ])->assertSuccessful()->expectsOutputToContain('Applied 1');

        $this->assertSame('030625510028', $sku->fresh()->upc);
    }

    // ── No-match cases ───────────────────────────────────────────────────────

    public function test_no_color_match_is_reported(): void
    {
        BalloonSize::factory()->create(['brand_id' => $this->brand->id, 'name' => 'R-5']);

        $path = $this->writeTempJson([[
            'raw_title' => 'Sempertex 5 Round Pearl Fantasy',
            'color_slug' => 'pearl-fantasy',    // not in DB
            'size' => 'R-5',
            'count' => 50,
            'packaging' => 'Loose',
            'upc' => '030625510028',
            'source' => 'larock-old',
        ]]);

        $this->artisan('catalog:backfill-sempertex-upcs', ['--source' => $path])
            ->assertSuccessful()
            ->expectsOutputToContain('no-color=1');
    }

    public function test_no_sku_match_is_reported_when_count_differs(): void
    {
        $size = BalloonSize::factory()->create(['brand_id' => $this->brand->id, 'name' => 'R-5']);
        $color = Color::factory()->create(['brand_id' => $this->brand->id, 'name' => 'Fashion White']);
        // SKU exists but for 12CT, not 50CT
        $this->makeSku($size, $color, 12);

        $path = $this->writeTempJson([[
            'raw_title' => 'Sempertex 5 Round Fashion White',
            'color_slug' => 'fashion-white',
            'size' => 'R-5',
            'count' => 50,                // 50CT not in DB
            'packaging' => 'Loose',
            'upc' => '030625510028',
            'source' => 'betallic-us',
        ]]);

        $this->artisan('catalog:backfill-sempertex-upcs', ['--source' => $path])
            ->assertSuccessful()
            ->expectsOutputToContain('no-sku=1');
    }

    // ── Already-set UPC ──────────────────────────────────────────────────────

    public function test_already_set_same_upc_is_reported_not_re_applied(): void
    {
        $size = BalloonSize::factory()->create(['brand_id' => $this->brand->id, 'name' => 'R-5']);
        $color = Color::factory()->create(['brand_id' => $this->brand->id, 'name' => 'Fashion White']);
        $sku = $this->makeSku($size, $color, 50, '030625510028');

        $path = $this->writeTempJson([[
            'raw_title' => 'Sempertex 5 Round Fashion White',
            'color_slug' => 'fashion-white',
            'size' => 'R-5',
            'count' => 50,
            'packaging' => 'Loose',
            'upc' => '030625510028',
            'source' => 'betallic-us',
        ]]);

        $this->artisan('catalog:backfill-sempertex-upcs', [
            '--source' => $path,
            '--apply' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('already-set=1');

        $this->assertSame('030625510028', $sku->fresh()->upc);
    }

    // ── Multiple entries ─────────────────────────────────────────────────────

    public function test_multiple_entries_applied_in_single_run(): void
    {
        $sizeR5 = BalloonSize::factory()->create(['brand_id' => $this->brand->id, 'name' => 'R-5']);
        $sizeR18 = BalloonSize::factory()->create(['brand_id' => $this->brand->id, 'name' => 'R-18']);
        $colorWhite = Color::factory()->create(['brand_id' => $this->brand->id, 'name' => 'Fashion White']);
        $colorRed = Color::factory()->create(['brand_id' => $this->brand->id, 'name' => 'Fashion Red']);
        $skuA = $this->makeSku($sizeR5, $colorWhite, 50);
        $skuB = $this->makeSku($sizeR18, $colorRed, 6);

        $path = $this->writeTempJson([
            [
                'raw_title' => 'Sempertex 5 Round Fashion White',
                'color_slug' => 'fashion-white',
                'size' => 'R-5',
                'count' => 50,
                'packaging' => 'Loose',
                'upc' => '030625510028',
                'source' => 'betallic-us',
            ],
            [
                'raw_title' => 'Sempertex 18 Round Fashion Red',
                'color_slug' => 'fashion-red',
                'size' => 'R-18',
                'count' => 6,
                'packaging' => 'Loose',
                'upc' => '030625550024',
                'source' => 'betallic-us',
            ],
        ]);

        $this->artisan('catalog:backfill-sempertex-upcs', [
            '--source' => $path,
            '--apply' => true,
        ])->assertSuccessful()->expectsOutputToContain('Applied 2');

        $this->assertSame('030625510028', $skuA->fresh()->upc);
        $this->assertSame('030625550024', $skuB->fresh()->upc);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeSku(
        BalloonSize $size,
        Color $color,
        int $count,
        ?string $upc = null,
    ): Sku {
        return Sku::factory()->create([
            'brand_id' => $this->brand->id,
            'balloon_size_id' => $size->id,
            'color_id' => $color->id,
            'packaging_id' => $this->loosePackaging->id,
            'default_count_per_bag' => $count,
            'upc' => $upc,
        ]);
    }

    /** @param array<int, array<string, mixed>> $data */
    private function writeTempJson(array $data): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sempertex_upcs_').'.json';
        file_put_contents($path, json_encode($data));

        return $path;
    }
}
