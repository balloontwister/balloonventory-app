<?php

namespace Tests\Unit;

use App\Support\Gtin;
use PHPUnit\Framework\TestCase;

class GtinTest extends TestCase
{
    // ── canonicalize() ───────────────────────────────────────────────────────────

    public function test_canonicalize_pads_upc_a_to_gtin14(): void
    {
        $this->assertSame('00012345678905', Gtin::canonicalize('012345678905'));
    }

    public function test_canonicalize_pads_ean_13_to_gtin14(): void
    {
        $this->assertSame('04006381333931', Gtin::canonicalize('4006381333931'));
    }

    public function test_canonicalize_keeps_gtin_14_as_is(): void
    {
        $this->assertSame('10012345678902', Gtin::canonicalize('10012345678902'));
    }

    public function test_canonicalize_pads_gtin_8_to_gtin14(): void
    {
        $this->assertSame('00000040123456', Gtin::canonicalize('40123456'));
    }

    public function test_canonicalize_strips_separators_before_padding(): void
    {
        $this->assertSame('00012345678905', Gtin::canonicalize(' 012-345-678905 '));
    }

    public function test_canonicalize_returns_null_for_unrecognized_length(): void
    {
        $this->assertNull(Gtin::canonicalize('12345'));
        $this->assertNull(Gtin::canonicalize('1234567890'));
        $this->assertNull(Gtin::canonicalize(''));
    }

    // ── checkDigit() / isValidCheckDigit() ───────────────────────────────────────

    public function test_check_digit_matches_known_upc_a(): void
    {
        // Real Wikipedia example: 036000241457 → check digit 7
        $this->assertSame(7, Gtin::checkDigit('03600024145'));
    }

    public function test_check_digit_matches_known_ean_13(): void
    {
        // 4006381333931 (Faber-Castell pen sample) → check digit 1
        $this->assertSame(1, Gtin::checkDigit('400638133393'));
    }

    public function test_is_valid_check_digit_accepts_valid_gtin(): void
    {
        $this->assertTrue(Gtin::isValidCheckDigit('036000241457'));
        $this->assertTrue(Gtin::isValidCheckDigit('4006381333931'));
    }

    public function test_is_valid_check_digit_rejects_invalid_gtin(): void
    {
        // Change the last digit — check digit no longer matches.
        $this->assertFalse(Gtin::isValidCheckDigit('036000241450'));
    }

    public function test_is_valid_check_digit_rejects_non_gtin_lengths(): void
    {
        $this->assertFalse(Gtin::isValidCheckDigit('12345'));
        $this->assertFalse(Gtin::isValidCheckDigit('1234567890'));
    }

    // ── expandUpcE() ─────────────────────────────────────────────────────────────

    public function test_expand_upc_e_handles_b6_one_pattern(): void
    {
        // Wikipedia's canonical example: UPC-E 0425261 expands to UPC-A
        // 042100005264. The 8-digit form 04252614 expands to the same
        // UPC-A (check digit is recomputed on expansion).
        $this->assertSame('042100005264', Gtin::expandUpcE('04252614'));
        $this->assertSame('042100005264', Gtin::expandUpcE('0425261'));
    }

    public function test_expand_upc_e_handles_b6_high_pattern(): void
    {
        // b6 ∈ 5..9 keeps the manufacturer code intact and inserts four
        // zeros before the trailing digit. 01234565 → 012345000065.
        $this->assertSame('012345000065', Gtin::expandUpcE('01234565'));
    }

    public function test_expand_upc_e_treats_six_digit_input_as_ns_zero_body(): void
    {
        // Six-digit form: bare body, NS defaults to 0, check digit computed.
        // Body 425261 with NS 0 → UPC-A 042100005264.
        $this->assertSame('042100005264', Gtin::expandUpcE('425261'));
    }

    public function test_expand_upc_e_returns_null_for_invalid_number_system(): void
    {
        // UPC-E only valid for NS 0 or 1.
        $this->assertNull(Gtin::expandUpcE('21234565'));
    }

    public function test_expand_upc_e_returns_null_for_wrong_length(): void
    {
        $this->assertNull(Gtin::expandUpcE('12345'));
        $this->assertNull(Gtin::expandUpcE('012345678901'));
    }
}
