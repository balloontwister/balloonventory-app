<?php

namespace App\Services\Distributors;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Color;
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
 *     quality: 'exact'|'fuzzy'|'none',
 *     candidates: array<int, array{id: string, name: string, quality: string}>
 * }
 */
class DistributorAttributeMatcher
{
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
    ];

    /** @var array{brands: Collection, balloonSizes: Collection, colors: Collection}|null */
    private ?array $data = null;

    /**
     * @param  array<string, array<int, string>>  $attributes  label → value(s) from the distributor table
     * @param  array<string, mixed>  $config  the distributor's config (`attribute_aliases`, `extraction.label_map`)
     * @return array{brand: AttributeMatch, balloon_size: AttributeMatch, color: AttributeMatch, count: int|null}
     */
    public function match(array $attributes, array $config = []): array
    {
        $aliases = $config['attribute_aliases'] ?? [];
        $labels = array_merge(self::DEFAULT_LABELS, $config['extraction']['label_map'] ?? []);

        $brand = $this->matchBrand($this->value($attributes, $labels['brand']), $aliases);

        // Size and colour are brand-scoped, so without a brand there's nothing to
        // match them against.
        $brandModel = $brand['model'];

        return [
            'brand' => $brand,
            'balloon_size' => $brandModel instanceof Brand
                ? $this->matchSize($this->value($attributes, $labels['size']), $brandModel)
                : $this->none(),
            'color' => $brandModel instanceof Brand
                ? $this->matchColor($this->value($attributes, $labels['color']), $brandModel, $aliases)
                : $this->none(),
            'count' => $this->parseCount($this->value($attributes, $labels['count'])),
        ];
    }

    /**
     * @param  array<string, mixed>  $aliases
     * @return AttributeMatch
     */
    private function matchBrand(?string $value, array $aliases): array
    {
        return $this->matchAliased($value, $this->data()['brands'], $aliases['brand'] ?? []);
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
     * @return AttributeMatch
     */
    private function matchSize(?string $value, Brand $brand): array
    {
        if ($value === null) {
            return $this->none();
        }

        $sizes = $this->data()['balloonSizes']->get($brand->id, collect());

        // "350 / 360" style combined values: try each part's core key in turn.
        foreach ($this->splitValue($value) as $part) {
            $key = $this->sizeKey($part);
            $matches = $sizes->filter(fn (BalloonSize $bs) => $this->sizeKey($bs->name) === $key)->values();

            if ($matches->isNotEmpty()) {
                return [
                    'model' => $matches->first(),
                    'value' => $part,
                    // A single brand size for that core key is unambiguous; several
                    // (e.g. round vs heart at the same inch) need a human pick.
                    'quality' => $matches->count() === 1 ? 'exact' : 'fuzzy',
                    'candidates' => $this->candidates($matches),
                ];
            }
        }

        return $this->none($value);
    }

    /**
     * @param  array<string, mixed>  $aliases
     * @return AttributeMatch
     */
    private function matchColor(?string $value, Brand $brand, array $aliases): array
    {
        return $this->matchAliased($value, $this->data()['colors']->get($brand->id, collect()), $aliases['color'] ?? []);
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
     * @return AttributeMatch
     */
    private function none(?string $value = null): array
    {
        return ['model' => null, 'value' => $value, 'quality' => 'none', 'candidates' => []];
    }

    /**
     * @return array{brands: Collection, balloonSizes: Collection, colors: Collection}
     */
    private function data(): array
    {
        return $this->data ??= [
            'brands' => Brand::all()->keyBy(fn (Brand $b) => strtolower($b->name)),
            'balloonSizes' => BalloonSize::all()->groupBy('brand_id'),
            'colors' => Color::all()
                ->groupBy('brand_id')
                ->map(fn (Collection $group) => $group->keyBy(fn (Color $c) => strtolower($c->name))),
        ];
    }
}
