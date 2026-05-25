<?php

namespace App\Services;

use App\Models\BrandGs1Prefix;
use App\Models\Sku;
use App\Support\Gtin;
use Illuminate\Database\Eloquent\Builder;

class BarcodeMatcher
{
    /** Scan's GTIN-14 canonical form matches the stored `upc` / `ean` in any length variant. */
    public const MATCH_GTIN_EXACT = 'gtin_exact';

    /** Scan's GTIN-14 canonical form, minus its trailing check digit, matches the stored value. */
    public const MATCH_GTIN_MISSING_CHECK_DIGIT = 'gtin_missing_check_digit';

    /** Exact match against `sku.asin` (alphanumeric, raw input). */
    public const MATCH_ASIN_EXACT = 'asin_exact';

    /** Scan starts with a brand's GS1 prefix and contains the SKU's `warehouse_sku`. */
    public const MATCH_GS1_WAREHOUSE_SKU = 'gs1_warehouse_sku';

    /** Scan starts with a brand's GS1 prefix and contains the SKU's `mfg_no`. */
    public const MATCH_GS1_MFG_NO = 'gs1_mfg_no';

    /** Scan starts with a brand's GS1 prefix and contains the SKU's `asin`. */
    public const MATCH_GS1_ASIN = 'gs1_asin';

    /** Minimum needle length for the missing-check-digit fallback. */
    private const MIN_CHECK_DIGIT_STRIP_LENGTH = 8;

    /** @var array<string,int> */
    private const SCORES = [
        self::MATCH_GTIN_EXACT => 100,
        self::MATCH_GTIN_MISSING_CHECK_DIGIT => 80,
        self::MATCH_ASIN_EXACT => 75,
        self::MATCH_GS1_WAREHOUSE_SKU => 60,
        self::MATCH_GS1_MFG_NO => 60,
        self::MATCH_GS1_ASIN => 55,
    ];

    /**
     * Find candidate SKUs for a scanned barcode, ranked by confidence.
     *
     * The matcher follows GS1 canonical-form practice: every UPC/EAN length
     * variant (GTIN-8/12/13/14) is the same number padded with leading zeros
     * to 14 digits, so all the per-length matching collapses into one tier.
     * The pipeline is:
     *
     *   1. Pre-normalize: trim, strip non-digits, expand UPC-E to UPC-A if
     *      applicable.
     *   2. Build a set of plausible stored-value forms from the scan's
     *      GTIN-14: the standard length variants (14/13/12/8) plus a
     *      leading-zero-stripped form.
     *   3. `gtin_exact` — stored `upc` / `ean` matches any of those forms.
     *   4. `gtin_missing_check_digit` — same set with the trailing digit
     *      dropped, covering imports that truncated the check digit.
     *   5. `asin_exact` — raw scan matches stored `asin` (alphanumeric).
     *   6. `gs1_*` fallback — scan starts with a known brand GS1 prefix and
     *      the tail contains the SKU's warehouse_sku / mfg_no / asin.
     *
     * Results are scoped to the catalog visibility rule (shared SKUs always
     * visible; private SKUs only visible to their owning business). Pass the
     * current business id to include that tenant's private SKUs; omit it (or
     * pass null) to search only the shared catalog.
     *
     * @return array{
     *     scanned: string,
     *     normalized: string,
     *     gtin14: ?string,
     *     is_valid_gtin: bool,
     *     candidates: array<int, array{sku: Sku, match: string, score: int}>
     * }
     */
    public function match(string $scanned, ?string $businessId = null): array
    {
        $scanned = trim($scanned);
        $normalized = Gtin::digitsOnly($scanned);

        $gtin14 = $this->resolveGtin14($normalized);
        $isValidGtin = $gtin14 !== null && Gtin::isValidCheckDigit($gtin14);

        $hits = [];

        if ($gtin14 !== null) {
            $exactForms = $this->storedFormsFromGtin14($gtin14);

            $this->collectExactColumnMatches($hits, $businessId, 'upc', $exactForms, self::MATCH_GTIN_EXACT);
            $this->collectExactColumnMatches($hits, $businessId, 'ean', $exactForms, self::MATCH_GTIN_EXACT);

            $missingCheckForms = $this->missingCheckDigitVariants($exactForms);
            $this->collectExactColumnMatches($hits, $businessId, 'upc', $missingCheckForms, self::MATCH_GTIN_MISSING_CHECK_DIGIT);
            $this->collectExactColumnMatches($hits, $businessId, 'ean', $missingCheckForms, self::MATCH_GTIN_MISSING_CHECK_DIGIT);
        }

        if ($scanned !== '') {
            $this->collectExactColumnMatches($hits, $businessId, 'asin', [$scanned], self::MATCH_ASIN_EXACT);
        }

        // GS1-prefix fallback. Try the raw normalized scan plus every length
        // variant of the canonical GTIN-14 form, so a 13-digit EAN-13 scan
        // with a scanner-prepended country zero still lines up against a
        // 6-digit Sempertex/Qualatex prefix once the leading zero is stripped.
        $gs1Forms = $normalized === '' ? [] : [$normalized];
        if ($gtin14 !== null) {
            $gs1Forms = array_merge($gs1Forms, $this->storedFormsFromGtin14($gtin14));
        }
        foreach (array_unique($gs1Forms) as $digits) {
            if ($digits === '') {
                continue;
            }
            $this->collectGs1PrefixMatches($hits, $businessId, $digits);
        }

        return [
            'scanned' => $scanned,
            'normalized' => $normalized,
            'gtin14' => $gtin14,
            'is_valid_gtin' => $isValidGtin,
            'candidates' => $this->rankAndDedupe($hits),
        ];
    }

