<?php

namespace App\Services\Distributors;

/**
 * Reads a distributor product page's structured attribute table into a normalised
 * label → value(s) map, driven by a per-distributor "extraction recipe" stored in
 * the distributor's config. This is the distributor telling us, in their own
 * words, what the product is (Brand, Material, Size, Colour, Theme, …) — far more
 * reliable than parsing the page title.
 *
 * The recipe lives at config `extraction.attribute_table` and declares the CSS
 * classes of the label/value cells, plus the labels we require for the page to be
 * trusted. Validating every page against that recipe is also what lets us detect
 * when a site changes its template (a sudden drop in matched rows / required
 * labels) — see {@see extract()}'s returned `ok` / `missing_required`.
 *
 * Example recipe (Larocks):
 *   'extraction' => [
 *     'attribute_table' => ['header_class' => 'productView-table-header', 'value_class' => 'productView-table-data'],
 *     'required_labels' => ['Brand', 'Industry'],
 *     'min_rows' => 4,
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
class ProductAttributeTableExtractor
{
    /**
     * @param  array<string, mixed>  $config  the distributor's config
     * @return ExtractionResult
     */
    public function extract(string $html, array $config): array
    {
        $recipe = $config['extraction']['attribute_table'] ?? null;

        if (! is_array($recipe)) {
            return $this->emptyResult(hasRecipe: false);
        }

        $headerClass = $recipe['header_class'] ?? 'productView-table-header';
        $valueClass = $recipe['value_class'] ?? 'productView-table-data';

        $attributes = $this->scan($html, $headerClass, $valueClass);
        $rowCount = array_sum(array_map('count', $attributes));

        $required = $config['extraction']['required_labels'] ?? [];
        $minRows = (int) ($config['extraction']['min_rows'] ?? 0);
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
     * Pull every adjacent label/value cell pair. Repeated labels (Larocks emits
     * two "Balloon Type / Shape" rows) are collected as a list rather than
     * overwriting, so no information is lost.
     *
     * @return array<string, array<int, string>>
     */
    private function scan(string $html, string $headerClass, string $valueClass): array
    {
        $pattern = '#class="[^"]*'.preg_quote($headerClass, '#').'[^"]*"[^>]*>(.*?)</div>\s*'
            .'<div[^>]*class="[^"]*'.preg_quote($valueClass, '#').'[^"]*"[^>]*>(.*?)</div>#is';

        if (! preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $attributes = [];

        foreach ($matches as $match) {
            $label = $this->clean($match[1]);
            $label = rtrim($label, ': ');
            $value = $this->clean($match[2]);

            if ($label === '' || $value === '') {
                continue;
            }

            $attributes[$label][] = $value;
        }

        return $attributes;
    }

    /**
     * Required labels absent from the page — drives the per-page trust flag and,
     * in aggregate, template-drift detection.
     *
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

    private function clean(string $raw): string
    {
        $text = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
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
