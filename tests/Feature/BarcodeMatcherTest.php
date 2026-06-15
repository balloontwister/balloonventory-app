<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\BrandGs1Prefix;
use App\Models\Business;
use App\Models\Sku;
use App\Services\BarcodeMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BarcodeMatcherTest extends TestCase
{
    use RefreshDatabase;

    private BarcodeMatcher $matcher;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->matcher = new BarcodeMatcher;
        $this->business = Business::factory()->create();
    }

    // ── gtin_exact ───────────────────────────────────────────────────────────────

    public function test_gtin_exact_match_on_stored_upc(): void
    {
        $sku = Sku::factory()->create(['upc' => '012345678905']);

        $result = $this->matcher->match('012345678905', $this->business->id);

        $this->assertSame('00012345678905', $result['gtin14']);
        $this->assertTrue($result['is_valid_gtin']);
        $this->assertCount(1, $result['candidates']);
        $this->assertSame($sku->id, $result['candidates'][0]['sku']->id);
        $this->assertSame(BarcodeMatcher::MATCH_GTIN_EXACT, $result['candidates'][0]['match']);
        $this->assertSame(100, $result['candidates'][0]['score']);
    }

    public function test_gtin_exact_match_on_stored_ean(): void
    {
        $sku = Sku::factory()->create([
            'upc' => null,
            'ean' => '4006381333931',
        ]);

        $result = $this->matcher->match('4006381333931', $this->business->id);

        $this->assertCount(1, $result['candidates']);
        $this->assertSame($sku->id, $result['candidates'][0]['sku']->id);
        $this->assertSame(BarcodeMatcher::MATCH_GTIN_EXACT, $result['candidates'][0]['match']);
    }

    public function test_strips_separators_and_whitespace_before_matching(): void
    {
        $sku = Sku::factory()->create(['upc' => '012345678905']);

        $result = $this->matcher->match(' 012-345-678905 ', $this->business->id);

        $this->assertCount(1, $result['candidates']);
        $this->assertSame($sku->id, $result['candidates'][0]['sku']->id);
    }

    // ── length variants — leading-zero and EAN/UPC interchange ──────────────────

    public function test_13_digit_scan_with_country_prefix_zero_matches_12_digit_stored_upc(): void
    {
        // Scanner emitted UPC-A as 13-digit EAN-13 (country-code zero prepended).
        $sku = Sku::factory()->create(['upc' => '012345678905']);

        $result = $this->matcher->match('0012345678905', $this->business->id);

        $this->assertCount(1, $result['candidates']);
        $this->assertSame($sku->id, $result['candidates'][0]['sku']->id);
        $this->assertSame(BarcodeMatcher::MATCH_GTIN_EXACT, $result['candidates'][0]['match']);
    }

    public function test_12_digit_scan_matches_13_digit_stored_ean(): void
    {
        $sku = Sku::factory()->create([
            'upc' => null,
            'ean' => '0012345678905',
        ]);

        $result = $this->matcher->match('012345678905', $this->business->id);

        $this->assertCount(1, $result['candidates']);
        $this->assertSame($sku->id, $result['candidates'][0]['sku']->id);
        $this->assertSame(BarcodeMatcher::MATCH_GTIN_EXACT, $result['candidates'][0]['match']);
    }

    public function test_gtin_14_scan_matches_12_digit_stored_upc(): void
    {
        // Case-pack ITF-14 scan should still resolve to the base UPC-A SKU
        // when the indicator digit is 0 (i.e. the GTIN-14 is just the UPC-A
        // padded with leading zeros).
        $sku = Sku::factory()->create(['upc' => '012345678905']);

        $result = $this->matcher->match('00012345678905', $this->business->id);

        $this->assertCount(1, $result['candidates']);
        $this->assertSame($sku->id, $result['candidates'][0]['sku']->id);
    }

    // ── UPC-E expansion ─────────────────────────────────────────────────────────

    public function test_upc_e_scan_expands_to_upc_a_and_matches(): void
    {
        // UPC-E 04252614 expands to UPC-A 042100005264. SKU stores the
        // 12-digit UPC-A form.
        $sku = Sku::factory()->create(['upc' => '042100005264']);

        $result = $this->matcher->match('04252614', $this->business->id);

        $this->assertCount(1, $result['candidates']);
        $this->assertSame($sku->id, $result['candidates'][0]['sku']->id);
        $this->assertSame(BarcodeMatcher::MATCH_GTIN_EXACT, $result['candidates'][0]['match']);
    }

    // ── gtin_missing_check_digit ────────────────────────────────────────────────

    public function test_matches_stored_upc_missing_its_check_digit(): void
    {
        // Real Sempertex case: scan 0030625575393 (scanner-prepended EAN-13)
        // against a stored UPC of 03062557539 (11 digits — importer dropped
        // the check digit).
        $sku = Sku::factory()->create(['upc' => '03062557539']);

        $result = $this->matcher->match('0030625575393', $this->business->id);

        $this->assertNotEmpty($result['candidates']);
        $this->assertSame($sku->id, $result['candidates'][0]['sku']->id);
        $this->assertSame(BarcodeMatcher::MATCH_GTIN_MISSING_CHECK_DIGIT, $result['candidates'][0]['match']);
        $this->assertSame(80, $result['candidates'][0]['score']);
    }

    public function test_matches_stored_ean_missing_its_check_digit(): void
    {
        $sku = Sku::factory()->create([
            'upc' => null,
            'ean' => '400638133393',
        ]);

        $result = $this->matcher->match('4006381333931', $this->business->id);

        $this->assertCount(1, $result['candidates']);
        $this->assertSame($sku->id, $result['candidates'][0]['sku']->id);
        $this->assertSame(BarcodeMatcher::MATCH_GTIN_MISSING_CHECK_DIGIT, $result['candidates'][0]['match']);
    }

    // ── is_valid_gtin metadata ──────────────────────────────────────────────────

    public function test_is_valid_gtin_true_for_valid_check_digit(): void
    {
        $result = $this->matcher->match('012345678905', $this->business->id);

        $this->assertTrue($result['is_valid_gtin']);
    }

    public function test_is_valid_gtin_false_for_invalid_check_digit(): void
    {
        $result = $this->matcher->match('012345678900', $this->business->id);

        $this->assertFalse($result['is_valid_gtin']);
    }

    public function test_is_valid_gtin_false_for_non_gtin_length(): void
    {
        $result = $this->matcher->match('12345', $this->business->id);

        $this->assertNull($result['gtin14']);
        $this->assertFalse($result['is_valid_gtin']);
    }

    // ── asin ────────────────────────────────────────────────────────────────────

    public function test_exact_asin_match_is_alphanumeric(): void
    {
        $sku = Sku::factory()->create([
            'upc' => null,
            'asin' => 'B07XJ8C8F5',
        ]);

        $result = $this->matcher->match('B07XJ8C8F5', $this->business->id);

        $this->assertCount(1, $result['candidates']);
        $this->assertSame($sku->id, $result['candidates'][0]['sku']->id);
        $this->assertSame(BarcodeMatcher::MATCH_ASIN_EXACT, $result['candidates'][0]['match']);
    }

    // ── GS1-prefix fallback ─────────────────────────────────────────────────────

    public function test_gs1_prefix_plus_warehouse_sku_matches(): void
    {
        $brand = Brand::factory()->create();
        BrandGs1Prefix::create(['brand_id' => $brand->id, 'prefix' => '071444']);

        $sku = Sku::factory()->create([
            'brand_id' => $brand->id,
            'upc' => null,
            'ean' => null,
            'warehouse_sku' => '43734',
        ]);

        $result = $this->matcher->match('071444437345', $this->business->id);

        $this->assertCount(1, $result['candidates']);
        $this->assertSame($sku->id, $result['candidates'][0]['sku']->id);
        $this->assertSame(BarcodeMatcher::MATCH_GS1_WAREHOUSE_SKU, $result['candidates'][0]['match']);
    }

    public function test_gs1_prefix_table_is_loaded_only_once_per_match(): void
    {
        // A single scan expands into ~5 barcode forms (raw + GTIN 14/13/12/8 +
        // leading-zero-stripped). The brand GS1 prefix list is identical for
        // every form, so it must be read from the DB once per match(), not once
        // per form. Guards against re-introducing the per-form query.
        $brand = Brand::factory()->create();
        BrandGs1Prefix::create(['brand_id' => $brand->id, 'prefix' => '071444']);

        Sku::factory()->create([
            'brand_id' => $brand->id,
            'upc' => null,
            'ean' => null,
            'warehouse_sku' => '43734',
        ]);

        DB::enableQueryLog();

        $this->matcher->match('071444437345', $this->business->id);

        $prefixQueries = array_filter(
            DB::getQueryLog(),
            static fn (array $entry) => str_contains($entry['query'], 'brand_gs1_prefixes'),
        );

        DB::disableQueryLog();

        $this->assertCount(1, $prefixQueries);
    }

    public function test_gs1_prefix_plus_mfg_no_matches(): void
    {
        $brand = Brand::factory()->create();
        BrandGs1Prefix::create(['brand_id' => $brand->id, 'prefix' => '071444']);

        $sku = Sku::factory()->create([
            'brand_id' => $brand->id,
            'upc' => null,
            'ean' => null,
            'warehouse_sku' => null,
            'mfg_no' => '99821',
        ]);

        $result = $this->matcher->match('071444998213', $this->business->id);

        $this->assertCount(1, $result['candidates']);
        $this->assertSame($sku->id, $result['candidates'][0]['sku']->id);
        $this->assertSame(BarcodeMatcher::MATCH_GS1_MFG_NO, $result['candidates'][0]['match']);
    }

    public function test_gs1_prefix_match_is_scoped_to_the_correct_brand(): void
    {
        $brandA = Brand::factory()->create();
        $brandB = Brand::factory()->create();

        BrandGs1Prefix::create(['brand_id' => $brandA->id, 'prefix' => '071444']);
        BrandGs1Prefix::create(['brand_id' => $brandB->id, 'prefix' => '888888']);

        $brandASku = Sku::factory()->create([
            'brand_id' => $brandA->id,
            'upc' => null,
            'warehouse_sku' => '43734',
        ]);

        Sku::factory()->create([
            'brand_id' => $brandB->id,
            'upc' => null,
            'warehouse_sku' => '43734',
        ]);

        $result = $this->matcher->match('071444437345', $this->business->id);

        $this->assertCount(1, $result['candidates']);
        $this->assertSame($brandASku->id, $result['candidates'][0]['sku']->id);
    }

    public function test_gs1_prefix_match_handles_scanner_prepended_leading_zero(): void
    {
        // Sempertex prefix 030625 only lines up after the scanner-prepended
        // country zero is stripped.
        $brand = Brand::factory()->create();
        BrandGs1Prefix::create(['brand_id' => $brand->id, 'prefix' => '030625']);

        $sku = Sku::factory()->create([
            'brand_id' => $brand->id,
            'upc' => null,
            'warehouse_sku' => '57539',
        ]);

        $result = $this->matcher->match('0030625575393', $this->business->id);

        $this->assertNotEmpty($result['candidates']);
        $this->assertSame($sku->id, $result['candidates'][0]['sku']->id);
        $this->assertSame(BarcodeMatcher::MATCH_GS1_WAREHOUSE_SKU, $result['candidates'][0]['match']);
    }

    public function test_short_warehouse_sku_does_not_create_false_positives(): void
    {
        $brand = Brand::factory()->create();
        BrandGs1Prefix::create(['brand_id' => $brand->id, 'prefix' => '071444']);

        Sku::factory()->create([
            'brand_id' => $brand->id,
            'upc' => null,
            'warehouse_sku' => '12',
        ]);

        $result = $this->matcher->match('071444999999', $this->business->id);

        $this->assertSame([], $result['candidates']);
    }

    // ── ranking ─────────────────────────────────────────────────────────────────

    public function test_ranks_gtin_exact_above_gs1_inferred(): void
    {
        $brand = Brand::factory()->create();
        BrandGs1Prefix::create(['brand_id' => $brand->id, 'prefix' => '071444']);

        $exact = Sku::factory()->create([
            'brand_id' => $brand->id,
            'upc' => '071444437345',
        ]);

        $inferred = Sku::factory()->create([
            'brand_id' => $brand->id,
            'upc' => null,
            'warehouse_sku' => '43734',
        ]);

        $result = $this->matcher->match('071444437345', $this->business->id);

        $this->assertCount(2, $result['candidates']);
        $this->assertSame($exact->id, $result['candidates'][0]['sku']->id);
        $this->assertSame(BarcodeMatcher::MATCH_GTIN_EXACT, $result['candidates'][0]['match']);
        $this->assertSame($inferred->id, $result['candidates'][1]['sku']->id);
        $this->assertSame(BarcodeMatcher::MATCH_GS1_WAREHOUSE_SKU, $result['candidates'][1]['match']);
    }

    // ── tenant scoping ──────────────────────────────────────────────────────────

    public function test_private_sku_is_invisible_to_other_businesses(): void
    {
        $otherBusiness = Business::factory()->create();

        Sku::factory()->create([
            'upc' => '012345678905',
            'owned_by_business_id' => $otherBusiness->id,
        ]);

        $result = $this->matcher->match('012345678905', $this->business->id);

        $this->assertSame([], $result['candidates']);
    }

    public function test_private_sku_is_visible_to_its_owning_business(): void
    {
        $sku = Sku::factory()->create([
            'upc' => '012345678905',
            'owned_by_business_id' => $this->business->id,
        ]);

        $result = $this->matcher->match('012345678905', $this->business->id);

        $this->assertCount(1, $result['candidates']);
        $this->assertSame($sku->id, $result['candidates'][0]['sku']->id);
    }

    // ── empty / no-match ────────────────────────────────────────────────────────

    public function test_returns_no_candidates_when_nothing_matches(): void
    {
        Sku::factory()->create(['upc' => '012345678905']);

        $result = $this->matcher->match('999999999999', $this->business->id);

        $this->assertSame([], $result['candidates']);
    }

    public function test_empty_input_returns_no_candidates(): void
    {
        Sku::factory()->create(['upc' => '012345678905']);

        $result = $this->matcher->match('   ', $this->business->id);

        $this->assertSame('', $result['normalized']);
        $this->assertNull($result['gtin14']);
        $this->assertSame([], $result['candidates']);
    }
}
