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

    /** @var array{brands: Collection, balloonSizes: Collection, colors: Collection}|null */
    private ?array $data = null;

    /**
     * @param  array<string, array<int, string>>  $attributes  label → value(s) from the distributor table
     * @param  array<string, mixed>  $aliases  distributor config `attribute_aliases`
     * @return array{brand: AttributeMatch, balloon_size: AttributeMatch, color: AttributeMatch, count: int|null}
     */
    public function match(array $attributes, array $aliases = []): array
    {
        $brand = $this->matchBrand($this->value($attributes, 'Brand'), $aliases);

        // Size and colour are brand-scoped, so without a brand there's nothing to
        // match them against.
        $brandModel = $brand['model'];

        return [
            'brand' => $brand,
            'balloon_size' => $brandModel instanceof Brand
                ? $this->matchSize($this->value($attributes, 'Size'), $brandModel)
                : $this->none(),
            'color' => $brandModel instanceof Brand
                ? $this->matchColor($this->value($attributes, 'Color'), $brandModel, $aliases)
                : $this->none(),
            'count' => $this->parseCount($this->value($attributes, 'Quantity')),
        ];
    }

    /**
     * @param  array<string, mixed>  $aliases
     * @return AttributeMatch
     */
    private function matchBrand(?string $value, array $aliases): array
    {
        if ($value === null) {
            return $this->none();
        }

        [$value, $aliased] = $this->applyAlias($value, $aliases['brand'] ?? []);
        $brands = $this->data()['brands'];

        return $this->resolve($value, $brands, $aliased);
    }

    /**
     * @return AttributeMatch
     */
    private function matchSize(?string $value, Brand $brand): array
    {
        if ($value === null) {
            return $this->none();
        }

        $key = $this->sizeKey($value);
        $sizes = $this->data()['balloonSizes']->get($brand->id, collect());

        $matches = $sizes->filter(fn (BalloonSize $bs) => $this->sizeKey($bs->name) === $key)->values();

        if ($matches->isEmpty()) {
            return $this->none($value);
        }

        return [
            'model' => $matches->first(),
            'value' => $value,
            // A single brand size for that core key is unambiguous; several
            // (e.g. round vs heart at the same inch) need a human pick.
            'quality' => $matches->count() === 1 ? 'exact' : 'fuzzy',
            'candidates' => $this->candidates($matches),
        ];
    }

    /**
     * @param  array<string, mixed>  $aliases
     * @return AttributeMatch
     */
    private function matchColor(?string $value, Brand $brand, array $aliases): array
    {
        if ($value === null) {
            return $this->none();
        }

        [$value, $aliased] = $this->applyAlias($value, $aliases['color'] ?? []);
        $colors = $this->data()['colors']->get($brand->id, collect());

        return $this->resolve($value, $colors, $aliased);
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
