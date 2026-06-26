<?php

namespace App\Services\Distributors;

/**
 * Reads a product page's stock state from its schema.org JSON-LD `Offer`.
 *
 * Shopify storefronts expose no inventory field in their public `products.json`
 * (BargainBalloons' variant carries neither `available` nor `inventory_quantity`),
 * but the rendered product page embeds a JSON-LD `Product` whose
 * `offers.availability` (e.g. `http://schema.org/InStock`) is a reliable signal.
 *
 * Unlike the BigCommerce page parser — which deliberately distrusts JSON-LD
 * availability because some storefronts (havinaparty) hard-code `InStock` — this
 * is used only for stores verified to render an accurate availability value.
 */
class JsonLdAvailabilityParser
{
    /**
     * @return bool|null true = in stock, false = out of stock, null = unknown
     *                   (no JSON-LD Product, or no availability on its offers)
     */
    public function parse(string $html): ?bool
    {
        $product = $this->jsonLdProduct($html);

        if ($product === null) {
            return null;
        }

        $availabilities = $this->offerAvailabilities($product['offers'] ?? null);

        if ($availabilities === []) {
            return null;
        }

        // Any offer in stock wins; otherwise every known offer is unavailable.
        foreach ($availabilities as $availability) {
            if (str_contains(strtolower($availability), 'instock')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Collect the `availability` strings from an `offers` node, which schema.org
     * allows to be a single Offer object or a list of them.
     *
     * @return list<string>
     */
    private function offerAvailabilities(mixed $offers): array
    {
        if (! is_array($offers)) {
            return [];
        }

        if (isset($offers['availability'])) {
            return [(string) $offers['availability']];
        }

        $availabilities = [];

        foreach ($offers as $offer) {
            if (is_array($offer) && isset($offer['availability'])) {
                $availabilities[] = (string) $offer['availability'];
            }
        }

        return $availabilities;
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
}
