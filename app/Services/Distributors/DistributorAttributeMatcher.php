<?php

namespace App\Services\Distributors;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Color;
use App\Models\PackagingType;
use App\Services\CatalogAttributeResolver;
use App\Support\ProductText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Resolves a distributor's *structured* product attributes (the label/value table
 * read by {@see ProductAttributeTableExtractor}) to our catalog reference rows.
 *
 * This is the reliable counterpart to {@see CatalogAttributeResolver},
 * which infers attributes from free-text titles. Because the distributor hands us
 * clean fields ("Brand: Kalisan", "Size: 260", "Color: Clear"), we match
 * field-by-field — exact name → curated alias → fuzzy contains — and always return
 * the top few candidates plus a match quality, so an ambiguous field surfaces
 * options for a human to pick rather than a silent wrong guess.
 *
 * Size names in our catalog embed a brand suffix ("260K" for Kalisan, "11-inch (Q)"
 * for Qualatex) that the distributor's bare "Size: 260" never carries, so sizes are
 * matched on a normalised core key (digits + optional unit, brand letter stripped).
 *
 * @phpstan-type AttributeMatch array{
 *     model: \Illuminate\Database\Eloquent\Model|null,
 *     value: string|null,
 *     quality: 'exact'|'learned'|'fuzzy'|'none',
 *     candidates: array<int, array{id: string, name: string, quality: string}>
 * }
 */
class DistributorAttributeMatcher
{
    public function __construct(private DistributorLearnedAliasStore $aliasStore) {}

    private const MAX_CANDIDATES = 3;

    /**
     * Which distributor table label feeds each canonical attribute. A distributor
     * whose page uses different wording (e.g. "Manufacturer" / "Pack") overrides
     * these per key via config `extraction.label_map`, so the matcher itself stays
     * store-agnostic.
     */
    private const DEFAULT_LABELS = [
        'brand' => 'Brand',
        'size' => 'Size',
        'color' => 'Color',
        'count' => 'Quantity',
        'packaging' => 'Package Type',
        'shape' => 'Balloon Type / Shape',
        // The finish/texture line. Our colour names embed the finish ("Fashion
        // Yellow"), so when a distributor splits it out ("Manufacturer Color:
        // Yellow" + "Latex Finish: Fashion") we recompose it to match.
        'texture' => 'Latex Finish',
    ];

    /**
     * Shape word (lower-cased) → the prefix some brands put in front of a size's
     * number, e.g. Sempertex names its round 24-inch "R-24", its 14-inch heart
     * "C-14", and its link balloons "LOL-12". Used by {@see matchSize} to bridge a
     * distributor's bare "24 inch" + "Round" to those shape-prefixed size names.
     * A distributor whose catalogued brand uses a different scheme overrides this
     * via config `size_shape_prefixes`.
     */
    private const DEFAULT_SHAPE_PREFIXES = [
        'round' => 'R',
        'heart' => 'C',
        'link' => 'LOL',
        'link-o-loon' => 'LOL',
        'lol' => 'LOL',
    ];

    /** @var array{brands: Collection, balloonSizes: Collection, colors: Collection, packagingTypes: Collection}|null */
    private ?array $data = null;

    /** The distributor whose learned aliases apply to the current match() call. */
    private ?string $distributorId = null;

    /**
     * @param  array<string, array<int, string>>  $attributes  label → value(s) from the distributor table
     * @param  array<string, mixed>  $config  the distributor's config (`attribute_aliases`, `extraction.label_map`)
     * @param  string|null  $distributorId  whose learned aliases to consult (null skips the learned tier)
     * @return array{brand: AttributeMatch, balloon_size: AttributeMatch, color: AttributeMatch, packaging: AttributeMatch, count: int|null}
     */
    public function match(array $attributes, array $config = [], ?string $distributorId = null): array
    {
        $this->distributorId = $distributorId;
        $aliases = $config['attribute_aliases'] ?? [];
        $labels = array_merge(self::DEFAULT_LABELS, $config['extraction']['label_map'] ?? []);

        $brand = $this->matchBrand($this->value($attributes, $labels['brand']), $aliases);

        // Size and colour are brand-scoped, so without a brand there's nothing to
        // match them against. Packaging is a global attribute, so it resolves
        // independently of the brand.
        $brandModel = $brand['model'];

        $sizeValue = $this->value($attributes, $labels['size']);
        $shapeValues = $this->valuesFor($attributes, $labels['shape']);
        $shapePrefix = $this->resolveShapePrefix($shapeValues, $sizeValue, $config);

        return [
            'brand' => $brand,
            'balloon_size' => $brandModel instanceof Brand
                ? $this->matchSize($sizeValue, $brandModel, $shapeValues[0] ?? null, $shapePrefix, $this->sizeNumberAliases($brandModel->name, $config))
                : $this->none(),
            'color' => $brandModel instanceof Brand
                ? $this->matchColor(
                    $this->value($attributes, $labels['color']),
                    $this->value($attributes, $labels['texture']),
                    $brandModel,
                    $aliases,
                )
                : $this->none(),
            'packaging' => $this->matchPackaging($this->value($attributes, $labels['packaging']), $aliases),
            'count' => $this->parseCount($this->value($attributes, $labels['count'])),
        ];
    }

