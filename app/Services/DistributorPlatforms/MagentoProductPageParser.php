<?php

namespace App\Services\DistributorPlatforms;

/**
 * Reads a Magento 2 product page into the staged-product fields we need, out of
 * the page's JSON-LD Product block (Magento renders name / sku / brand / price /
 * availability there). A Magento store exposes NO barcode, and its `sku` is the
 * manufacturer item number (e.g. Rainbow Balloons' Sempertex "57102B", TufTex
 * "10021") — the join key our catalog stores in warehouse_sku or mfg_no, used by
 * the barcode-less rescue tier. Availability is read separately, from the same
 * JSON-LD Offer, by {@see JsonLdAvailabilityParser}.
 *
 * @phpstan-type MagentoParseResult array{raw_sku: string, upc: null, title: string, brand: ?string, price: ?float}
 */
class MagentoProductPageParser
{
    /**
     * @return MagentoParseResult|null null when the page carries no usable JSON-LD Product/sku
     */
    public function parse(string $html): ?array
    {
        $product = $this->firstJsonLdProduct($html);

        if ($product === null) {
            return null;
        }

        $sku = trim((string) ($product['sku'] ?? ''));

        if ($sku === '') {
            return null;
        }

        return [
            'raw_sku' => $sku,
            'upc' => null, // Magento product pages expose no barcode/GTIN
            'title' => trim((string) ($product['name'] ?? '')),
            'brand' => $this->brandName($product),
            'price' => $this->price($product),
        ];
    }

    /**
     * The first JSON-LD node whose @type is Product (Magento renders one per page).
     *
     * @return array<string, mixed>|null
     */
    private function firstJsonLdProduct(string $html): ?array
    {
        if (! preg_match_all('#<script[^>]*type="application/ld\+json"[^>]*>(.*?)</script>#is', $html, $matches)) {
            return null;
        }

        foreach ($matches[1] as $block) {
            $data = json_decode(trim($block), true);

            if (! is_array($data)) {
                continue;
            }

            foreach ($this->nodes($data) as $node) {
                if (is_array($node) && ($node['@type'] ?? null) === 'Product') {
                    return $node;
                }
            }
        }

        return null;
    }

    /**
     * A JSON-LD block may be a single node, a bare list, or an @graph wrapper.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, mixed>
     */
    private function nodes(array $data): array
    {
        if (isset($data['@graph']) && is_array($data['@graph'])) {
            return array_values($data['@graph']);
        }

        if (array_is_list($data)) {
            return $data;
        }

        return [$data];
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function brandName(array $product): ?string
    {
        $brand = $product['brand'] ?? null;

        if (is_array($brand)) {
            $brand = $brand['name'] ?? null;
        }

        $brand = trim((string) $brand);

        return $brand !== '' ? $brand : null;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function price(array $product): ?float
    {
        $offers = $product['offers'] ?? null;

        // A single Offer, or a list of them — take the first with a price.
        if (is_array($offers) && array_is_list($offers)) {
            $offers = $offers[0] ?? null;
        }

        $price = is_array($offers) ? ($offers['price'] ?? null) : null;

        return is_numeric($price) ? (float) $price : null;
    }
}
