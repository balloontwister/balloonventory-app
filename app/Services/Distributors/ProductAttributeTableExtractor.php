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
 * Three recipe shapes are supported:
 *   - `attribute_table` (Larocks): adjacent label/value cells identified by CSS class.
 *   - `attribute_list` (BargainBalloons): a `<ul>` of `<li><span>Label: </span>Value</li>`,
 *     optionally anchored to a `section_marker` so only the spec list is read.
 *   - `attribute_rows` (Joker Party Supply): a plain two-column `<table>` of
 *     `<tr><td>Label</td><td>Value</td></tr>` rows (no CSS classes), optionally
 *     anchored to a `section_marker` (e.g. the "Product Information" header) so only
 *     the spec table is read. The `<thead>` uses `<th>`, so it's skipped naturally.
 *
 * Example recipe (Larocks):
 *   'extraction' => [
 *     'attribute_table' => ['header_class' => 'productView-table-header', 'value_class' => 'productView-table-data'],
 *     'required_labels' => ['Brand', 'Industry'],
 *     'min_rows' => 4,
 *   ]
 *
 * Example recipe (BargainBalloons):
 *   'extraction' => [
 *     'attribute_list' => ['section_marker' => 'Additional Product Details'],
 *     'required_labels' => ['Manufacturer Color', 'Latex Finish', 'Package Count'],
 *     'min_rows' => 5,
 *   ]
 *
 * Example recipe (Joker Party Supply):
 *   'extraction' => [
 *     'attribute_rows' => ['section_marker' => 'Product Information'],
 *     'required_labels' => ['Brand', 'Size', 'Material'],
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
        $tableRecipe = $config['extraction']['attribute_table'] ?? null;
        $listRecipe = $config['extraction']['attribute_list'] ?? null;
        $rowsRecipe = $config['extraction']['attribute_rows'] ?? null;

        if (is_array($tableRecipe)) {
            $attributes = $this->scan(
                $html,
                $tableRecipe['header_class'] ?? 'productView-table-header',
                $tableRecipe['value_class'] ?? 'productView-table-data',
            );
        } elseif (is_array($listRecipe)) {
            $attributes = $this->scanList($html, $listRecipe);
        } elseif (is_array($rowsRecipe)) {
            $attributes = $this->scanRows($html, $rowsRecipe);
        } else {
            return $this->emptyResult(hasRecipe: false);
        }

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
     * Pull `<li><span>Label: </span>Value</li>` pairs from a spec list. An optional
     * `section_marker` narrows scanning to the list that follows it (up to the next
     * `</ul>`), so unrelated `<li>` elsewhere on the page (nav, related products)
     * can't leak in. Repeated labels are collected as a list, like {@see scan()}.
     *
     * @param  array<string, mixed>  $recipe
     * @return array<string, array<int, string>>
     */
    private function scanList(string $html, array $recipe): array
    {
        $marker = $recipe['section_marker'] ?? null;

        if ($marker !== null) {
            $start = stripos($html, (string) $marker);

            if ($start === false) {
                return [];
            }

            $end = stripos($html, '</ul>', $start);
            $html = substr($html, $start, $end !== false ? $end - $start : 5000);
        }

        if (! preg_match_all('#<li[^>]*>\s*<span[^>]*>(.*?)</span>(.*?)</li>#is', $html, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $attributes = [];

        foreach ($matches as $match) {
            $label = rtrim($this->clean($match[1]), ': ');
            $value = $this->clean($match[2]);

            if ($label === '' || $value === '') {
                continue;
            }

            $attributes[$label][] = $value;
        }

        return $attributes;
    }

    /**
     * Pull `<tr><td>Label</td><td>Value</td></tr>` pairs from a plain two-column
     * table (Joker Party Supply's `body_html` "Product Information" table, which
     * carries no CSS classes). An optional `section_marker` narrows scanning to the
     * table that follows it (up to the next `</table>`) so unrelated tables in the
     * body can't leak in. The header row uses `<th>`, so the `<td>` pattern skips it.
     * Repeated labels are collected as a list, like {@see scan()}.
     *
     * @param  array<string, mixed>  $recipe
     * @return array<string, array<int, string>>
     */
    private function scanRows(string $html, array $recipe): array
    {
        $marker = $recipe['section_marker'] ?? null;

        if ($marker !== null) {
            $start = stripos($html, (string) $marker);

            if ($start === false) {
                return [];
            }

            $end = stripos($html, '</table>', $start);
            $html = substr($html, $start, $end !== false ? $end - $start : 5000);
        }

        if (! preg_match_all('#<tr[^>]*>\s*<td[^>]*>(.*?)</td>\s*<td[^>]*>(.*?)</td>#is', $html, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $attributes = [];

        foreach ($matches as $match) {
            $label = rtrim($this->clean($match[1]), ': ');
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
