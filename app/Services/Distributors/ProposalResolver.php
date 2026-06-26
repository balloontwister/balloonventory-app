<?php

namespace App\Services\Distributors;

use App\Models\DistributorCatalogProposal;
use App\Services\CatalogAttributeResolver;

/**
 * Computes the canonical, storable resolution for a proposal cluster: which
 * catalog brand/size/colour the distributor's attributes map to, and an overall
 * state (full / partial / no_brand). Stamped onto the proposal at cluster time so
 * the review queue can sort, group and count by it without re-running the matcher.
 *
 * Mirrors the colour priority used elsewhere (exact structured → title shade →
 * fuzzy structured), so the stored state agrees with what the queue guess shows
 * and what approving would create.
 */
class ProposalResolver
{
    public function __construct(
        private DistributorAttributeMatcher $matcher,
        private CatalogAttributeResolver $resolver,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $members  the cluster's evidence members
     * @param  array<string, mixed>  $config  the representative member's distributor config
     * @return array{brand_id: ?string, brand_name: ?string, state: string, count: ?int, detail: array<string, mixed>}
     */
    public function resolve(array $members, array $config): array
    {
        $member = collect($members)->first(fn (array $m) => ! empty($m['attributes']));

        if ($member === null) {
            return $this->none();
        }

        $match = $this->matcher->match($member['attributes'], $config);
        $brand = $match['brand']['model'];
        $size = $match['balloon_size']['model'];
        $color = $match['color']['model'];

        // Coarse structured colour ("Green") defers to the shade in the title.
        if ($brand !== null && $match['color']['quality'] !== 'exact') {
            $title = collect($members)->pluck('title')->filter()->implode(' ');
            $color = $this->resolver->colorInText($title, $brand) ?? $color;
        }

        $state = match (true) {
            $brand === null => DistributorCatalogProposal::RESOLUTION_NO_BRAND,
            $size !== null && $color !== null => DistributorCatalogProposal::RESOLUTION_FULL,
            default => DistributorCatalogProposal::RESOLUTION_PARTIAL,
        };

        return [
            'brand_id' => $brand?->id,
            'brand_name' => $brand?->name,
            'state' => $state,
            // The distributor's structured "Quantity" (e.g. "100 ct") — cleaner than
            // parsing a count out of the title.
            'count' => $match['count'],
            'detail' => [
                'brand' => $this->attr($brand, $match['brand']['value']),
                'size' => $this->attr($size, $match['balloon_size']['value']),
                'color' => $this->attr($color, $match['color']['value']),
            ],
        ];
    }

    /**
     * A resolved attribute as {id, name}, or the unresolved distributor value the
     * gaps panel reports as missing reference data.
     *
     * @return array<string, mixed>
     */
    private function attr(mixed $model, ?string $value): array
    {
        return $model !== null
            ? ['id' => $model->id, 'name' => $model->name]
            : ['value' => $value];
    }

    /**
     * @return array{brand_id: ?string, brand_name: ?string, state: string, count: ?int, detail: array<string, mixed>}
     */
    private function none(): array
    {
        return [
            'brand_id' => null,
            'brand_name' => null,
            'state' => DistributorCatalogProposal::RESOLUTION_NO_BRAND,
            'count' => null,
            'detail' => [],
        ];
    }
}