    /**
     * @param  array<string, mixed>  $aliases
     * @return AttributeMatch
     */
    private function matchBrand(?string $value, array $aliases): array
    {
        return $this->learned('brand', $value, null, $this->data()['brands'])
            ?? $this->matchAliased($value, $this->data()['brands'], $aliases['brand'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $aliases
     * @return AttributeMatch
     */
    private function matchPackaging(?string $value, array $aliases): array
    {
        return $this->learned('packaging', $value, null, $this->data()['packagingTypes'])
            ?? $this->matchAliased($value, $this->data()['packagingTypes'], $aliases['packaging'] ?? []);
    }

    /**
     * The learned-alias tier: a value an admin previously corrected resolves
     * straight to the catalog row they chose, ahead of every heuristic. It runs
     * FIRST (not merely before the fuzzy fallback) so a human decision overrides
     * even a spurious exact match — e.g. a distributor "Color: Yellow / Gold"
     * whose "Yellow" part exact-matches our generic Yellow, when the admin has
     * said this listing is really a specific shade. An alias pointing at a row
     * that's since been deleted is ignored, falling through to the heuristics.
     *
     * `$resultQuality` lets a caller keep the result from short-circuiting a
     * downstream safety net that only runs when quality isn't 'exact' (used by
     * colour: see {@see matchColor}). A raw colour value is very often a coarse
     * distributor category ("Blue", "Yellow / Gold") that legitimately means a
     * DIFFERENT specific shade on every other product that shares it, so a single
     * taught mapping must not be trusted as blindly as, say, a brand name is.
     *
     * @param  Collection<string, Model>  $pool  the candidate reference rows for this attribute/brand
     * @return AttributeMatch|null
     */
    private function learned(string $attribute, ?string $rawValue, ?string $brandId, Collection $pool, string $resultQuality = 'exact'): ?array
    {
        if ($rawValue === null || $this->distributorId === null) {
            return null;
        }

        $catalogId = $this->aliasStore->lookup($this->distributorId, $attribute, $brandId, $rawValue);

        if ($catalogId === null) {
            return null;
        }

        $model = $pool->first(fn (Model $m) => (string) $m->getKey() === $catalogId);

        if ($model === null) {
            return null;
        }

        return [
            'model' => $model,
            'value' => $rawValue,
            'quality' => $resultQuality,
            'candidates' => $this->candidates(collect([$model])),
        ];
    }

    /**
     * The raw distributor value the matcher reads for a canonical attribute, using
     * the same label resolution match() does (default label + per-distributor
     * `extraction.label_map` override). Shared with the review service so a learned
     * alias is captured under exactly the key the matcher will later look up.
     *
     * @param  array<string, array<int, string>>  $attributes
     * @param  array<string, mixed>  $config
     */
    public static function rawAttributeValue(array $attributes, array $config, string $attribute): ?string
    {
        $labels = array_merge(self::DEFAULT_LABELS, $config['extraction']['label_map'] ?? []);
        $label = $labels[$attribute] ?? null;

        if ($label === null) {
            return null;
        }

        foreach ($attributes as $key => $values) {
            if (strcasecmp($key, $label) === 0) {
                return $values[0] ?? null;
            }
        }

        return null;
    }

    /**
     * Resolve a value that may be a slash-combined family ("Gray / Silver",
     * "Yellow / Gold") by trying each part in turn, aliasing per part, and
     * returning the first that resolves. When none do, the gap is reported
     * against the original value so the admin sees what the distributor said.
     *
     * @param  Collection<string, Model>  $keyedByLowerName
     * @param  array<string, mixed>  $aliasMap
     * @return AttributeMatch
     */
    private function matchAliased(?string $value, Collection $keyedByLowerName, array $aliasMap): array
    {
        if ($value === null) {
            return $this->none();
        }

        foreach ($this->splitValue($value) as $part) {
            [$resolvedPart, $aliased] = $this->applyAlias($part, $aliasMap);
            $match = $this->resolve($resolvedPart, $keyedByLowerName, $aliased);

            if ($match['model'] !== null) {
                return $match;
            }
        }

        return $this->none($value);
    }

    /**
     * @param  array<string, mixed>  $numberAliases  distributor size number → catalog number, for this brand
     * @return AttributeMatch
     */
    private function matchSize(?string $value, Brand $brand, ?string $shapeWord = null, ?string $shapePrefix = null, array $numberAliases = []): array
    {
        if ($value === null) {
            return $this->none();
        }

        $sizes = $this->data()['balloonSizes']->get($brand->id, collect());

        if (($learned = $this->learned('size', $value, $brand->id, $sizes)) !== null) {
            return $learned;
        }

        // Tier 0 — shape+number named in the candidate itself. Several sizes can
        // share a bare number across shapes (round/heart/link), but sizeKey()
        // strips only KNOWN decorations (parentheticals, a trailing brand letter)
        // — it doesn't know how to strip an arbitrary shape suffix like Kalisan's
        // "12" K-Link", so Tier 1 below would find ONLY the (wrong) round variant
        // as a confident, unambiguous match and never get a chance to reconsider.
        // When the shape is known, look for a candidate whose own name mentions
        // BOTH it and the raw number (Sempertex's "{prefix}-{number}" convention,
        // Kalisan's "{number}\" {letter}-{Shape}" convention, ...) OR whose
        // catalog shape record matches the shape word directly — plain names like
        // "12-inch" carry no shape text at all (Kalisan's own "12-inch" is a
        // Heart, not a Round, despite the bare-sounding name), so the name-text
        // check alone would miss it.
        if ($shapeWord !== null) {
            foreach ($this->splitValue($value) as $part) {
                if (! preg_match('/\d+/', $part, $m)) {
                    continue;
                }

                $shapeMatches = $sizes->filter(function (BalloonSize $bs) use ($m, $shapeWord) {
                    if (! ProductText::mentions(strtolower($bs->name), $m[0])) {
                        return false;
                    }

                    return ProductText::mentions(strtolower($bs->name), strtolower($shapeWord))
                        || strtolower($bs->shape->name ?? '') === strtolower($shapeWord);
                })->values();

                if ($shapeMatches->count() === 1) {
                    return $this->sizeMatch($shapeMatches, $part, $value);
                }
            }
        }

        // Tier 1 — core-key equality. "350 / 360" style combined values: try each
        // part's core key in turn.
        foreach ($this->splitValue($value) as $part) {
            $key = $this->sizeKey($part);
            $matches = $sizes->filter(fn (BalloonSize $bs) => $this->sizeKey($bs->name) === $key)->values();

            if ($matches->isNotEmpty()) {
                return $this->sizeMatch($matches, $part, $value);
            }
        }

        // Tier 2 — shape-prefixed names. Some brands name a size by its shape and
        // number ("R-24", "C-14", "LOL-12") which Tier 1 can't reach from the
        // distributor's bare "24 inch". With the shape resolved to a prefix, look
        // for "{prefix}-{number}". The shape disambiguates what a bare number can't
        // (11-inch round vs heart vs link all share the number). A per-brand number
        // alias absorbs a marketing quirk first (Sempertex sells its code-12 / 30 cm
        // balloons as "11 inch", so 11 → 12 → R-12/C-12/LOL-12).
        if ($shapePrefix !== null) {
            $prefix = strtolower($shapePrefix);

            foreach ($this->splitValue($value) as $part) {
                if (! preg_match('/\d+/', $part, $m)) {
                    continue;
                }

                $number = (string) ($numberAliases[$m[0]] ?? $m[0]);
                $target = $prefix.'-'.$number;
                $matches = $sizes->filter(fn (BalloonSize $bs) => $this->prefixedSizeName($bs->name) === $target)->values();

                if ($matches->isNotEmpty()) {
                    return $this->sizeMatch($matches, $part, $value);
                }
            }
        }

        return $this->none($value);
    }

    /**
     * Per-brand size-number remap from config. Sempertex markets its code-12 /
     * 30 cm balloons as "11 inch" across shapes, so `{"Sempertex": {"11": "12"}}`
     * maps the distributor's 11 onto our R-12 / C-12 / LOL-12. Brand match is
     * case-insensitive; an empty map (the default) is a no-op.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function sizeNumberAliases(string $brandName, array $config): array
    {
        foreach ($config['size_number_aliases'] ?? [] as $brand => $map) {
            if (strcasecmp((string) $brand, $brandName) === 0) {
                return $map;
            }
        }

        return [];
    }

    /**
     * @param  Collection<int, BalloonSize>  $matches
     * @return AttributeMatch
     */
    private function sizeMatch(Collection $matches, string $part, string $value): array
    {
        return [
            'model' => $matches->first(),
            'value' => $part,
            // A single brand size for that key is unambiguous; several (e.g. round
            // vs heart at the same inch) need a human pick.
            'quality' => $matches->count() === 1 ? 'exact' : 'fuzzy',
            'candidates' => $this->candidates($matches),
        ];
    }

    /**
     * Resolve the shape a distributor reports to a size-name prefix. Reads the
     * structured shape field first, then falls back to a shape word embedded in
     * the size value itself ("660 LOL" carries no shape field but names the link
     * line). Returns null when no known shape word is present.
     *
     * @param  array<int, string>  $shapeValues
     * @param  array<string, mixed>  $config
     */
    private function resolveShapePrefix(array $shapeValues, ?string $sizeValue, array $config): ?string
    {
        /** @var array<string, string> $map */
        $map = array_change_key_case(
            $config['size_shape_prefixes'] ?? self::DEFAULT_SHAPE_PREFIXES,
            CASE_LOWER,
        );

        foreach ($shapeValues as $shape) {
            $key = strtolower(trim($shape));

            if (isset($map[$key])) {
                return $map[$key];
            }
        }

        if ($sizeValue !== null) {
            $haystack = strtolower($sizeValue);

            foreach ($map as $word => $prefix) {
                if (preg_match('/\b'.preg_quote($word, '/').'\b/', $haystack)) {
                    return $prefix;
                }
            }
        }

        return null;
    }

    /**
     * A size name reduced to its bare "{prefix}-{number}" form for shape-prefix
     * matching: lower-cased, parentheticals ("C-14 (S)" → "c-14") and whitespace
     * removed.
     */
    private function prefixedSizeName(string $name): string
    {
        $s = strtolower(trim($name));
        $s = preg_replace('/\(.*?\)/', '', $s) ?? $s;

        return preg_replace('/\s+/', '', $s) ?? $s;
    }

    /**
     * Our colour names embed the finish ("Fashion Yellow", "Reflex Silver"). When a
     * distributor splits the finish into its own field ("Manufacturer Color: Yellow"
     * + "Latex Finish: Fashion"), recompose "{finish} {colour}" and try that first —
     * it picks the right finish variant instead of fuzzy-matching some other yellow —
     * then fall back to the base colour alone.
     *
     * @param  array<string, mixed>  $aliases
     * @return AttributeMatch
     */
    private function matchColor(?string $value, ?string $finish, Brand $brand, array $aliases): array
    {
        $colors = $this->data()['colors']->get($brand->id, collect());
        $aliasMap = $aliases['color'] ?? [];

        // A learned alias is keyed on the raw colour value the distributor sent
        // (the same field the admin saw when correcting it), so it's consulted on
        // $value before the finish recompose / structured / title heuristics.
        // Quality 'learned' (not 'exact') deliberately keeps the door open for the
        // title-shade override below/upstream (ProposalResolver, presentColor) —
        // a raw colour word is often a coarse category that means a different
        // shade per product, so an unambiguous shade named in THIS product's own
        // title is stronger evidence than a mapping taught from a different one.
        if (($learned = $this->learned('color', $value, $brand->id, $colors, 'learned')) !== null) {
            return $learned;
        }

        if ($value !== null && $finish !== null && trim($finish) !== '') {
            $combined = $this->matchAliased(trim($finish).' '.trim($value), $colors, $aliasMap);

            if ($combined['model'] !== null) {
                return $combined;
            }
        }

        return $this->matchAliased($value, $colors, $aliasMap);
    }

    /**
     * Exact (or aliased) name match first, else a fuzzy contains match in either
     * direction ("Clear" ⊂ "Clear Transparent"), returning the closest few.
     *
     * @param  Collection<string, Model>  $keyedByLowerName
     * @return AttributeMatch
     */
    private function resolve(string $value, Collection $keyedByLowerName, bool $aliased): array
    {
        $needle = strtolower(trim($value));

        if ($keyedByLowerName->has($needle)) {
            $model = $keyedByLowerName->get($needle);

            return [
                'model' => $model,
                'value' => $value,
                'quality' => 'exact',
                'candidates' => $this->candidates(collect([$model])),
            ];
        }

        // An alias that doesn't resolve is a config error, not a fuzzy guess —
        // surface nothing rather than a misleading partial.
        $fuzzy = $aliased
            ? collect()
            : $keyedByLowerName
                ->filter(fn ($model, string $name) => str_contains($name, $needle) || str_contains($needle, $name))
                ->sortBy(fn ($model, string $name) => abs(strlen($name) - strlen($needle)))
                ->values();

        if ($fuzzy->isEmpty()) {
            return $this->none($value);
        }

        return [
            'model' => $fuzzy->first(),
            'value' => $value,
            'quality' => 'fuzzy',
            'candidates' => $this->candidates($fuzzy),
        ];
    }

    /**
     * @param  Collection<int, Model>  $models
     * @return array<int, array{id: string, name: string, quality: string}>
     */
    private function candidates(Collection $models): array
    {
        return $models->take(self::MAX_CANDIDATES)
            ->map(fn ($model) => ['id' => $model->id, 'name' => $model->name, 'quality' => 'candidate'])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $map
     * @return array{0: string, 1: bool} [resolved value, whether an alias applied]
     */
    private function applyAlias(string $value, array $map): array
    {
        $lower = strtolower(trim($value));

        foreach ($map as $from => $to) {
            if (strtolower((string) $from) === $lower) {
                return [(string) $to, true];
            }
        }

        return [$value, false];
    }

    /**
     * Normalised size comparison key: lower-cased, parentheticals removed, inch
     * notation canonicalised, and a trailing brand letter ("260K" → "260")
     * stripped — but never the "in" unit ("11in" stays "11in").
     */
    private function sizeKey(string $value): string
    {
        $s = strtolower(trim($value));
        $s = preg_replace('/\(.*?\)/', '', $s) ?? $s;
        $s = ProductText::normalizeSizeTokens($s);
        $s = preg_replace('/\s+/', '', $s) ?? $s;

        // A bare number, with or without a trailing ".0" (e.g. bargain-balloons'
        // "Size (inches)" field reports "5.0", "12.0" — no inch mark, no word),
        // carries no unit at all but the label already tells us it's inches.
        // Capped under 100: modeling-balloon codes ("260", "350", "660") are
        // also bare numbers in the catalog but aren't inch counts, and every
        // real inch size tops out in the 30s. The ".0" is stripped either way so
        // a modeling code sent as "260.0" still matches the catalog's own "260".
        if (preg_match('/^(\d+)(?:\.0+)?$/', $s, $m)) {
            return (int) $m[1] < 100 ? $m[1].'in' : $m[1];
        }

        if (preg_match('/^(\d+)[a-z]$/', $s, $m)) {
            return $m[1];
        }

        return $s;
    }

    /**
     * Split a slash-combined value ("Gray / Silver", "350 / 360") into its parts;
     * a plain value yields a single-element list.
     *
     * @return array<int, string>
     */
    private function splitValue(string $value): array
    {
        $parts = array_values(array_filter(
            array_map('trim', preg_split('#\s*/\s*#', $value) ?: [$value]),
            fn (string $p) => $p !== '',
        ));

        return $parts === [] ? [$value] : $parts;
    }

    private function parseCount(?string $value): ?int
    {
        if ($value !== null && preg_match('/\d+/', $value, $m)) {
            return (int) $m[0];
        }

        return null;
    }

    /**
     * @param  array<string, array<int, string>>  $attributes
     */
    private function value(array $attributes, string $label): ?string
    {
        foreach ($attributes as $key => $values) {
            if (strcasecmp($key, $label) === 0) {
                return $values[0] ?? null;
            }
        }

        return null;
    }

    /**
     * All values for a label (the shape field carries several, e.g.
     * ["Solid Color", "Round"]), case-insensitive on the label.
     *
     * @param  array<string, array<int, string>>  $attributes
     * @return array<int, string>
     */
    private function valuesFor(array $attributes, string $label): array
    {
        foreach ($attributes as $key => $values) {
            if (strcasecmp($key, $label) === 0) {
                return array_values($values);
            }
        }

        return [];
    }

    /**
     * @return AttributeMatch
     */
    private function none(?string $value = null): array
    {
        return ['model' => null, 'value' => $value, 'quality' => 'none', 'candidates' => []];
    }

    /**
     * @return array{brands: Collection, balloonSizes: Collection, colors: Collection, packagingTypes: Collection}
     */
    private function data(): array
    {
        return $this->data ??= [
            'brands' => Brand::all()->keyBy(fn (Brand $b) => strtolower($b->name)),
            'balloonSizes' => BalloonSize::with('shape')->get()->groupBy('brand_id'),
            'colors' => Color::all()
                ->groupBy('brand_id')
                ->map(fn (Collection $group) => $group->keyBy(fn (Color $c) => strtolower($c->name))),
            // Packaging types are global (not brand-scoped).
            'packagingTypes' => PackagingType::all()->keyBy(fn (PackagingType $p) => strtolower($p->name)),
        ];
    }
}