    /**
     * Resolve a GTIN-14 canonical form from the scanned digits. Expands
     * UPC-E codes to UPC-A before canonicalization so a zero-suppressed
     * label still lines up against a 12-digit UPC stored in the database.
     */
    private function resolveGtin14(string $digits): ?string
    {
        if ($digits === '') {
            return null;
        }

        // UPC-E codes are 6/7/8 digits and need expansion before they can be
        // compared to a stored UPC-A. The expanded form is then canonicalized
        // like any other GTIN.
        $expansionInput = match (strlen($digits)) {
            6, 7, 8 => Gtin::expandUpcE($digits),
            default => null,
        };

        if ($expansionInput !== null) {
            $expanded = Gtin::canonicalize($expansionInput);
            if ($expanded !== null) {
                return $expanded;
            }
        }

        return Gtin::canonicalize($digits);
    }

    /**
     * Every plausible stored-value form derived from a GTIN-14: the standard
     * length variants (14/13/12/8) plus a leading-zero-stripped form for
     * legacy data that dropped padding. Empty strings and duplicates are
     * removed.
     *
     * @return array<int, string>
     */
    private function storedFormsFromGtin14(string $gtin14): array
    {
        $forms = [
            $gtin14,                  // GTIN-14
            substr($gtin14, 1),       // GTIN-13 / EAN-13
            substr($gtin14, 2),       // GTIN-12 / UPC-A
            substr($gtin14, 6),       // GTIN-8
            ltrim($gtin14, '0'),      // any leading-zero-stripped form (legacy imports)
        ];

        return array_values(array_unique(array_filter($forms, static fn ($v) => $v !== '')));
    }

    /**
     * Drop the trailing digit from every supplied form, keeping only those
     * that are still long enough to avoid colliding with unrelated SKUs.
     *
     * @param  array<int, string>  $forms
     * @return array<int, string>
     */
    private function missingCheckDigitVariants(array $forms): array
    {
        $variants = [];

        foreach ($forms as $form) {
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

        $needle = Gtin::digitsOnly($identifier);
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
