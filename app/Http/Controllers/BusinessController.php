<?php

namespace App\Http\Controllers;

use App\Models\BalloonList;
use App\Models\Bin;
use App\Models\Business;
use App\Models\Location;
use App\Models\Membership;
use App\Scopes\BusinessScope;
use App\Support\BusinessContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BusinessController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Onboarding/CreateBusiness');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $slug = $this->uniqueSlug($request->name);

        $business = Business::create([
            'id' => (string) Str::uuid7(),
            'name' => $request->name,
            'slug' => $slug,
        ]);

        $membership = Membership::create([
            'user_id' => $request->user()->id,
            'business_id' => $business->id,
            'role' => 'owner',
            'business_badge_color' => '#6366F1',
            'joined_at' => now(),
        ]);

        // Seed the Favorites list. BalloonList uses BelongsToBusiness, but since
        // we're doing an INSERT (not a SELECT), the global scope doesn't filter here.
        // We pass business_id explicitly and bypass scope on the create.
        BalloonList::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $business->id,
            'name' => 'Favorites',
            'is_business_favorites' => true,
            'created_by_user_id' => $request->user()->id,
        ]);

        // Seed the Default location and Default bin for inventory storage.
        $location = Location::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $business->id,
            'name' => 'Default',
            'is_default' => true,
        ]);

        Bin::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $business->id,
            'location_id' => $location->id,
            'name' => 'Default',
            'is_default' => true,
        ]);

        $request->session()->put('current_business_id', $business->id);
        BusinessContext::set($business->id);

        return redirect()->route('onboarding.wizard');
    }

    public function switch(Request $request, string $business): RedirectResponse
    {
        $membership = Membership::withoutGlobalScope(BusinessScope::class)
            ->with('business')
            ->where('user_id', $request->user()->id)
            ->where('business_id', $business)
            ->whereNull('deleted_at')
            ->first();

        abort_unless($membership !== null, 403);
        abort_if($membership->role === 'none', 403);
        // A suspended business behaves like "No Access" — members may see it but
        // cannot enter it until support unsuspends it.
        abort_if($membership->business?->isFrozen() ?? false, 403);

        $request->session()->put('current_business_id', $business);
        BusinessContext::set($business);

        return redirect()->back()->with('success', __('flash.business.switched', ['name' => $membership->business->name]));
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;

        while (Business::where('slug', $slug)->withTrashed()->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
