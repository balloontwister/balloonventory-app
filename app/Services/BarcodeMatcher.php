<?php

namespace App\Services;

use App\Models\BrandGs1Prefix;
use App\Models\Sku;
use Illuminate\Database\Eloquent\Builder;

class BarcodeMatcher
{
    /** Exact match against `sku.upc`. */
    public const MATCH_UPC_EXACT = 'upc_exact';

    /** Exact match against `sku.ean`. */
    public const MATCH_EAN_EXACT = 'ean_exact';

    /** Match against `sku.upc` after normalizing a leading zero on either side. */
    public const MATCH_UPC_LEADING_ZERO = 'upc_leading_zero';

    /** Match against `sku.ean` after normalizing a leading zero on either side. */
    public const MATCH_EAN_LEADING_ZERO = 'ean_leading_zero';

    /** Match against `sku.upc` after dropping the trailing check digit from the scan. */
    public const MATCH_UPC_MISSING_CHECK_DIGIT = 'upc_missing_check_digit';

    /** Match against `sku.ean` after dropping the trailing check digit from the scan. */
    public const MATCH_EAN_MISSING_CHECK_DIGIT = 'ean_missing_check_digit';

    /** Exact match against `sku.asin` (alphanumeric, raw input). */
    public const MATCH_ASIN_EXACT = 'asin_exact';

    /** Scan starts with a brand's GS1 prefix and contains the SKU's `warehouse_sku`. */
    public const MATCH_GS1_WAREHOUSE_SKU = 'gs1_warehouse_sku';

    /** Scan starts with a brand's GS1 prefix and contains the SKU's `mfg_no`. */
    public const MATCH_GS1_MFG_NO = 'gs1_mfg_no';

    /** Scan starts with a brand's GS1 prefix and contains the SKU's `asin`. */
    public const MATCH_GS1_ASIN = 'gs1_asin';

    /** Minimum digit length (post-strip) required for the check-digit fallback to fire. */
    private const MIN_CHECK_DIGIT_STRIP_LENGTH = 8;

    /** @var array<string,int> */
    private const SCORES = [
        self::MATCH_UPC_EXACT => 100,
        self::MATCH_EAN_EXACT => 95,
        self::MATCH_UPC_LEADING_ZERO => 90,
        self::MATCH_EAN_LEADING_ZERO => 85,
        self::MATCH_UPC_MISSING_CHECK_DIGIT => 80,
        self::MATCH_EAN_MISSING_CHECK_DIGIT => 78,
        self::MATCH_ASIN_EXACT => 75,
        self::MATCH_GS1_WAREHOUSE_SKU => 60,
        self::MATCH_GS1_MFG_NO => 60,
        self::MATCH_GS1_ASIN => 55,
    ];

