<?php

namespace App\Services\Distributors;

/**
 * Reads a Shopify product's structured attributes from its `products.json` data —
 * namespaced tags (`Color_Teal`, `Size_11" Latex`, `Packaging_Packaged`) plus the
 * `product_type` — into the SAME canonical label → value(s) map that
 * {@see ProductAttributeTableExtractor} produces from an HTML table. Because the
 * output shape matches, the classifier, matcher, clustering and accuracy gate all
 * work unchanged downstream.
 *
 * Unlike the page extractors this needs NO HTML fetch: the bulk products.json
 * already carries the tags + vendor + product_type, so a whole catalog's
 * attributes come from the bulk feed. (The barcode is the only thing Shopify
 * withholds from the bulk feed — the caller fetches that separately.) Brand (from
 * the JSON vendor) and Shape are injected by the caller, exactly as for the HTML
 * Shopify path, so this extractor only handles the tag/product_type fields.
 *
 * The recipe lives at config `extraction.tag_attributes`:
 *   'extraction' => [
 *     'tag_attributes' => [
 *       // tag prefix => our canonical label
 *       'tag_map' => [
 *         'Color_' => 'Color',
 *         'Size_' => 'Size',
 *         'Packaging_' => 'Package Type',
 *         'Theme_' => 'Occasion / Theme',
 *       ],
 *       // product_type substring => Balloon Material value (drives classification)
 *       'product_type_map' => ['latex' => 'Latex', 'foil' => 'Foil', 'mylar' => 'Foil'],
 *       // words stripped from every tag value (e.g. "11\" Latex" => "11\"")
 *       'strip_words' => ['Latex', 'Foil', 'Mylar', 'Bubble'],
 *       'required_labels' => ['Color', 'Size'],
 *       'min_rows' => 2,
 *     ],
 *   ]
 *
 * @phpstan-type ExtractionResult array{
 *     has_recipe: bool,
 *     attributes: array<string, array<int, string>>,
 *     row_count: int,
 *     missing_required: array<int, string>,
 *     ok: bool
 * }
 */
class ShopifyTagAttributeExtractor
{
    /**
     * @param  array<string, mixed>  $product  one product from products.json (tags, product_type)
     * @param  array<string, mixed>  $config  the distributor's config
     * @return ExtractionResult
     */
    public function extract(array $product, array $config): array
    {
        $recipe = $config['extraction']['tag_attributes'] ?? null;

        if (! is_array($recipe)) {
            return $this->emptyResult(hasRecipe: false);
        }

        $tagMap = $recipe['tag_map'] ?? [];
        $stripWords = $recipe['strip_words'] ?? [];

        $attributes = [];

        foreach ($this->tags($product) as $tag) {
            foreach ($tagMap as $prefix => $label) {
                if (stripos($tag, (string) $prefix) === 0) {
                    $value = $this->clean(substr($tag, strlen((string) $prefix)), $stripWords);

                    if ($value !== '') {
                        $attributes[$label][] = $value;
                    }

                    break;
                }
            }
        }

        // product_type → Balloon Material, so the shared classifier can read it.
        $material = $this->materialFromProductType((string) ($product['product_type'] ?? ''), $recipe['product_type_map'] ?? []);

        if ($material !== null) {
            $attributes['Balloon Material'] = [$material];
        }

        $rowCount = array_sum(array_map('count', $attributes));
        $required = $recipe['required_labels'] ?? [];
        $minRows = (int) ($recipe['min_rows'] ?? 0);
        $missing = $this->missingRequired($attributes, $required);

        return [
            'has_recipe' => true,
            'attributes' => $attributes,
            'row_count' => $rowCount,
            'missing_required' => $missing,
            'ok' => $rowCount >= $minRows && $missing === [],
        ];
    }

    /**
     * The product's tags as a clean list — Shopify reports them as an array here,
     * but tolerate the comma-string form too.
     *
     * @param  array<string, mixed>  $product
     * @return array<int, string>
     */
    private function tags(array $product): array
    {
        $tags = $product['tags'] ?? [];

        if (is_string($tags)) {
            $tags = explode(',', $tags);
        }

        return array_values(array_filter(array_map(fn ($t) => trim((string) $t), (array) $tags)));
    }

    /**
     * Map the Shopify product_type to a Balloon Material value via the recipe's
     * substring map (e.g. "Latex Balloons" => "Latex"). Null when nothing matches.
     *
     * @param  array<string, mixed>  $map
     */
    private function materialFromProductType(string $productType, array $map): ?string
    {
        $haystack = strtolower($productType);

        foreach ($map as $needle => $material) {
            if ($needle !== '' && str_contains($haystack, strtolower((string) $needle))) {
                return (string) $material;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $stripWords
     */
    private function clean(string $value, array $stripWords): string
    {
        $value = trim($value);

        foreach ($stripWords as $word) {
            // Remove the word as a whole token (so "11\" Latex" => "11\"", but a
            // colour like "Latex Blue" — unlikely — would only lose the word).
            $value = preg_replace('/\b'.preg_quote((string) $word, '/').'\b/i', '', $value) ?? $value;
        }

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    /**
     * @param  array<string, array<int, string>>  $attributes
     * @param  array<int, string>  $required
     * @return array<int, string>
     */
    private function missingRequired(array $attributes, array $required): array
    {
        $present = array_map('strtolower', array_keys($attributes));

        return array_values(array_filter(
            $required,
            fn (string $label) => ! in_array(strtolower($label), $present, true),
        ));
    }

    /**
     * @return ExtractionResult
     */
    private function emptyResult(bool $hasRecipe): array
    {
        return [
            'has_recipe' => $hasRecipe,
            'attributes' => [],
            'row_count' => 0,
            'missing_required' => [],
            'ok' => false,
        ];
    }
}
