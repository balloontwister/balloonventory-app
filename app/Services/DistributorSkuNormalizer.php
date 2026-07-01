<?php

namespace App\Services;

/**
 * Reduces a distributor's SKU to the bare manufacturer item number used to
 * group the same product across stores. The same Sempertex/Betallic item shows
 * up as `53012` (havinaparty), `BL-53012` (BargainBalloons), and `53012-B`
 * (LA Balloons) — all of which normalize to `53012`. Item numbers may carry a
 * pack/variant marker (`56360P2`), so a clean alphanumeric core is kept too, not
 * just pure-digit ones — `BT-56360P2` and `56360P2-B` both normalize to `56360P2`.
 *
 * This is only a GROUPING HINT. A shared core number proposes that two listings
 * might be the same product; identity is confirmed downstream by a matching UPC
 * (clustering is UPC-gated), so a lossy result here is acceptable — e.g.
 * `53012-B` (100-ct) and `53012-B-10` (10-ct) both reduce to `53012` but are
 * kept apart by their differing barcodes.
 */
class DistributorSkuNormalizer
{
    /** Manufacturer item numbers are at least this many digits; shorter runs are ignored. */
    private const MIN_CORE_LENGTH = 4;

    /**
     * @param  array<string, mixed>  $config  Per-distributor overrides:
     *                                        - sku_strip_prefixes: string[] (e.g. ["BL-"])
     *                                        - sku_strip_suffixes: string[] (e.g. ["-B"])
     */
    public function normalize(string $rawSku, array $config = []): ?string
    {
        $sku = strtoupper(trim($rawSku));

        if ($sku === '') {
            return null;
        }

        $sku = $this->stripConfiguredAffixes($sku, $config);

        // A single clean token (no separators left) that carries at least one digit
        // is the item number — trust it whether it's pure digits ("53012") or an
        // alphanumeric code with a pack/variant marker ("56360P2"). Requiring a
        // digit keeps pure-letter SKUs ("MEGAGLU") out.
        $trimmed = trim($sku, " \t-_/.");
        if (strlen($trimmed) >= self::MIN_CORE_LENGTH
            && preg_match('/^[A-Z0-9]+$/', $trimmed)
            && preg_match('/\d/', $trimmed)) {
            return $trimmed;
        }

        // Generic fallback: the longest all-digit token of sufficient length.
        // "BL-53012" → 53012, "53012-B" → 53012, slug-y "36-s-...-10-count" → null.
        return $this->longestNumericToken($sku);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function stripConfiguredAffixes(string $sku, array $config): string
    {
        foreach ($this->stringList($config['sku_strip_prefixes'] ?? []) as $prefix) {
            $prefix = strtoupper($prefix);
            if ($prefix !== '' && str_starts_with($sku, $prefix)) {
                $sku = substr($sku, strlen($prefix));
                break;
            }
        }

        foreach ($this->stringList($config['sku_strip_suffixes'] ?? []) as $suffix) {
            $suffix = strtoupper($suffix);
            if ($suffix !== '' && str_ends_with($sku, $suffix)) {
                $sku = substr($sku, 0, -strlen($suffix));
                break;
            }
        }

        return $sku;
    }

    private function longestNumericToken(string $sku): ?string
    {
        $tokens = preg_split('/[^A-Z0-9]+/', $sku) ?: [];
        $core = null;

        foreach ($tokens as $token) {
            if (ctype_digit($token)
                && strlen($token) >= self::MIN_CORE_LENGTH
                && ($core === null || strlen($token) > strlen($core))) {
                $core = $token;
            }
        }

        return $core;
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    private function stringList($value): array
    {
        return array_values(array_filter(
            array_map('strval', is_array($value) ? $value : [$value]),
            fn (string $item) => $item !== '',
        ));
    }
}
