<?php

namespace Tests\Unit;

use App\Services\Distributors\Gs1BrandRegistry;
use Tests\TestCase;

class Gs1BrandRegistryTest extends TestCase
{
    public function test_resolves_a_known_upc_a_prefix(): void
    {
        $this->assertSame('Sempertex', (new Gs1BrandRegistry)->brandFor('030625530057'));
    }

    public function test_resolves_a_known_prefix_through_gtin14_padding(): void
    {
        // A UPC-A stored padded to GTIN-14 still resolves once padding is trimmed.
        $this->assertSame('Sempertex', (new Gs1BrandRegistry)->brandFor('00030625530057'));
    }

    public function test_resolves_a_known_ean13_prefix(): void
    {
        $this->assertSame('Gemar', (new Gs1BrandRegistry)->brandFor('8021880123456'));
    }

    public function test_unknown_prefix_is_null(): void
    {
        $this->assertNull((new Gs1BrandRegistry)->brandFor('123456789012'));
    }

    public function test_conflicts_only_on_a_confident_mismatch(): void
    {
        $registry = new Gs1BrandRegistry;

        // Known prefix, wrong brand → conflict.
        $this->assertTrue($registry->conflictsWith('030625530057', 'Qualatex'));
        // Known prefix, right brand (case-insensitive) → no conflict.
        $this->assertFalse($registry->conflictsWith('030625530057', 'sempertex'));
        // Unknown prefix → never conflicts.
        $this->assertFalse($registry->conflictsWith('123456789012', 'Qualatex'));
    }
}
