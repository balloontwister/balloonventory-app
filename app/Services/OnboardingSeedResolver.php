<?php

namespace App\Services;

use App\Models\Brand;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the brand-agnostic onboarding seed-list specs
 * (database/data/onboarding/seed_lists/*.json) against the live shared catalog.
 *
 * Each spec item names a size/shape/bags plus a per-brand color map. For every
 * (item, brand) pair this finds the matching shared SKU, honoring per-brand size
 * and bag-count overrides and an ordered count-preference fallback. The result
 * is a flat list of rows tagged matched / no_match / brand_missing — consumed by
 * the `onboarding:seed-list` command for validation and (later) by the wizard
 * for the actual seeding.
 */
class OnboardingSeedResolver
{
    public function defaultDirectory(): string
    {
        return database_path('data/onboarding/seed_lists');
    }

    /**
     * Load every spec file in the directory (one per file).
     *
     * @return array<int, array<string, mixed>>
     */
    public function loadSpecs(?string $directory = null): array
    {
        $directory ??= $this->defaultDirectory();

        $specs = [];

        foreach (glob(rtrim($directory, '/').'/*.json') as $file) {
            $decoded = json_decode((string) file_get_contents($file), true);

            if (! is_array($decoded)) {
                continue;
            }

            $decoded['_file'] = basename($file);
            $specs[] = $decoded;
        }

        return $specs;
    }

    /**
     * Find the spec serving a given role (e.g. "twister", "retailer").
     *
     * @return array<string, mixed>|null
     */
    public function findSpecForRole(string $role, ?string $directory = null): ?array
    {
        foreach ($this->loadSpecs($directory) as $spec) {
            if (in_array($role, $this->rolesFor($spec), true)) {
                return $spec;
            }
        }

        return null;
    }

    /**
     * The role(s) a spec serves. Accepts a `roles` array or a singular `role`.
     *
     * @param  array<string, mixed>  $spec
     * @return array<int, string>
     */
    public function rolesFor(array $spec): array
    {
        if (isset($spec['roles']) && is_array($spec['roles'])) {
            return array_values($spec['roles']);
        }

        if (isset($spec['role']) && is_string($spec['role'])) {
            return [$spec['role']];
        }

        return [];
    }

    /**
     * Resolve a spec's items against the catalog.
     *
     * @param  array<string, mixed>  $spec
     * @param  array<int, string>|null  $onlyBrands  Limit to these brand names.
     * @return array<int, array<string, mixed>>
     */
    public function resolve(array $spec, ?array $onlyBrands = null): array
    {
        $defaultCount = $spec['defaults']['count_per_bag'] ?? null;
        $items = $spec['items'] ?? [];

        $brandNames = [];
        foreach ($items as $item) {
            foreach (array_keys($item['colors'] ?? []) as $brandName) {
                $brandNames[$brandName] = true;
            }
        }

        $brands = Brand::whereIn('name', array_keys($brandNames))->get()->keyBy('name');
        $catalog = $this->catalogIndex($brands->pluck('id')->all());

        $rows = [];

        foreach ($items as $item) {
            foreach (($item['colors'] ?? []) as $brandName => $entry) {
                if ($onlyBrands !== null && ! in_array($brandName, $onlyBrands, true)) {
                    continue;
                }

                $rows[] = $this->resolveOne($item, (string) $brandName, $entry, $defaultCount, $brands, $catalog);
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  string|array<string, mixed>  $entry
     * @param  Collection<string, Brand>  $brands
     * @param  array<string, array<int, array<string, mixed>>>  $catalog
     * @return array<string, mixed>
     */
    private function resolveOne(array $item, string $brandName, string|array $entry, mixed $defaultCount, $brands, array $catalog): array
    {
        $entry = is_array($entry) ? $entry : ['color' => $entry];

        $color = $entry['color'] ?? null;
        $size = $entry['size'] ?? ($item['size'] ?? null);
        $shape = $entry['shape'] ?? ($item['shape'] ?? null);
        $countPref = $this->normalizeCounts($entry['count_per_bag'] ?? $item['count_per_bag'] ?? $defaultCount);

        $base = [
            'key' => $item['key'] ?? null,
            'brand' => $brandName,
            'size' => $size,
            'shape' => $shape,
            'color' => $color,
            'bags' => $item['bags'] ?? null,
            'sku_id' => null,
            'sku_name' => null,
            'count_per_bag' => null,
            'count_fallback' => false,
        ];

        $brand = $brands->get($brandName);

        if ($brand === null) {
            return ['status' => 'brand_missing'] + $base;
        }

        $key = $this->indexKey($brand->id, $size, $shape, $color);
        $candidates = $catalog[$key] ?? [];

        if ($candidates === []) {
            return ['status' => 'no_match'] + $base;
        }

        [$chosen, $fallback] = $this->pickByCount($candidates, $countPref);

        return [
            'status' => 'matched',
            'sku_id' => $chosen['id'],
            'sku_name' => $chosen['name'],
            'count_per_bag' => $chosen['count'],
            'count_fallback' => $fallback,
        ] + $base;
    }

    /**
     * Build an in-memory index of shared catalog SKUs keyed by
     * brand+size+shape+color, each bucket sorted deterministically.
     *
     * @param  array<int, string>  $brandIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function catalogIndex(array $brandIds): array
    {
        if ($brandIds === []) {
            return [];
        }

        $rows = DB::table('skus')
            ->join('balloon_sizes', 'balloon_sizes.id', '=', 'skus.balloon_size_id')
            ->join('sizes', 'sizes.id', '=', 'balloon_sizes.size_id')
            ->join('shapes', 'shapes.id', '=', 'balloon_sizes.shape_id')
            ->join('colors', 'colors.id', '=', 'skus.color_id')
            ->whereIn('skus.brand_id', $brandIds)
            ->whereNull('skus.owned_by_business_id')
            ->whereNull('skus.deleted_at')
            ->get([
                'skus.id',
                'skus.brand_id',
                'skus.computed_name',
                'skus.default_count_per_bag as count',
                'sizes.name as size',
                'shapes.name as shape',
                'colors.name as color',
            ]);

        $index = [];

        foreach ($rows as $row) {
            $key = $this->indexKey($row->brand_id, $row->size, $row->shape, $row->color);

            $index[$key][] = [
                'id' => $row->id,
                'name' => $row->computed_name,
                'count' => $row->count !== null ? (int) $row->count : null,
            ];
        }

        foreach ($index as &$bucket) {
            usort($bucket, fn ($a, $b) => ($a['count'] <=> $b['count']) ?: strcmp((string) $a['name'], (string) $b['name']));
        }

        return $index;
    }

    private function indexKey(string $brandId, ?string $size, ?string $shape, ?string $color): string
    {
        return $brandId.'|'.mb_strtolower((string) $size).'|'.mb_strtolower((string) $shape).'|'.mb_strtolower((string) $color);
    }

    /**
     * Pick the candidate matching the ordered count preference. Falls back to the
     * count nearest the first preference (flagged) when no exact preference hits.
     *
     * @param  array<int, array<string, mixed>>  $candidates  (already sorted)
     * @param  array<int, int>  $countPref
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function pickByCount(array $candidates, array $countPref): array
    {
        if ($countPref !== []) {
            foreach ($countPref as $pref) {
                foreach ($candidates as $candidate) {
                    if ($candidate['count'] === $pref) {
                        return [$candidate, false];
                    }
                }
            }

            $target = $countPref[0];
            $withCount = array_values(array_filter($candidates, fn ($c) => $c['count'] !== null));

            if ($withCount !== []) {
                usort($withCount, fn ($a, $b) => (abs($a['count'] - $target) <=> abs($b['count'] - $target)) ?: ($a['count'] <=> $b['count']));

                return [$withCount[0], true];
            }
        }

        return [$candidates[0], $countPref !== []];
    }

    /**
     * @return array<int, int>
     */
    private function normalizeCounts(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            return array_values(array_map('intval', $value));
        }

        return [(int) $value];
    }
}
