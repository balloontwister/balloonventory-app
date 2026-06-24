<?php

namespace App\Services;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Color;
use App\Support\ProductText;
use Illuminate\Support\Collection;

/**
 * Resolves a free-text product name to catalog reference rows (brand → balloon
 * size → colour). Shared by the DistributorMatcher (to match existing SKUs) and
 * the DistributorCatalogPromoter (to create new ones from a proposal).
 *
 * Reference data is loaded once and memoised, ordered longest-name-first so the
 * most specific token wins ("15-inch" before "5-inch", "Light Blue" before
 * "Blue"). Size and colour are scoped to the resolved brand.
 */
class CatalogAttributeResolver
{
    /** @var array{brands: Collection, balloonSizes: Collection, colors: Collection}|null */
    private ?array $data = null;

    /**
     * @return array{brand: ?Brand, balloonSize: ?BalloonSize, color: ?Color}
     */
    public function resolve(string $name): array
    {
        $data = $this->data();
        $haystack = strtolower($name);

        $brand = $this->firstMention($data['brands'], $haystack);

        if ($brand === null) {
            return ['brand' => null, 'balloonSize' => null, 'color' => null];
        }

        $balloonSize = $data['balloonSizes']
            ->first(fn (BalloonSize $bs, string $sizeName) => $bs->brand_id === $brand->id && ProductText::mentions($haystack, $sizeName));

        $color = $this->firstMention($data['colors']->get($brand->id, collect()), $haystack);

        return ['brand' => $brand, 'balloonSize' => $balloonSize, 'color' => $color];
    }

    private function firstMention(Collection $keyedByName, string $haystack)
    {
        return $keyedByName->first(fn ($model, string $name) => ProductText::mentions($haystack, $name));
    }

    /**
     * @return array{brands: Collection, balloonSizes: Collection, colors: Collection}
     */
    private function data(): array
    {
        return $this->data ??= [
            'brands' => Brand::all()
                ->sortByDesc(fn (Brand $b) => strlen($b->name))
                ->keyBy(fn (Brand $b) => strtolower($b->name)),
            'balloonSizes' => BalloonSize::with(['size', 'shape'])
                ->get()
                ->sortByDesc(fn (BalloonSize $bs) => strlen($bs->name))
                ->keyBy(fn (BalloonSize $bs) => strtolower($bs->name)),
            'colors' => Color::all()
                ->groupBy(fn (Color $c) => $c->brand_id)
                ->map(fn (Collection $group) => $group
                    ->sortByDesc(fn (Color $c) => strlen($c->name))
                    ->keyBy(fn (Color $c) => strtolower($c->name))),
        ];
    }
}