    /**
     * Find candidate SKUs for a scanned barcode, ranked by confidence.
     *
     * The matcher tries, in order:
     *   1. Exact `upc` / `ean` match on the digits of the scan.
     *   2. Leading-zero variants — scanners sometimes prepend a zero (turning a
     *      12-digit UPC-A into a 13-digit EAN-13) or the database may store
     *      `'0' + upc` in the `ean` column. Both directions are checked.
     *   3. Missing-check-digit variants — some imports dropped the trailing
     *      check digit. The scan minus its last digit (and each leading-zero
     *      variant minus its last digit) is matched against `upc` / `ean`.
     *   4. Exact `asin` match (raw input, since ASINs are alphanumeric).
     *   5. Brand GS1 prefix match: if the scanned digits start with a known
     *      brand's GS1 company prefix, look for SKUs under that brand whose
     *      `warehouse_sku`, `mfg_no`, or `asin` is contained in the remainder
     *      of the scanned digits.
     *
     * Results are scoped to the catalog visibility rule (shared SKUs always
     * visible; private SKUs only visible to their owning business). Pass the
     * current business id to include that tenant's private SKUs; omit it (or
     * pass null) to search only the shared catalog.
     *
     * @return array{
     *     scanned: string,
     *     normalized: string,
     *     candidates: array<int, array{sku: Sku, match: string, score: int}>
     * }
     */
    public function match(string $scanned, ?string $businessId = null): array
    {
        $scanned = trim($scanned);
        $normalized = $this->digitsOnly($scanned);

        $hits = [];

        $leadingZeroVariants = $normalized === '' ? [] : $this->leadingZeroVariants($normalized);

        if ($normalized !== '') {
            $this->collectExactColumnMatches($hits, $businessId, 'upc', [$normalized], self::MATCH_UPC_EXACT);
            $this->collectExactColumnMatches($hits, $businessId, 'ean', [$normalized], self::MATCH_EAN_EXACT);

            if ($leadingZeroVariants !== []) {
                $this->collectExactColumnMatches($hits, $businessId, 'upc', $leadingZeroVariants, self::MATCH_UPC_LEADING_ZERO);
                $this->collectExactColumnMatches($hits, $businessId, 'ean', $leadingZeroVariants, self::MATCH_EAN_LEADING_ZERO);
            }

            // Some import paths dropped the trailing check digit. Try every
            // digit form with its last digit stripped. Guarded by a minimum
            // length so a short scan can't collapse to a 1-2 char needle that
            // matches half the catalog.
            $missingCheckDigitVariants = $this->missingCheckDigitVariants($normalized, $leadingZeroVariants);
            if ($missingCheckDigitVariants !== []) {
                $this->collectExactColumnMatches($hits, $businessId, 'upc', $missingCheckDigitVariants, self::MATCH_UPC_MISSING_CHECK_DIGIT);
                $this->collectExactColumnMatches($hits, $businessId, 'ean', $missingCheckDigitVariants, self::MATCH_EAN_MISSING_CHECK_DIGIT);
            }
        }

        if ($scanned !== '') {
            $this->collectExactColumnMatches($hits, $businessId, 'asin', [$scanned], self::MATCH_ASIN_EXACT);
        }

        // Run the GS1-prefix fallback against the raw normalized scan AND each
        // leading-zero variant. A 13-digit EAN-13 emitted by a scanner from a
        // 12-digit UPC-A has a country-code zero in front, so the brand's GS1
        // prefix only lines up after that zero is stripped.
        foreach (array_unique(array_filter([$normalized, ...$leadingZeroVariants])) as $digits) {
            $this->collectGs1PrefixMatches($hits, $businessId, $digits);
        }

        $candidates = $this->rankAndDedupe($hits);

        return [
            'scanned' => $scanned,
            'normalized' => $normalized,
            'candidates' => $candidates,
        ];
    }

    /**
     * Strip every non-digit character. Returns an empty string when the input
     * has no digits at all (e.g. a pure-letter ASIN).
     */
    public function digitsOnly(string $value): string
    {
        return (string) preg_replace('/\D+/', '', $value);
    }

    /**
     * @return array<int, string>
     */
    private function leadingZeroVariants(string $digits): array
    {
        $variants = [];

        // Scanner emitted 0 + UPC-A (13 digits) — try the 12-digit version.
        if (strlen($digits) === 13 && str_starts_with($digits, '0')) {
            $variants[] = substr($digits, 1);
        }

        // Scanner emitted bare UPC-A (12 digits) — try the EAN-13 form (0 + UPC).
        if (strlen($digits) === 12) {
            $variants[] = '0'.$digits;
        }

        return array_values(array_unique($variants));
    }

    /**
     * Drop the trailing digit from every supplied digit form. Used to match
     * SKUs whose stored UPC/EAN lost its check digit during import (e.g.
     * spreadsheet truncation). Results below the minimum-length threshold are
     * discarded so a short scan can't collapse into a near-empty needle.
     *
     * @param  array<int, string>  $extras
     * @return array<int, string>
     */
    private function missingCheckDigitVariants(string $normalized, array $extras): array
    {
        $variants = [];

        foreach ([$normalized, ...$extras] as $form) {
            if (strlen($form) <= self::MIN_CHECK_DIGIT_STRIP_LENGTH) {
                continue;
            }
            $variants[] = substr($form, 0, -1);
        }

        return array_values(array_unique($variants));
    }

    /**
     * @param  array<int, array{sku: Sku, match: string}>  $hits
     * @param  array<int, string>  $values
     */
    private function collectExactColumnMatches(
        array &$hits,
        ?string $businessId,
        string $column,
        array $values,
        string $matchType,
    ): void {
        $values = array_values(array_filter($values, static fn ($v) => $v !== null && $v !== ''));

        if ($values === []) {
            return;
        }

        $skus = $this->visibleSkuQuery($businessId)
            ->whereIn($column, $values)
            ->get();

        foreach ($skus as $sku) {
            $hits[] = ['sku' => $sku, 'match' => $matchType];
        }
    }

