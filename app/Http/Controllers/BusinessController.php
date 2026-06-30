<?php

namespace App\Http\Controllers;

use App\Models\BalloonList;
use App\Models\Bin;
use App\Models\Business;
use App\Models\Location;
use App\Models\Membership;
use App\Scopes\BusinessScope;
use App\Support\BusinessContext;
use App\Support\PendingInvitations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BusinessController extends Controller
{
    /**
     * Neutral "no business yet" landing for a verified user who isn't a member of
     * any business. Offers creating their own shop or accepting a pending team
     * invitation. Self-correcting: once they have any membership (e.g. just
     * accepted an invite), send them on to the dashboard.
     */
    public function welcome(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        $hasMembership = Membership::withoutGlobalScope(BusinessScope::class)
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->exists();

        if ($hasMembership) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Onboarding/Welcome', [
            'pendingInvitations' => PendingInvitations::for($user),
        ]);
    }

    public function create(Request $request): Response
    {
        $hasExistingBusiness = Membership::withoutGlobalScope(BusinessScope::class)
            ->where('user_id', $request->user()->id)
            ->whereNull('deleted_at')
            ->exists();

        return Inertia::render('Onboarding/CreateBusiness', [
            'hasExistingBusiness' => $hasExistingBusiness,
        ]);
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
            'created_by_user_id' => $request->user()->id,
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
