<?php

namespace App\Services\Distributors;

/**
 * Derives a product's attributes from its TITLE + brand, for BigCommerce stores
 * that render NO structured attribute table (havinaparty: identity comes from
 * BCData/JSON-LD, but size/colour/type live only in the title like
 * `11"S Red Fashion (100 count)`). Emits the SAME canonical label → value(s) map
 * that {@see ProductAttributeTableExtractor} and {@see ShopifyTagAttributeExtractor}
 * produce, so the classifier, matcher, clustering and accuracy gate run unchanged.
 *
 * This first increment resolves the fields needed to CLASSIFY the product
 * correctly — Brand, Balloon Material (latex vs foil), a printed-theme signal, and
 * Quantity. Getting the type right matters beyond proposals: a cluster's type is
 * taken from its first classified member, so a mis-typed havinaparty row could
 * otherwise demote a real solid-latex cluster shared with another distributor.
 * Size/colour parsing from the title code system (`11"S`, `160K`) is a later
 * increment; until then those products resolve on brand/count + a sibling's
 * attributes.
 *
 * The recipe lives at config `extraction.title_attributes`:
 *   'extraction' => [
 *     'title_attributes' => [
 *       // title substrings (case-insensitive) that mark a FOIL product
 *       'foil_keywords' => ['air-fill', 'air fill', 'foil', 'mylar', 'orbz', 'sphere'],
 *       // brands whose latex line is the default when no foil signal is present
 *       'latex_brands' => ['Sempertex', 'Kalisan', 'Tuftex', 'Qualatex'],
 *       // title substrings that mark a PRINTED (themed) latex product
 *       'printed_keywords' => ['happy birthday', 'christmas', 'halloween'],
 *       'required_labels' => ['Balloon Material'],
 *       'min_rows' => 1,
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
class TitleAttributeExtractor
{
    /**
     * @param  array<string, mixed>  $parsed  parsed page fields (title, brand) from BigCommerceProductPageParser
     * @param  array<string, mixed>  $config  the distributor's config
     * @return ExtractionResult
     */
    public function extract(array $parsed, array $config): array
    {
        $recipe = $config['extraction']['title_attributes'] ?? null;

        if (! is_array($recipe)) {
            return $this->emptyResult(hasRecipe: false);
        }

        $title = trim((string) ($parsed['title'] ?? ''));
        $brand = trim((string) ($parsed['brand'] ?? ''));

        if ($title === '') {
            return $this->emptyResult(hasRecipe: true);
        }

        $attributes = [];

        if ($brand !== '') {
            $attributes['Brand'] = [$brand];
        }

        $material = $this->material($title, $brand, $recipe);
        if ($material !== null) {
            $attributes['Balloon Material'] = [$material];
        }

        // A themed latex product is printed; the classifier reads Occasion / Theme.
        $theme = $this->theme($title, $recipe);
        if ($theme !== null) {
            $attributes['Occasion / Theme'] = [$theme];
        }

        $count = $this->count($title);
        if ($count !== null) {
            $attributes['Quantity'] = [$count];
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
     * Latex vs foil. Foil signals (air-fill, letters/numbers, shapes) win first —
     * a latex brand can still sell foil letters — then a latex brand defaults to
     * latex. Returns null when neither is determinable.
     *
     * @param  array<string, mixed>  $recipe
     */
    private function material(string $title, string $brand, array $recipe): ?string
    {
        $haystack = strtolower($title);

        foreach ((array) ($recipe['foil_keywords'] ?? []) as $keyword) {
            if ($keyword !== '' && str_contains($haystack, strtolower((string) $keyword))) {
                return 'Foil';
            }
        }

        foreach ((array) ($recipe['latex_brands'] ?? []) as $latexBrand) {
            if ($latexBrand !== '' && strcasecmp($brand, (string) $latexBrand) === 0) {
                return 'Latex';
            }
        }

        return null;
    }

    /**
     * The first printed-theme keyword present in the title, or null.
     *
     * @param  array<string, mixed>  $recipe
     */
    private function theme(string $title, array $recipe): ?string
    {
        $haystack = strtolower($title);

        foreach ((array) ($recipe['printed_keywords'] ?? []) as $keyword) {
            if ($keyword !== '' && str_contains($haystack, strtolower((string) $keyword))) {
                return (string) $keyword;
            }
        }

        return null;
    }

    /**
     * Pack count from a trailing "(N count)" / "N-count" in the title.
     */
    private function count(string $title): ?string
    {
        if (preg_match('/(\d+)\s*[-\s]?count/i', $title, $m) === 1) {
            return $m[1];
        }

        return null;
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
