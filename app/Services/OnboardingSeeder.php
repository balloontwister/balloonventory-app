<?php

namespace App\Services;

use App\Enums\StockDirection;
use App\Models\Bin;
use App\Models\Business;
use App\Models\Location;
use App\Models\Membership;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Scopes\BusinessScope;
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

            [$primaryBin, $locationCount, $binCount] = $this->applyLocations($business, $input['locations'] ?? []);
            [$skuCount, $bagCount] = $this->seedSampleStock($business, $owner, $primaryBin, $input);

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

        Membership::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $business->id)
            ->where('user_id', $owner->id)
            ->update(['business_badge_color' => $input['badge_color']]);
    }

    /**
     * Rename the seeded Default location/bin to the owner's first-named spot and
     * create any additional locations and bins. Returns the primary bin sample
     * stock should land in, plus the location/bin counts.
     *
     * @param  array<int, array{name: string, bins?: array<int, string>}>  $locations
     * @return array{0: Bin, 1: int, 2: int}
     */
    private function applyLocations(Business $business, array $locations): array
    {
        $defaultLocation = $this->ensureDefaultLocation($business);
        $defaultBin = $this->ensureDefaultBin($business, $defaultLocation);

        $locationCount = 1;
        $binCount = 1;

        if ($locations === []) {
            return [$defaultBin, $locationCount, $binCount];
        }

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

        return [$defaultBin, $locationCount, $binCount];
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
     * @param  array<string, mixed>  $input
     * @return array{0: int, 1: int}
     */
    private function seedSampleStock(Business $business, User $owner, Bin $bin, array $input): array
    {
        $role = $input['role'] ?? null;

        if (! $role) {
            return [0, 0];
        }

        $spec = $this->resolver->findSpecForRole($role);

        if ($spec === null) {
            return [0, 0];
        }

        $brands = ! empty($input['brands']) ? $input['brands'] : null;
        $rows = $this->resolver->resolve($spec, $brands);

        $skuCount = 0;
        $bagCount = 0;

        foreach ($rows as $row) {
            if ($row['status'] !== 'matched') {
                continue;
            }

            $bags = (int) ($row['bags'] ?? 0);

            if ($bags <= 0) {
                continue;
            }

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
}
