<?php

namespace App\Services\Distributors;

use App\Support\Gtin;

/**
 * A small, curated map of GS1 company prefixes → balloon brand, used as a sanity
 * check before auto-creating a catalog SKU: a barcode's manufacturer prefix should
 * agree with the brand we resolved for it. A mismatch (e.g. an "030625…" Sempertex
 * barcode resolved to Qualatex) flags a bad resolution and routes the proposal to
 * human review instead of auto-creating a wrong product.
 *
 * It is deliberately conservative and partial: it only ever *disproves* a brand
 * when the prefix is one we know. An unknown prefix returns null (can't judge), so
 * the check never blocks a brand we simply haven't catalogued a prefix for. Extend
 * {@see PREFIXES} as more manufacturer prefixes are confirmed.
 */
class Gs1BrandRegistry
{
    /**
     * Known GS1 company prefixes (as they appear at the START of the manufacturer's
     * own barcode — UPC-A keeps its leading number-system digit, EAN-13 keeps its
     * country prefix) → the brand that owns them.
     *
     * @var array<string, string>
     */
    private const PREFIXES = [
        '030625' => 'Sempertex', // Sempertex / Betallatex latex (UPC-A)
        '802188' => 'Gemar',     // Gemar latex (EAN-13)
    ];

    /**
     * The brand a barcode's GS1 company prefix belongs to, or null when the prefix
     * is not one we recognise. Matches against the barcode's significant digits
     * (leading GTIN-14 padding stripped) so a stored UPC-A and its EAN-13 form both
     * resolve.
     */
    public function brandFor(string $barcode): ?string
    {
        $digits = Gtin::digitsOnly($barcode);

        if ($digits === '') {
            return null;
        }

        // The manufacturer prefix sits at the front of the UPC-A / EAN-13, but a
        // value stored padded to GTIN-14 hides it behind leading zeros. Compare the
        // prefix against the GTIN-12 and GTIN-13 views (offsets 2 and 1 of the
        // padded form) as well as the raw digits, so a UPC-A keeps its own leading
        // number-system zero rather than having it stripped.
        $views = [$digits];

        if (($g14 = Gtin::canonicalize($digits)) !== null) {
            $views[] = substr($g14, 1); // EAN-13 view
            $views[] = substr($g14, 2); // UPC-A view
        }

        foreach (self::PREFIXES as $prefix => $brand) {
            foreach ($views as $view) {
                if (str_starts_with($view, $prefix)) {
                    return $brand;
                }
            }
        }

        return null;
    }

    /**
     * True only when the prefix is known AND names a different brand than the one
     * given — i.e. a confident contradiction. Unknown prefixes never conflict.
     */
    public function conflictsWith(string $barcode, string $brandName): bool
    {
        $expected = $this->brandFor($barcode);

        return $expected !== null && strcasecmp($expected, $brandName) !== 0;
    }
}
