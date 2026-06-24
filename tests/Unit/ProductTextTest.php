<?php

namespace Tests\Unit;

use App\Support\ProductText;
use PHPUnit\Framework\TestCase;

class ProductTextTest extends TestCase
{
    public function test_normalizes_inch_notations_to_one_form(): void
    {
        foreach (['11 inch', '11-inch', '11inch', '11 inches', '11"', '11”', '11-in', '11in'] as $variant) {
            $this->assertStringContainsString(
                '11in',
                ProductText::normalizeSizeTokens("Sempertex {$variant} Fashion Red"),
                "Failed to normalize '{$variant}'",
            );
        }
    }

    public function test_distributor_and_catalog_size_forms_compare_equal(): void
    {
        $title = ProductText::normalizeSizeTokens('11 inch sempertex fashion red');
        $catalog = ProductText::normalizeSizeTokens('11-inch');

        $this->assertTrue(ProductText::mentions($title, $catalog));
    }

    public function test_does_not_confuse_a_larger_size(): void
    {
        $title = ProductText::normalizeSizeTokens('110 inch giant');
        $needle = ProductText::normalizeSizeTokens('11-inch');

        $this->assertFalse(ProductText::mentions($title, $needle));
    }

    public function test_pack_count_parses_common_forms(): void
    {
        $this->assertSame(100, ProductText::packCount('11"S Red Fashion (100 count)'));
        $this->assertSame(50, ProductText::packCount('5-inch latex 50 per bag'));
        $this->assertSame(25, ProductText::packCount('bag of 25'));
        $this->assertNull(ProductText::packCount('no count here'));
    }
}
