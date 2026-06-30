<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Location;
use App\Scopes\BusinessScope;
use App\Support\BusinessContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

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
            'position_locked' => ['boolean'],
        ]);

        $location->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'position_locked' => $data['position_locked'] ?? false,
        ]);

        return back()->with('success', __('bins.flash.location_updated'));
    }

    /**
     * Persist a new visual order for the business's locations (drag-reorder on
     * Manage storage). The locations are sent in their new order; sort_order is
     * written by index. Position locks are enforced on the client (locked
     * locations can't be dragged or crossed), so the order already keeps them
     * in place. The global BusinessScope keeps this tenant-safe.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $businessId = BusinessContext::currentId();
        Gate::authorize('inventory.manual_adjust', Business::findOrFail($businessId));

        $data = $request->validate([
            'location_ids' => ['required', 'array'],
            'location_ids.*' => ['uuid'],
        ]);

        $validIds = Location::whereIn('id', $data['location_ids'])->pluck('id')->all();

        $ordered = array_values(array_filter(
            $data['location_ids'],
            fn ($id) => in_array($id, $validIds, true),
        ));

        DB::transaction(function () use ($ordered) {
            foreach ($ordered as $index => $locationId) {
                Location::where('id', $locationId)->update(['sort_order' => $index]);
            }
        });

        return back()->with('success', __('bins.flash.locations_reordered'));
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