    /**
     * @param  array<int, array{sku: Sku, match: string}>  $hits
     */
    private function collectGs1PrefixMatches(array &$hits, ?string $businessId, string $normalized): void
    {
        // Longest prefix first so brands with more specific prefixes (e.g. a
        // 10-digit assignment) win over shorter umbrella prefixes.
        $prefixes = BrandGs1Prefix::orderByRaw('LENGTH(prefix) DESC')->get();

        foreach ($prefixes as $prefixRow) {
            $prefix = (string) $prefixRow->prefix;

            if ($prefix === '' || ! str_starts_with($normalized, $prefix)) {
                continue;
            }

            // The portion of the scan that follows the GS1 prefix. Includes
            // the check digit; substring comparisons below will tolerate it.
            $tail = substr($normalized, strlen($prefix));

            if ($tail === '') {
                continue;
            }

            $this->matchBrandSkusByTail($hits, $businessId, $prefixRow->brand_id, $tail);
        }
    }

    /**
     * @param  array<int, array{sku: Sku, match: string}>  $hits
     */
    private function matchBrandSkusByTail(array &$hits, ?string $businessId, string $brandId, string $tail): void
    {
        $skus = $this->visibleSkuQuery($businessId)
            ->where('brand_id', $brandId)
            ->where(function (Builder $q): void {
                $q->whereNotNull('warehouse_sku')
                    ->orWhereNotNull('mfg_no')
                    ->orWhereNotNull('asin');
            })
            ->get(['id', 'brand_id', 'warehouse_sku', 'mfg_no', 'asin', 'upc', 'ean', 'owned_by_business_id', 'name', 'computed_name']);

        foreach ($skus as $sku) {
            foreach ([
                'warehouse_sku' => self::MATCH_GS1_WAREHOUSE_SKU,
                'mfg_no' => self::MATCH_GS1_MFG_NO,
                'asin' => self::MATCH_GS1_ASIN,
            ] as $column => $matchType) {
                if ($this->tailContainsIdentifier($tail, (string) ($sku->{$column} ?? ''))) {
                    $hits[] = ['sku' => $sku, 'match' => $matchType];
                }
            }
        }
    }

    /**
     * True when `$identifier` (after digit normalization and leading-zero
     * stripping) appears inside `$tail`. Identifiers shorter than 3 digits are
     * rejected to keep false positives down.
     */
    private function tailContainsIdentifier(string $tail, string $identifier): bool
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return false;
        }

        $needle = $this->digitsOnly($identifier);
        if ($needle === '') {
            return false;
        }

        $needle = ltrim($needle, '0');

        if (strlen($needle) < 3) {
            return false;
        }

        return str_contains($tail, $needle);
    }

    /**
     * Apply the catalog visibility rule: shared SKUs (NULL owner) plus the
     * caller's private SKUs.
     */
    private function visibleSkuQuery(?string $businessId): Builder
    {
        return Sku::query()->where(function (Builder $q) use ($businessId): void {
            $q->whereNull('owned_by_business_id');

            if ($businessId !== null) {
                $q->orWhere('owned_by_business_id', $businessId);
            }
        });
    }

    /**
     * Dedupe by `sku.id`, keep the highest-scoring match per SKU, and order
     * the resulting list by score descending. Ties break on insertion order.
     *
     * @param  array<int, array{sku: Sku, match: string}>  $hits
     * @return array<int, array{sku: Sku, match: string, score: int}>
     */
    private function rankAndDedupe(array $hits): array
    {
        $bestPerSku = [];

        foreach ($hits as $hit) {
            $score = self::SCORES[$hit['match']] ?? 0;
            $skuId = $hit['sku']->id;

            if (! isset($bestPerSku[$skuId]) || $score > $bestPerSku[$skuId]['score']) {
                $bestPerSku[$skuId] = [
                    'sku' => $hit['sku'],
                    'match' => $hit['match'],
                    'score' => $score,
                ];
            }
        }

        $ranked = array_values($bestPerSku);

        usort($ranked, static fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return $ranked;
    }
}
