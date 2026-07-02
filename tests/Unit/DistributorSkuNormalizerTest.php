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

    public function test_alphanumeric_item_number_is_preserved(): void
    {
        // Sempertex Deluxe/foil codes carry a pack marker ("P2" = 2-count) — the
        // core is alphanumeric, not pure digits, but still a real item number.
        $this->assertSame('56360P2', $this->normalizer->normalize('56360P2'));
    }

    public function test_affixed_alphanumeric_forms_collapse_to_one_core(): void
    {
        // The two forms Todd saw in the queue: LA Balloons' "-B" suffix and Joker's
        // "BT-" prefix, both wrapping the alphanumeric core "56360P2".
        $this->assertSame('56360P2', $this->normalizer->normalize('56360P2-B', ['sku_strip_suffixes' => ['-B']]));
        $this->assertSame('56360P2', $this->normalizer->normalize('BT-56360P2', ['sku_strip_prefixes' => ['BT-']]));
    }

    public function test_layered_suffixes_are_stripped_repeatedly(): void
    {
        // LA Balloons' actual Sempertex format: an inner "TB" variant marker glued
        // straight to the digits (no separator), then an outer "-B" pack marker.
        // A single pass only strips the outer "-B" and leaves "53023TB" stuck —
        // which the alphanumeric-preservation branch then wrongly keeps whole,
        // silently creating a duplicate instead of matching the existing 53023.
        $config = ['sku_strip_suffixes' => ['-KL', '-B', '-M', 'TB']];

        $this->assertSame('53023', $this->normalizer->normalize('53023TB-B', $config));
        $this->assertSame('55023', $this->normalizer->normalize('55023TB-B', $config));
    }

    public function test_layered_prefix_and_suffix_both_strip_in_one_call(): void
    {
        $config = ['sku_strip_prefixes' => ['BT-'], 'sku_strip_suffixes' => ['-B']];

        $this->assertSame('53012', $this->normalizer->normalize('BT-53012-B', $config));
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
