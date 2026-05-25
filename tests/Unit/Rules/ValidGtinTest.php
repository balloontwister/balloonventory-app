<?php

namespace Tests\Unit\Rules;

use App\Rules\ValidGtin;
use PHPUnit\Framework\TestCase;

class ValidGtinTest extends TestCase
{
    private function validate(?string $value): array
    {
        $failures = [];
        $fail = function (string $message) use (&$failures) {
            $failures[] = $message;

            return new class
            {
                public function translate(array $replace = []): void {}
            };
        };

        (new ValidGtin)->validate('upc', $value, $fail);

        return $failures;
    }

    public function test_passes_for_valid_upc_a(): void
    {
        $this->assertSame([], $this->validate('012345678905'));
    }

    public function test_passes_for_valid_ean_13(): void
    {
        $this->assertSame([], $this->validate('4006381333931'));
    }

    public function test_passes_for_valid_gtin_14(): void
    {
        // 10012345678902 — body 1001234567890, check digit 2.
        $this->assertSame([], $this->validate('10012345678902'));
    }

    public function test_passes_for_null_and_empty(): void
    {
        $this->assertSame([], $this->validate(null));
        $this->assertSame([], $this->validate(''));
    }

    public function test_strips_separators_before_validating(): void
    {
        $this->assertSame([], $this->validate(' 012-345-678905 '));
    }

    public function test_fails_for_wrong_length(): void
    {
        $this->assertNotEmpty($this->validate('12345'));
        $this->assertNotEmpty($this->validate('1234567890'));
    }

    public function test_fails_for_invalid_check_digit(): void
    {
        $this->assertNotEmpty($this->validate('012345678900'));
    }

    public function test_fails_for_input_with_no_digits(): void
    {
        $this->assertNotEmpty($this->validate('abc-def'));
    }
}
