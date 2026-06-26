<?php

namespace App\Services\DistributorPlatforms;

use App\Services\DistributorSkuNormalizer;
use App\Support\Gtin;

/**
 * Extracts the staged-product fields from a BigCommerce product page's HTML.
 *
 * One parser serves every BigCommerce store: rather than a per-store field map,
 * it reads whatever the page exposes from the two reliable sources, so the
 * differing profiles fall out naturally —
 *   - havinaparty: price login-gated (absent), numeric stock present, no barcode.
 *   - Larocks: public price, no numeric stock (boolean only), mpn, and Kalisan
 *     SKUs that are themselves EAN-13s.
 *
 * Sources (see project_distributor_havinaparty / _larocks specs):
 *   - JSON-LD `Product` block → name, brand, sku, mpn (clean, standard).
 *   - `var BCData = {…}` → product_attributes: sku/upc/mpn, stock, instock, price.
 * JSON-LD `offers.availability`/`price` are NOT trusted for stock (havinaparty
 * hardcodes OutOfStock); BCData is the source of truth there.
 *
 * Returns the content fields only — the crawler adds distributor_id, external_id,
 * url, and fetched_at.
 */
class BigCommerceProductPageParser
{
    public function __construct(private DistributorSkuNormalizer $normalizer) {}

    /**
     * @param  array<string, mixed>  $config  distributor config (e.g. sku affixes)
     * @return array<string, mixed>|null null when the page carries no product data
     */
    public function parse(string $html, array $config = []): ?array
    {
        $ld = $this->jsonLdProduct($html) ?? [];
        $bc = $this->bcDataProductAttributes($html) ?? [];

        $sku = $this->firstNonEmpty([$bc['sku'] ?? null, $ld['sku'] ?? null]);

        if ($sku === null) {
            return null;
        }

        $mpn = $this->firstNonEmpty([$bc['mpn'] ?? null, $ld['mpn'] ?? null]);

        return [
            'raw_sku' => $sku,
            // The manufacturer number (mpn) is a cleaner cross-store join key
            // than a store's internal product id, so prefer it when present.
            'normalized_sku' => $this->normalizer->normalize($mpn ?? $sku, $config),
            'upc' => $this->extractUpc($bc, $sku),
            'title' => $this->firstNonEmpty([$ld['name'] ?? null]),
            'brand' => $this->extractBrand($ld),
            // The category breadcrumb (e.g. Latex Balloons > … > Sempertex Latex >
            // Red Fashion) — an authoritative material/brand/colour signal for
            // stores with no attribute table. Excludes "Home" and the product leaf.
            'categories' => $this->breadcrumbCategories($html),
            'price' => $this->extractPrice($bc, $ld),
            'stock' => $this->extractStockCount($bc),
            'in_stock' => $this->extractInStock($bc, $ld),
        ];
    }

    /**
     * Category names from the JSON-LD BreadcrumbList, ordered top → leaf, with the
     * "Home" root and the product leaf removed (so only real categories remain).
     *
     * @return array<int, string>
     */
    private function breadcrumbCategories(string $html): array
    {
        $list = $this->jsonLdBreadcrumb($html);

        if ($list === null) {
            return [];
        }

        $names = [];
        foreach ($list['itemListElement'] ?? [] as $element) {
            $name = $element['name'] ?? ($element['item']['name'] ?? null);
            if (is_string($name) && trim($name) !== '') {
                $names[] = trim($name);
            }
        }

        // Drop the "Home" root and the product-name leaf — keep the categories.
        if (($names[0] ?? null) !== null && strcasecmp($names[0], 'Home') === 0) {
            array_shift($names);
        }
        array_pop($names);

        return array_values($names);
    }

