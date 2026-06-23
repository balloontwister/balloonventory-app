<?php

namespace App\Http\Controllers;

use App\Models\Business;
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

        $favoritesList = $business->favoritesList();

        if (! $favoritesList) {
            return Inertia::render('Reorder/Index', [
                'skus' => [],
                'distributors' => [],
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

        $skus = $items->map(function ($item) use ($stockLevels) {
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
                'distributor_urls' => [],
            ];
        })->filter()->values()->all();

        return Inertia::render('Reorder/Index', [
            'skus' => $skus,
            'distributors' => [],
            'hasFavorites' => true,
        ]);
    }
}
