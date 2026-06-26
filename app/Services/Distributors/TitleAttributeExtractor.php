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

        // Colour, for solid latex only. The title carries the real shade most
        // consistently (`11"S Metallic Green …`, `17"B Matte Blue #153 …`); the
        // breadcrumb leaf is a fallback — it is sometimes a colour-family grouping
        // ("Oranges"), a sale bucket ("CLEARANCE"), or absent.
        if ($material === 'Latex' && $theme === null) {
            $color = $this->colorFromTitle($title, $recipe) ?? $this->colorFromBreadcrumb($categories);
            if ($color !== null) {
                $attributes['Color'] = [$color];
            }
        }

        $size = $this->size($title);
        if ($size !== null) {
            $attributes['Size'] = [$size];
        }

        // Latex needs a shape so the matcher can build our shape-prefixed size
        // names (Round → `R-24`). havinaparty's latex is round unless the title
        // says otherwise; modeling sizes (160K) already resolve on their own and
        // are unaffected by the default.
        if ($material === 'Latex') {
            $attributes['Balloon Type / Shape'] = [$this->shape($title, $recipe)];
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
     * Colour from the title: strip the leading size+brand-letter code (`11"S`,
     * `160K`, `5"G`), the trailing "(N count)", any `#NNN` style code, and
     * packaging/shape words, leaving the colour/finish (`Mirror Silver`,
     * `Matte Blue`). Returns null when nothing remains.
     *
     * @param  array<string, mixed>  $recipe
     */
    private function colorFromTitle(string $title, array $recipe): ?string
    {
        $value = $title;
        // Leading size + brand-letter code: 11"S, 160K, 5"G, 34"O, 18-A, 260S.
        $value = preg_replace('/^\s*\d+\s*["”\'’]?\s*-?\s*[A-Za-z]{1,3}\b/u', '', $value) ?? $value;
        // Trailing "( … count … )".
        $value = preg_replace('/\([^)]*count[^)]*\)\s*$/i', '', $value) ?? $value;
        // Internal item codes like "#028".
        $value = preg_replace('/#\s*\d+/', '', $value) ?? $value;

        foreach ((array) ($recipe['color_strip_words'] ?? []) as $word) {
            $value = preg_replace('/\b'.preg_quote((string) $word, '/').'\b/i', '', $value) ?? $value;
        }

        $value = trim((string) preg_replace('/\s+/', ' ', $value));

        return $value !== '' ? $value : null;
    }

    /**
     * Colour from the breadcrumb leaf (`… > {Brand} Latex > Red Fashion`) — a
     * fallback when the title yields nothing. Skips brand/nav nodes.
     *
     * @param  array<int, string>  $categories
     */
    private function colorFromBreadcrumb(array $categories): ?string
    {
        if ($categories === []) {
            return null;
        }

        $leaf = (string) end($categories);

        if (preg_match('/\blatex\b/i', $leaf) === 1 || strcasecmp($leaf, 'Shop by Brand') === 0) {
            return null;
        }

        return $leaf !== '' ? $leaf : null;
    }

    /**
     * The balloon shape, defaulting to Round for latex (havinaparty's latex is
     * round unless the title names another shape via `shape_keywords`).
     *
     * @param  array<string, mixed>  $recipe
     */
    private function shape(string $title, array $recipe): string
    {
        $haystack = strtolower($title);

        foreach ((array) ($recipe['shape_keywords'] ?? []) as $keyword => $shape) {
            if ($keyword !== '' && str_contains($haystack, strtolower((string) $keyword))) {
                return (string) $shape;
            }
        }

        return (string) ($recipe['default_shape'] ?? 'Round');
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
