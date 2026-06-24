<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessDistributor;
use App\Models\Distributor;
use App\Models\DistributorSkuUrl;
use App\Models\StockLevel;
use App\Scopes\BusinessScope;
use App\Support\BusinessContext;
use Inertia\Inertia;
use Inertia\Response;

class ReorderController extends Controller
{
    public function index(): Response
    {
        $businessId = BusinessContext::currentId();
        $business = Business::findOrFail($businessId);

        $favoritesList = $business->favoritesList();

        // Load preferred distributors for this business
        $enabledDistributorIds = BusinessDistributor::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $businessId)
            ->where('is_enabled', true)
            ->pluck('distributor_id')
            ->all();

        // Resolve to the live (active, non-trashed) distributors only. We reuse
        // these ids for the URL query below so a soft-deleted distributor can't
        // leave orphaned distributor_sku_urls whose ->distributor eager-loads as
        // null and blows up the page.
        $enabledDistributors = [];
        $activeDistributorIds = [];
        if ($enabledDistributorIds !== []) {
            $activeDistributors = Distributor::whereIn('id', $enabledDistributorIds)
                ->active()
                ->orderBy('sort_order')
                ->get();

            $activeDistributorIds = $activeDistributors->pluck('id')->all();

            $enabledDistributors = $activeDistributors
                ->map(fn (Distributor $d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'slug' => $d->slug,
                ])
                ->all();
        }

        if (! $favoritesList) {
            return Inertia::render('Reorder/Index', [
                'skus' => [],
                'distributors' => $enabledDistributors,
                'hasFavorites' => false,
            ]);
        }

        $items = $favoritesList->items()
            ->with(['sku' => function ($query) {
                $query->with(['brand', 'color', 'balloonSize']);
            }])
            ->whereNotNull('planned_quantity')
            ->orderBy('sort_order')
            ->get();

        $skuIds = $items->pluck('sku_id')->filter()->unique()->values()->all();

        $stockLevels = StockLevel::where('business_id', $businessId)
            ->whereIn('sku_id', $skuIds)
            ->get()
            ->mapWithKeys(fn (StockLevel $sl) => [
                $sl->sku_id => ($sl->full_bags ?? 0) + ($sl->open_bags ?? 0),
            ]);

        // Fetch distributor purchase URLs for the preferred distributors
        $distributorUrls = collect();
        if ($skuIds !== [] && $activeDistributorIds !== []) {
            $distributorUrls = DistributorSkuUrl::whereIn('sku_id', $skuIds)
                ->whereIn('distributor_id', $activeDistributorIds)
                ->with('distributor')
                ->get()
                ->groupBy('sku_id');
        }

        $skus = $items->map(function ($item) use ($stockLevels, $distributorUrls) {
            $sku = $item->sku;

            if (! $sku) {
                return null;
            }

            $onHand = $stockLevels->get($sku->id, 0);
            $skuUrls = $distributorUrls->get($sku->id, collect());

            return [
                'id' => $sku->id,
                'name' => $sku->name,
                'computed_name' => $sku->computed_name,
                'warehouse_sku' => $sku->warehouse_sku,
                'upc' => $sku->upc,
                'ean' => $sku->ean,
                'brand' => $sku->brand?->abbreviation,
                'color' => $sku->color?->name,
                'balloon_size' => $sku->balloonSize?->name,
                'default_count_per_bag' => $sku->default_count_per_bag,
                'planned_quantity' => $item->planned_quantity,
                'on_hand' => $onHand,
                'needed' => max(0, (float) $item->planned_quantity - $onHand),
                'distributor_urls' => $skuUrls->map(fn (DistributorSkuUrl $u) => [
                    'distributor' => [
                        'id' => $u->distributor->id,
                        'name' => $u->distributor->name,
                        'slug' => $u->distributor->slug,
                    ],
                    'url' => $u->url,
                    'price' => $u->price,
                    'currency' => $u->currency,
                    'in_stock' => $u->in_stock,
                ])->values()->all(),
            ];
        })->filter()->values()->all();

        return Inertia::render('Reorder/Index', [
            'skus' => $skus,
            'distributors' => $enabledDistributors,
            'hasFavorites' => true,
        ]);
    }
}
