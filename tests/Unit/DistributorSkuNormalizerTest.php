<?php

namespace Tests\Unit;

use App\Services\DistributorSkuNormalizer;
use PHPUnit\Framework\TestCase;

class DistributorSkuNormalizerTest extends TestCase
{
    private DistributorSkuNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new DistributorSkuNormalizer;
    }

    public function test_bare_item_number_is_returned_as_is(): void
    {
        $this->assertSame('53012', $this->normalizer->normalize('53012'));
    }

    public function test_generic_fallback_strips_a_prefix(): void
    {
        // BargainBalloons' "BL-" prefix, no config needed.
        $this->assertSame('53012', $this->normalizer->normalize('BL-53012'));
    }

    public function test_generic_fallback_strips_a_suffix(): void
    {
        // LA Balloons' "-B" suffix, no config needed.
        $this->assertSame('53012', $this->normalizer->normalize('53012-B'));
    }

    public function test_all_three_distributor_forms_collapse_to_one_core(): void
    {
        $core = $this->normalizer->normalize('53012');

        $this->assertSame($core, $this->normalizer->normalize('BL-53012'));
        $this->assertSame($core, $this->normalizer->normalize('53012-B'));
    }

    public function test_configured_prefix_is_stripped(): void
    {
        $config = ['sku_strip_prefixes' => ['BL-']];

        $this->assertSame('53012', $this->normalizer->normalize('BL-53012', $config));
    }

    public function test_configured_suffix_is_stripped(): void
    {
        $config = ['sku_strip_suffixes' => ['-B']];

        $this->assertSame('53012', $this->normalizer->normalize('53012-B', $config));
    }

    public function test_lossy_variant_collapse_is_documented(): void
    {
        // 53012-B-10 is a different (10-ct) product, but it shares the core
        // number. The normalizer intentionally collapses it to 53012 — the
        // differing UPC keeps it from clustering with the 100-ct.
        $this->assertSame('53012', $this->normalizer->normalize('53012-B-10'));
    }

    public function test_slug_without_a_clear_item_number_returns_null(): void
    {
        // A sitemap-derived slug has no 4+ digit run → no core number.
        $this->assertNull($this->normalizer->normalize('36-s-crystal-clear-10-count'));
    }

    public function test_short_numbers_are_not_treated_as_item_numbers(): void
    {
        $this->assertNull($this->normalizer->normalize('123'));
        $this->assertNull($this->normalizer->normalize('12'));
    }

    public function test_purely_alphabetic_sku_returns_null(): void
    {
        $this->assertNull($this->normalizer->normalize('MEGAGLU'));
    }

    public function test_empty_and_whitespace_return_null(): void
    {
        $this->assertNull($this->normalizer->normalize(''));
        $this->assertNull($this->normalizer->normalize('   '));
    }

    public function test_eight_digit_internal_sku_is_preserved(): void
    {
        // Our own warehouse_sku style (e.g. 20001208) is already a clean number.
        $this->assertSame('20001208', $this->normalizer->normalize('20001208'));
    }
}
