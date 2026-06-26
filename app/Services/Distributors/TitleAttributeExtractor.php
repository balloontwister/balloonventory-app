<?php

namespace App\Services\Distributors;

use App\Services\DistributorPlatforms\BigCommerceProductPageParser;

/**
 * Derives a product's attributes for BigCommerce stores that render NO structured
 * attribute table (havinaparty), emitting the SAME canonical label → value(s) map
 * that {@see ProductAttributeTableExtractor} and {@see ShopifyTagAttributeExtractor}
 * produce — so the classifier, matcher, clustering and accuracy gate run unchanged.
 *
 * Two page sources (both parsed by {@see BigCommerceProductPageParser}):
 *  - the JSON-LD **BreadcrumbList** category path — authoritative for material and
 *    colour, e.g. `Latex Balloons > Shop by Brand > Sempertex Latex > Red Fashion`;
 *  - the **title** (`11"S Red Fashion (100 count)`) — for size + pack count;
 *  - the JSON-LD **brand**.
 *
 * Title keywords (foil signals, latex brands) are a fallback only when the
 * breadcrumb is absent. Getting the type right matters beyond proposals: a
 * cluster's type is taken from its first classified member, so a mis-typed
 * havinaparty row could otherwise demote a real solid-latex cluster shared with
 * another distributor.
 *
 * Recipe at config `extraction.title_attributes`:
 *   'title_attributes' => [
 *     // breadcrumb top-category substring => Balloon Material (primary signal)
 *     'category_material_map' => ['Latex Balloons' => 'Latex', 'Foil Balloons' => 'Foil', 'Mylar' => 'Foil', 'Bubble' => 'Plastic'],
 *     // breadcrumb nodes that mark a printed product (their spelling)
 *     'printed_categories' => ['Printed', 'Special Occassion', 'Shop by Prints'],
 *     // FALLBACKS when no breadcrumb:
 *     'foil_keywords' => ['air-fill', 'foil', 'mylar', 'orbz', 'sphere'],
 *     'latex_brands' => ['Sempertex', 'Kalisan', 'Tuftex', 'Qualatex'],
 *     'printed_keywords' => ['happy birthday', 'christmas'],
 *     'required_labels' => ['Balloon Material'],
 *     'min_rows' => 1,
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
     * @param  array<string, mixed>  $parsed  parsed page fields (title, brand, categories)
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
        /** @var array<int, string> $categories */
        $categories = array_values(array_filter(array_map(
            fn ($c) => trim((string) $c),
            (array) ($parsed['categories'] ?? []),
        )));

        if ($title === '') {
            return $this->emptyResult(hasRecipe: true);
        }

        $attributes = [];

        if ($brand !== '') {
            $attributes['Brand'] = [$brand];
        }

        $material = $this->material($categories, $title, $brand, $recipe);
        if ($material !== null) {
            $attributes['Balloon Material'] = [$material];
        }

        // Themed/printed latex → the classifier reads Occasion / Theme.
        $theme = $this->theme($categories, $title, $recipe);
        if ($theme !== null) {
            $attributes['Occasion / Theme'] = [$theme];
        }

        // Colour comes from the breadcrumb leaf for solid latex (`… > Sempertex
        // Latex > Red Fashion`); foil/printed paths carry a theme node instead.
        $color = $this->color($categories, $material, $theme);
        if ($color !== null) {
            $attributes['Color'] = [$color];
        }

        $size = $this->size($title);
        if ($size !== null) {
            $attributes['Size'] = [$size];
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
     * Latex vs foil. The breadcrumb's top category is authoritative; fall back to
     * title foil-keywords (a latex brand can still sell foil letters) then a latex
     * brand default. Returns null when nothing is determinable.
     *
     * @param  array<int, string>  $categories
     * @param  array<string, mixed>  $recipe
     */
    private function material(array $categories, string $title, string $brand, array $recipe): ?string
    {
        $top = $categories[0] ?? '';
        foreach ((array) ($recipe['category_material_map'] ?? []) as $needle => $material) {
            if ($needle !== '' && stripos($top, (string) $needle) !== false) {
                return (string) $material;
            }
        }

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
     * A printed-theme signal from a breadcrumb node, then a title keyword. Null
     * when the product is solid.
     *
     * @param  array<int, string>  $categories
     * @param  array<string, mixed>  $recipe
     */
    private function theme(array $categories, string $title, array $recipe): ?string
    {
        foreach ($categories as $category) {
            foreach ((array) ($recipe['printed_categories'] ?? []) as $needle) {
                if ($needle !== '' && stripos($category, (string) $needle) !== false) {
                    return $category;
                }
            }
        }

        $haystack = strtolower($title);
        foreach ((array) ($recipe['printed_keywords'] ?? []) as $keyword) {
            if ($keyword !== '' && str_contains($haystack, strtolower((string) $keyword))) {
                return (string) $keyword;
            }
        }

        return null;
    }

    /**
     * Colour from the breadcrumb leaf, only for solid latex — the leaf is the
     * colour node (`Red Fashion`) under `… > {Brand} Latex`. Skips brand/nav nodes
     * and anything already flagged as a theme.
     *
     * @param  array<int, string>  $categories
     */
    private function color(array $categories, ?string $material, ?string $theme): ?string
    {
        if ($material !== 'Latex' || $theme !== null || $categories === []) {
            return null;
        }

        $leaf = (string) end($categories);

        // A "{Brand} Latex" or "Shop by Brand" navigation node is not a colour.
        if (preg_match('/\blatex\b/i', $leaf) === 1 || strcasecmp($leaf, 'Shop by Brand') === 0) {
            return null;
        }

        return $leaf !== '' ? $leaf : null;
    }

    /**
     * The leading size number of the title (`11"S …` → 11, `160K …` → 160).
     */
    private function size(string $title): ?string
    {
        if (preg_match('/^\s*(\d+)/', $title, $m) === 1) {
            return $m[1];
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
