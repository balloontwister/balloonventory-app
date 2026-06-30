<?php

namespace App\Services;

use App\Enums\StockDirection;
use App\Models\Bin;
use App\Models\Business;
use App\Models\Location;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Scopes\BusinessScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Applies a completed onboarding wizard to a freshly-created business: renames /
 * creates the storage locations and bins the owner described, seeds sample stock
 * from the role's seed-list (via OnboardingSeedResolver), and applies the owner's
 * language / timezone / brand-color choices.
 *
 * Sample stock is seeded as real inventory (a StockLevel plus an "in" movement)
 * but flagged `is_sample` so it can be cleanly removed later — and only while the
 * owner hasn't already built real stock on top of it.
 */
class OnboardingSeeder
{
    public function __construct(private readonly OnboardingSeedResolver $resolver) {}

    /**
     * @param  array{role?: ?string, brands?: array<int, string>, locations?: array<int, array{name: string, bins?: array<int, string>}>, locale?: ?string, timezone?: ?string, badge_color?: ?string}  $input
     * @return array{locations: int, bins: int, sample_skus: int, sample_bags: int}
     */
    public function seed(Business $business, User $owner, array $input): array
    {
        return DB::transaction(function () use ($business, $owner, $input) {
            $this->applyPreferences($owner, $input);
            $this->applyBadgeColor($business, $owner, $input);

            [$bins, $locationCount, $binCount] = $this->applyLocations($business, $input['locations'] ?? []);
            [$skuCount, $bagCount] = $this->seedSampleStock($business, $owner, $bins, $input);

            return [
                'locations' => $locationCount,
                'bins' => $binCount,
                'sample_skus' => $skuCount,
                'sample_bags' => $bagCount,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function applyPreferences(User $owner, array $input): void
    {
        $changes = [];

        if (! empty($input['locale'])) {
            $changes['locale'] = $input['locale'];
        }

        if (! empty($input['timezone'])) {
            $changes['timezone'] = $input['timezone'];
        }

        if ($changes !== []) {
            $owner->forceFill($changes)->save();
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function applyBadgeColor(Business $business, User $owner, array $input): void
    {
        if (empty($input['badge_color'])) {
            return;
        }

        $business->update(['color' => $input['badge_color']]);
    }

    /**
     * Rename the seeded Default location/bin to the owner's first-named spot and
     * create any additional locations and bins. Returns the business's bins
     * (Default first) for sample stock to be distributed across, plus the
     * location/bin counts.
     *
     * @param  array<int, array{name: string, bins?: array<int, string>}>  $locations
     * @return array{0: Collection<int, Bin>, 1: int, 2: int}
     */
    private function applyLocations(Business $business, array $locations): array
    {
        $defaultLocation = $this->ensureDefaultLocation($business);
        $defaultBin = $this->ensureDefaultBin($business, $defaultLocation);

        $locationCount = 1;
        $binCount = 1;

        if ($locations !== []) {
            $first = array_shift($locations);
            $defaultLocation->update(['name' => $first['name']]);

            $firstBins = $first['bins'] ?? [];
            if ($firstBins !== []) {
                $defaultBin->update(['name' => array_shift($firstBins)]);

                foreach ($firstBins as $binName) {
                    $this->createBin($business, $defaultLocation, $binName);
                    $binCount++;
                }
            }

            foreach ($locations as $location) {
                $created = Location::withoutGlobalScope(BusinessScope::class)->create([
                    'business_id' => $business->id,
                    'name' => $location['name'],
                    'is_default' => false,
                ]);
                $locationCount++;

                foreach ($location['bins'] ?? [] as $binName) {
                    $this->createBin($business, $created, $binName);
                    $binCount++;
                }
            }
        }

        $bins = Bin::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $business->id)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return [$bins, $locationCount, $binCount];
    }

    private function createBin(Business $business, Location $location, string $name): void
    {
        Bin::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $business->id,
            'location_id' => $location->id,
            'name' => $name,
            'is_default' => false,
        ]);
    }

    private function ensureDefaultLocation(Business $business): Location
    {
        return Location::withoutGlobalScope(BusinessScope::class)
            ->firstOrCreate(
                ['business_id' => $business->id, 'is_default' => true],
                ['name' => 'Default'],
            );
    }

    private function ensureDefaultBin(Business $business, Location $location): Bin
    {
        return Bin::withoutGlobalScope(BusinessScope::class)
            ->firstOrCreate(
                ['business_id' => $business->id, 'is_default' => true],
                ['location_id' => $location->id, 'name' => 'Default'],
            );
    }

    /**
     * @param  Collection<int, Bin>  $bins
     * @param  array<string, mixed>  $input
     * @return array{0: int, 1: int}
     */
    private function seedSampleStock(Business $business, User $owner, Collection $bins, array $input): array
    {
        $role = $input['role'] ?? null;

        if (! $role) {
            return [0, 0];
        }

        $spec = $this->resolver->findSpecForRole($role);

        if ($spec === null || $bins->isEmpty()) {
            return [0, 0];
        }

        $brands = ! empty($input['brands']) ? $input['brands'] : null;

        $rows = array_values(array_filter(
            $this->resolver->resolve($spec, $brands),
            fn ($row) => $row['status'] === 'matched' && (int) ($row['bags'] ?? 0) > 0,
        ));

        if ($rows === []) {
            return [0, 0];
        }

        $familyBySku = $this->colorFamilyBySku(array_column($rows, 'sku_id'));
        $binByFamily = $this->distributeColorFamilies($familyBySku, $bins);
        $defaultBin = $bins->first();

        $skuCount = 0;
        $bagCount = 0;

        foreach ($rows as $row) {
            $bags = (int) $row['bags'];
            $familyKey = $familyBySku[$row['sku_id']]['key'] ?? self::NO_FAMILY;
            $bin = $binByFamily[$familyKey] ?? $defaultBin;

            $level = StockLevel::withoutGlobalScope(BusinessScope::class)->firstOrNew([
                'business_id' => $business->id,
                'sku_id' => $row['sku_id'],
                'bin_id' => $bin->id,
            ]);

            $level->full_bags = ($level->full_bags ?? 0) + $bags;
            $level->open_bags = $level->open_bags ?? 0;
            $level->is_sample = true;
            $level->last_movement_at = now();
            $level->save();

            StockMovement::withoutGlobalScope(BusinessScope::class)->create([
                'business_id' => $business->id,
                'sku_id' => $row['sku_id'],
                'bin_id' => $bin->id,
                'user_id' => $owner->id,
                'direction' => StockDirection::In,
                'full_bags_change' => $bags,
                'open_bags_change' => 0,
                'is_sample' => true,
                'notes' => 'Onboarding sample',
            ]);

            $skuCount++;
            $bagCount += $bags;
        }

        return [$skuCount, $bagCount];
    }

    private const NO_FAMILY = 'none';

    /**
     * Map each seeded SKU to its colour family with an ordering key (family
     * sort_order, then name) so families can be laid out around the colour wheel.
     *
     * @param  array<int, string>  $skuIds
     * @return array<string, array{key: string, sort: int, name: string}>
     */
    private function colorFamilyBySku(array $skuIds): array
    {
        if ($skuIds === []) {
            return [];
        }

        $rows = DB::table('skus')
            ->join('colors', 'colors.id', '=', 'skus.color_id')
            ->leftJoin('color_families', 'color_families.id', '=', 'colors.color_family_id')
            ->whereIn('skus.id', $skuIds)
            ->get([
                'skus.id as sku_id',
                'colors.color_family_id',
                'color_families.sort_order as family_sort',
                'color_families.name as family_name',
            ]);

        $map = [];

        foreach ($rows as $row) {
            $map[$row->sku_id] = [
                'key' => $row->color_family_id ?? self::NO_FAMILY,
                'sort' => $row->family_sort ?? PHP_INT_MAX,
                'name' => (string) $row->family_name,
            ];
        }

        return $map;
    }

    /**
     * Distribute colour families across the available bins. Families are ordered
     * around the colour wheel and split into contiguous chunks — so each family's
     * balloons stay together, every family gets its own bin when there are enough,
     * and similar (adjacent) families share a bin when there are not.
     *
     * @param  array<string, array{key: string, sort: int, name: string}>  $familyBySku
     * @param  Collection<int, Bin>  $bins
     * @return array<string, Bin>
     */
    private function distributeColorFamilies(array $familyBySku, Collection $bins): array
    {
        $families = collect($familyBySku)
            ->unique('key')
            ->sortBy([['sort', 'asc'], ['name', 'asc'], ['key', 'asc']])
            ->pluck('key')
            ->all();

        $binList = $bins->values()->all();
        $binCount = count($binList);
        $familyCount = count($families);

        if ($binCount === 0 || $familyCount === 0) {
            return [];
        }

        $base = intdiv($familyCount, $binCount);
        $remainder = $familyCount % $binCount;

        $map = [];
        $index = 0;

        for ($bin = 0; $bin < $binCount; $bin++) {
            $size = $base + ($bin < $remainder ? 1 : 0);

            for ($n = 0; $n < $size; $n++) {
                $map[$families[$index]] = $binList[$bin];
                $index++;
            }
        }

        return $map;
    }
}
