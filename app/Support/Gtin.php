<?php

namespace App\Support;

/**
 * GS1 GTIN helpers — canonicalize barcodes to GTIN-14, validate the mod-10
 * check digit, and expand zero-suppressed UPC-E codes to UPC-A.
 *
 * GTIN-8 / 12 / 13 / 14 are all the same number, right-justified in 14 digits
 * with leading zeros. Canonical form makes "scanner emitted EAN-13 from a
 * UPC-A label" and "stored as UPC-A in the upc column" collide on a single
 * value, eliminating most of the per-format variant juggling.
 */
class Gtin
{
    /** Lengths that GS1 recognizes as a stored / scanned GTIN. */
    private const VALID_GTIN_LENGTHS = [8, 12, 13, 14];

    /**
     * Return the GTIN-14 canonical form of the given digits, or `null` when
     * the input is not a recognizable GTIN length. Does NOT validate the
     * check digit — callers that care should run isValidCheckDigit().
     */
    public static function canonicalize(string $value): ?string
    {
        $digits = self::digitsOnly($value);
        $length = strlen($digits);

        if (! in_array($length, self::VALID_GTIN_LENGTHS, true)) {
            return null;
        }

        return str_pad($digits, 14, '0', STR_PAD_LEFT);
    }

    /**
     * Compute the GS1 mod-10 check digit for the supplied body digits (the
     * full GTIN minus its trailing check digit). Weights alternate 3, 1 from
     * the rightmost body digit. Throws if the body contains non-digits.
     */
    public static function checkDigit(string $body): int
    {
        if (preg_match('/\D/', $body)) {
            throw new \InvalidArgumentException('Body must contain digits only.');
        }

        $sum = 0;
        $length = strlen($body);

        for ($i = 0; $i < $length; $i++) {
            $digit = (int) $body[$length - 1 - $i];
            $sum += $digit * (($i % 2 === 0) ? 3 : 1);
        }

        return (10 - ($sum % 10)) % 10;
    }

    /**
     * True when the supplied digits form a valid GTIN: a recognized length
     * (8/12/13/14) and a check digit that matches the body.
     */
    public static function isValidCheckDigit(string $value): bool
    {
        $digits = self::digitsOnly($value);

        if (! in_array(strlen($digits), self::VALID_GTIN_LENGTHS, true)) {
            return false;
        }

        $body = substr($digits, 0, -1);
        $expected = self::checkDigit($body);

        return $expected === (int) substr($digits, -1);
    }

    /**
     * Expand a UPC-E (zero-suppressed) code to its UPC-A equivalent. Returns
     * `null` for inputs that are not in UPC-E shape. Accepts 6, 7, or 8
     * digit forms:
     *   - 6 digits: bare body (no number-system or check digit). Number
     *     system 0 is assumed and the check digit is computed.
     *   - 7 digits: number-system + 6-digit body. Check digit is computed.
     *   - 8 digits: number-system + 6-digit body + check digit. The check
     *     digit is recomputed from the expanded form — the supplied one is
     *     trusted but not re-validated here.
     *
     * UPC-E is defined only for number systems 0 and 1. Anything else
     * returns `null`.
     */
    public static function expandUpcE(string $value): ?string
    {
        $digits = self::digitsOnly($value);

        return match (strlen($digits)) {
            6 => self::expandUpcEBody('0', $digits),
            7 => self::expandUpcEBody($digits[0], substr($digits, 1)),
            8 => self::expandUpcEBody($digits[0], substr($digits, 1, 6)),
            default => null,
        };
    }

    private static function expandUpcEBody(string $numberSystem, string $body): ?string
    {
        if (! in_array($numberSystem, ['0', '1'], true) || strlen($body) !== 6) {
            return null;
        }

        [$b1, $b2, $b3, $b4, $b5, $b6] = str_split($body);

        $manufacturerAndProduct = match ($b6) {
            '0', '1', '2' => $b1.$b2.$b6.'0000'.$b3.$b4.$b5,
            '3' => $b1.$b2.$b3.'00000'.$b4.$b5,
            '4' => $b1.$b2.$b3.$b4.'00000'.$b5,
            default => $b1.$b2.$b3.$b4.$b5.'0000'.$b6,
        };

        $upcaBody = $numberSystem.$manufacturerAndProduct;
        $check = self::checkDigit($upcaBody);

        return $upcaBody.$check;
    }

    /**
     * Strip every non-digit character from the input.
     */
    public static function digitsOnly(string $value): string
    {
        return (string) preg_replace('/\D+/', '', $value);
    }

    /**
     * If the input's digits form a valid GTIN (a recognized length AND a
     * matching check digit), return its canonical GTIN-14; otherwise null.
     *
     * Used to detect when a distributor's SKU is itself a barcode — e.g. Larocks
     * lists Kalisan products with the EAN-13 as the SKU — so it can be promoted
     * to the UPC field. A random 10-digit store product id fails the length
     * test and returns null.
     */
    public static function toGtinIfValid(string $value): ?string
    {
        return self::isValidCheckDigit($value) ? self::canonicalize($value) : null;
    }
}
