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

        // Size notation varies wildly across distributors ("11 inch" vs our
        // "11-inch"), so canonicalise both sides before comparing.
        $sizeHaystack = ProductText::normalizeSizeTokens($haystack);

        $balloonSize = $data['balloonSizes']
            ->first(fn (BalloonSize $bs, string $sizeName) => $bs->brand_id === $brand->id
                && ProductText::mentions($sizeHaystack, ProductText::normalizeSizeTokens($sizeName)));

        $color = $this->firstMention($data['colors']->get($brand->id, collect()), $haystack);

        return ['brand' => $brand, 'balloonSize' => $balloonSize, 'color' => $color];
    }

    /**
     * The colour of a given brand named in a free-text string, or null. Used to
     * recover the real shade from a product title when a distributor's structured
     * "Color" field is only a coarse family ("Green" vs "Pastel Dusk Green Tea").
     */
    public function colorInText(string $text, Brand $brand): ?Color
    {
        return $this->firstMention($this->data()['colors']->get($brand->id, collect()), strtolower($text));
    }

    /**
     * Prefer a specific shade named in the title over the structured colour,
     * shared by every promotion/presentation path so the guard below can't drift
     * out of sync between them.
     *
     * A non-exact structured match (fuzzy/learned/none) always defers to the
     * title, as before. An EXACT structured match is trusted UNLESS the title
     * names a different, more specific colour that is itself a refinement of the
     * exact match's name (e.g. distributor Color "Green" exactly matches our
     * plain "Green", but the title says "Mirror Green Gold" — a distinct, real
     * catalog colour whose name literally contains "Green"). That containment
     * check is the load-bearing part: it lets a coarse-but-real exact match still
     * be corrected by a clearly more specific title mention, while refusing to let
     * an unrelated word in the title override a correct, specific exact match.
     */
    public function refineColorFromTitle(?Color $structuredColor, string $structuredQuality, string $text, Brand $brand): ?Color
    {
        $titleColor = $this->colorInText($text, $brand);

        if ($titleColor === null || $titleColor->is($structuredColor)) {
            return $structuredColor;
        }

        if ($structuredQuality !== 'exact') {
            return $titleColor;
        }

        if ($structuredColor !== null && ProductText::mentions(strtolower($titleColor->name), strtolower($structuredColor->name))) {
            return $titleColor;
        }

        return $structuredColor;
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
