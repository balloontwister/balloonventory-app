<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\BrandGs1Prefix;
use App\Models\Business;
use App\Models\Sku;
use App\Services\BarcodeMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_exact_upc_match_scores_highest(): void
    {
        $sku = Sku::factory()->create(['upc' => '012345678901']);

        $result = $this->matcher->match('012345678901', $this->business->id);

        $this->assertSame('012345678901', $result['normalized']);
        $this->assertCount(1, $result['candidates']);
        $this->assertSame($sku->id, $result['candidates'][0]['sku']->id);
        $this->assertSame(BarcodeMatcher::MATCH_UPC_EXACT, $result['candidates'][0]['match']);
        $this->assertSame(100, $result['candidates'][0]['score']);
    }

    public function test_exact_ean_match(): void
    {
        $sku = Sku::factory()->create([
            'upc' => null,
            'ean' => '4006381333931',
        ]);

        $result = $this->matcher->match('4006381333931', $this->business->id);

        $this->assertCount(1, $result['candidates']);
        $this->assertSame($sku->id, $result['candidates'][0]['sku']->id);
        $this->assertSame(BarcodeMatcher::MATCH_EAN_EXACT, $result['candidates'][0]['match']);
    }

    public function test_strips_separators_and_whitespace_before_matching(): void
    {
        $sku = Sku::factory()->create(['upc' => '012345678901']);

        $result = $this->matcher->match(' 012-345-678901 ', $this->business->id);

        $this->assertCount(1, $result['candidates']);
        $this->assertSame($sku->id, $result['candidates'][0]['sku']->id);
    }

    public function test_leading_zero_prepended_by_scanner_resolves_to_upc(): void
    {
        $sku = Sku::factory()->create(['upc' => '012345678901']);

        // The scanner read the 12-digit UPC-A as a 13-digit EAN-13 with a
        // leading zero — common Honeywell/Datalogic default config.
        $result = $this->matcher->match('0012345678901', $this->business->id);

        $this->assertCount(1, $result['candidates']);
        $this->assertSame($sku->id, $result['candidates'][0]['sku']->id);
        $this->assertSame(BarcodeMatcher::MATCH_UPC_LEADING_ZERO, $result['candidates'][0]['match']);
    }

    public function test_bare_upc_scan_matches_ean_with_leading_zero(): void
    {
        $sku = Sku::factory()->create([
            'upc' => null,
            'ean' => '0012345678901',
        ]);

        $result = $this->matcher->match('012345678901', $this->business->id);

        $this->assertCount(1, $result['candidates']);
        $this->assertSame($sku->id, $result['candidates'][0]['sku']->id);
        $this->assertSame(BarcodeMatcher::MATCH_EAN_LEADING_ZERO, $result['candidates'][0]['match']);
    }

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

    public function test_gs1_prefix_plus_warehouse_sku_matches(): void
    {
        $brand = Brand::factory()->create();
        BrandGs1Prefix::create(['brand_id' => $brand->id, 'prefix' => '071444']);

        // Brand has GS1 prefix 071444. SKU has no UPC but warehouse_sku 43734
        // (a real Qualatex item code). The scanned full UPC contains both.
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

        // Both SKUs share the same warehouse_sku digits, but only brand A's
        // GS1 prefix is in the scan, so only brand A's SKU should match.
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

    public function test_ranks_exact_above_gs1_inferred_match(): void
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
        $this->assertSame(BarcodeMatcher::MATCH_UPC_EXACT, $result['candidates'][0]['match']);
        $this->assertSame($inferred->id, $result['candidates'][1]['sku']->id);
        $this->assertSame(BarcodeMatcher::MATCH_GS1_WAREHOUSE_SKU, $result['candidates'][1]['match']);
    }

    public function test_returns_no_candidates_when_nothing_matches(): void
    {
        Sku::factory()->create(['upc' => '012345678901']);

        $result = $this->matcher->match('999999999999', $this->business->id);

        $this->assertSame([], $result['candidates']);
    }

    public function test_private_sku_is_invisible_to_other_businesses(): void
    {
        $otherBusiness = Business::factory()->create();

        Sku::factory()->create([
            'upc' => '012345678901',
            'owned_by_business_id' => $otherBusiness->id,
        ]);

        $result = $this->matcher->match('012345678901', $this->business->id);

        $this->assertSame([], $result['candidates']);
    }

    public function test_private_sku_is_visible_to_its_owning_business(): void
    {
        $sku = Sku::factory()->create([
            'upc' => '012345678901',
            'owned_by_business_id' => $this->business->id,
        ]);

        $result = $this->matcher->match('012345678901', $this->business->id);

        $this->assertCount(1, $result['candidates']);
        $this->assertSame($sku->id, $result['candidates'][0]['sku']->id);
    }

    public function test_empty_input_returns_no_candidates(): void
    {
        Sku::factory()->create(['upc' => '012345678901']);

        $result = $this->matcher->match('   ', $this->business->id);

        $this->assertSame('', $result['normalized']);
        $this->assertSame([], $result['candidates']);
    }

    public function test_gs1_prefix_match_handles_scanner_prepended_leading_zero(): void
    {
        // Regression: a scanner read Sempertex's 12-digit UPC-A as a 13-digit
        // EAN-13 with a country-code zero prepended. The brand's GS1 prefix
        // (030625) only lines up after that zero is stripped, so the GS1
        // fallback must run against the leading-zero variant too.
        $brand = Brand::factory()->create(['name' => 'Sempertex']);
        BrandGs1Prefix::create(['brand_id' => $brand->id, 'prefix' => '030625']);

        $sku = Sku::factory()->create([
            'brand_id' => $brand->id,
            'upc' => null,
            'ean' => null,
            'warehouse_sku' => '57539',
        ]);

        $result = $this->matcher->match('0030625575393', $this->business->id);

        $this->assertCount(1, $result['candidates']);
        $this->assertSame($sku->id, $result['candidates'][0]['sku']->id);
        $this->assertSame(BarcodeMatcher::MATCH_GS1_WAREHOUSE_SKU, $result['candidates'][0]['match']);
    }

    public function test_short_warehouse_sku_does_not_create_false_positives(): void
    {
        $brand = Brand::factory()->create();
        BrandGs1Prefix::create(['brand_id' => $brand->id, 'prefix' => '071444']);

        // A 2-character warehouse_sku would otherwise match almost any tail.
        Sku::factory()->create([
            'brand_id' => $brand->id,
            'upc' => null,
            'warehouse_sku' => '12',
        ]);

        $result = $this->matcher->match('071444999999', $this->business->id);

        $this->assertSame([], $result['candidates']);
    }
}
