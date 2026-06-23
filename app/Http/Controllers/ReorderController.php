<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessDistributor;
use App\Models\Distributor;
use App\Models\DistributorSkuUrl;
use App\Models\StockLevel;
use App\Support\BusinessContext;
use Inertia\Inertia;
use Inertia\Response;

class ReorderController extends Controller
{
    public function index(): Response
    {
        $businessId = BusinessContext::currentId();
        $business = Business::findOrFail($businessId);

        // Get the business's enabled distributor IDs
        $enabledDistributorIds = BusinessDistributor::where('business_id', $businessId)
            ->where('is_enabled', true)
            ->pluck('distributor_id')
            ->toArray();

        // Load the Favorites list with items that have a planned_quantity set
        $favoritesList = $business->favoritesList();

        if (! $favoritesList) {
            return Inertia::render('Reorder/Index', [
                'skus' => [],
                'distributors' => [],
                'hasFavorites' => false,
            ]);
        }

        // Get list items with their SKUs and current stock levels
        $items = $favoritesList->items()
            ->with(['sku' => function ($query) {
                $query->with(['brand', 'color', 'balloonSize']);
            }])
            ->whereNotNull('planned_quantity')
            ->orderBy('sort_order')
            ->get();

        // Load distributor URLs for the SKUs
        $skuIds = $items->pluck('sku_id')->filter()->unique()->values()->all();

        $distributorUrls = DistributorSkuUrl::whereIn('sku_id', $skuIds)
            ->whereIn('distributor_id', $enabledDistributorIds)
            ->with('distributor:id,name,slug')
            ->get()
            ->groupBy('sku_id');

        // Load stock levels for the SKUs
        $stockLevels = StockLevel::where('business_id', $businessId)
            ->whereIn('sku_id', $skuIds)
            ->get()
            ->mapWithKeys(fn (StockLevel $sl) => [
                $sl->sku_id => ($sl->full_bags ?? 0) + ($sl->open_bags ?? 0),
            ]);

        $skus = $items->map(function ($item) use ($distributorUrls, $stockLevels) {
            $sku = $item->sku;

            if (! $sku) {
                return null;
            }

            $onHand = $stockLevels->get($sku->id, 0);

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
                'distributor_urls' => ($distributorUrls->get($sku->id) ?? collect())
                    ->map(fn (DistributorSkuUrl $dsu) => [
                        'url' => $dsu->url,
                        'price' => $dsu->price,
                        'currency' => $dsu->currency,
                        'in_stock' => $dsu->in_stock,
                        'distributor' => [
                            'name' => $dsu->distributor->name,
                            'slug' => $dsu->distributor->slug,
                        ],
                    ])
                    ->values()
                    ->all(),
            ];
        })->filter()->values()->all();

        $distributors = Distributor::whereIn('id', $enabledDistributorIds)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug'])
            ->toArray();

        return Inertia::render('Reorder/Index', [
            'skus' => $skus,
            'distributors' => $distributors,
            'hasFavorites' => true,
        ]);
    }
}
