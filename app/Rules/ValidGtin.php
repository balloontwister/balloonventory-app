<?php

namespace App\Rules;

use App\Support\Gtin;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates that a UPC/EAN field — after non-digits are stripped — is a
 * well-formed GTIN: a recognized length (8/12/13/14) and a passing mod-10
 * check digit. Null / empty input is treated as a no-op so the rule can be
 * combined with `nullable` for optional barcode fields.
 */
class ValidGtin implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value)) {
            $fail('validation.valid_gtin')->translate();

            return;
        }

        $digits = Gtin::digitsOnly($value);

        if ($digits === '') {
            $fail('validation.valid_gtin')->translate();

            return;
        }

        $length = strlen($digits);

        if (! in_array($length, [8, 12, 13, 14], true)) {
            $fail('validation.valid_gtin_length')->translate(['length' => $length]);

            return;
        }

        if (! Gtin::isValidCheckDigit($digits)) {
            $fail('validation.valid_gtin_check_digit')->translate([
                'expected' => (string) Gtin::checkDigit(substr($digits, 0, -1)),
                'got' => substr($digits, -1),
            ]);
        }
    }
}
