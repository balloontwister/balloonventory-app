<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Scopes\BusinessScope;
use App\Support\BusinessContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $businessId = BusinessContext::currentId();

        Location::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $businessId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'sort_order' => $this->nextSortOrder($businessId),
        ]);

        return back()->with('success', __('bins.flash.location_created'));
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $location->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        return back()->with('success', __('bins.flash.location_updated'));
    }

    public function destroy(Location $location): RedirectResponse
    {
        if ($location->is_default) {
            return back()->with('error', __('bins.flash.location_default_protected'));
        }

        if ($location->bins()->exists()) {
            return back()->with('error', __('bins.flash.location_has_bins'));
        }

        $location->delete();

        return back()->with('success', __('bins.flash.location_deleted'));
    }

    private function nextSortOrder(string $businessId): int
    {
        return (int) Location::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $businessId)
            ->max('sort_order') + 1;
    }
}