    /**
     * The JSON-LD `BreadcrumbList` node (bare object or inside an `@graph`).
     *
     * @return array<string, mixed>|null
     */
    private function jsonLdBreadcrumb(string $html): ?array
    {
        if (! preg_match_all('#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $matches)) {
            return null;
        }

        foreach ($matches[1] as $block) {
            $data = json_decode(trim($block), true);

            if (! is_array($data)) {
                continue;
            }

            if (($data['@type'] ?? null) === 'BreadcrumbList') {
                return $data;
            }

            foreach ($data['@graph'] ?? [] as $node) {
                if (is_array($node) && ($node['@type'] ?? null) === 'BreadcrumbList') {
                    return $node;
                }
            }
        }

        return null;
    }

    private function extractUpc(array $bc, string $sku): ?string
    {
        // BCData.upc is often a BigCommerce product ID masquerading as a UPC
        // (e.g. "7144443643" — 10 digits, not a valid GTIN). Only accept
        // explicit values that are genuine GTINs.
        $explicit = $bc['upc'] ?? null;
        if (! empty($explicit)) {
            $digits = Gtin::digitsOnly((string) $explicit);
            if ($digits !== '' && Gtin::toGtinIfValid($digits) !== null) {
                return $digits;
            }
        }

        // SKU-as-barcode: keep the original digit form, but only when the SKU
        // actually validates as a GTIN (so a 10-digit product id is rejected).
        return Gtin::toGtinIfValid($sku) !== null ? Gtin::digitsOnly($sku) : null;
    }

    private function extractBrand(array $ld): ?string
    {
        $brand = $ld['brand'] ?? null;

        if (is_array($brand)) {
            return $this->firstNonEmpty([$brand['name'] ?? null]);
        }

        return $this->firstNonEmpty([$brand]);
    }

    private function extractPrice(array $bc, array $ld): ?float
    {
        $bcValue = $bc['price']['without_tax']['value'] ?? null;
        if (is_numeric($bcValue)) {
            return (float) $bcValue;
        }

        $ldValue = $ld['offers']['price'] ?? null;

        return is_numeric($ldValue) ? (float) $ldValue : null;
    }

    private function extractStockCount(array $bc): ?int
    {
        $stock = $bc['stock'] ?? null;

        return is_numeric($stock) ? (int) $stock : null;
    }

    private function extractInStock(array $bc, array $ld): ?bool
    {
        if (array_key_exists('instock', $bc) && is_bool($bc['instock'])) {
            return $bc['instock'];
        }

        $availability = $ld['offers']['availability'] ?? null;

        return $availability !== null
            ? str_contains(strtolower((string) $availability), 'instock')
            : null;
    }

    /**
     * The JSON-LD `Product` node (handles a bare object or an `@graph` array).
     *
     * @return array<string, mixed>|null
     */
    private function jsonLdProduct(string $html): ?array
    {
        if (! preg_match_all('#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $matches)) {
            return null;
        }

        foreach ($matches[1] as $block) {
            $data = json_decode(trim($block), true);

            if (! is_array($data)) {
                continue;
            }

            if (($data['@type'] ?? null) === 'Product') {
                return $data;
            }

            foreach ($data['@graph'] ?? [] as $node) {
                if (is_array($node) && ($node['@type'] ?? null) === 'Product') {
                    return $node;
                }
            }
        }

        return null;
    }

    /**
     * `BCData.product_attributes`, extracted by balanced-brace scan (the object
     * is nested, so a non-greedy regex would truncate it).
     *
     * @return array<string, mixed>|null
     */
    private function bcDataProductAttributes(string $html): ?array
    {
        $marker = strpos($html, 'var BCData');
        if ($marker === false) {
            return null;
        }

        $start = strpos($html, '{', $marker);
        if ($start === false) {
            return null;
        }

        $json = $this->balancedBraces($html, $start);
        if ($json === null) {
            return null;
        }

        $data = json_decode($json, true);

        return is_array($data) ? ($data['product_attributes'] ?? null) : null;
    }

    private function balancedBraces(string $s, int $start): ?string
    {
        $depth = 0;
        $inString = false;
        $escaped = false;
        $length = strlen($s);

        for ($i = $start; $i < $length; $i++) {
            $char = $s[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;
            } elseif ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($s, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $candidates
     */
    private function firstNonEmpty(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
            if (is_numeric($candidate)) {
                return (string) $candidate;
            }
        }

        return null;
    }
}
