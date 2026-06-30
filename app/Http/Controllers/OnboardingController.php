<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Business;
use App\Models\Sku;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Services\ImageAttachmentService;
use App\Services\OnboardingSeeder;
use App\Support\BusinessContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly OnboardingSeeder $seeder,
        private readonly ImageAttachmentService $images,
    ) {}

    /**
     * The multi-step "Tallie" wizard. Auto-shown after a business is created and
     * re-runnable from settings; resolves seedable brands and any prior answers.
     */
    public function show(Request $request): Response
    {
        $business = Business::findOrFail(BusinessContext::currentId());
        $user = $request->user();

        $seedableBrandNames = Sku::query()
            ->whereNull('owned_by_business_id')
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('brand_id');

        $brands = Brand::orderBy('sort_order')->get(['id', 'name', 'abbreviation'])
            ->map(fn (Brand $brand) => [
                'name' => $brand->name,
                'abbreviation' => $brand->abbreviation,
                'seedable' => $seedableBrandNames->contains($brand->id),
            ]);

        return Inertia::render('Onboarding/Wizard', [
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'logoUrl' => $this->images->url($business, 'logo'),
            ],
            'brands' => $brands,
            'roles' => ['twister', 'decorator', 'retailer'],
            'supportedLocales' => config('app.supported_locales'),
            'timezones' => timezone_identifiers_list(),
            'preferences' => [
                'locale' => $user->locale ?? 'en',
                'timezone' => $user->timezone,
                'badge_color' => $business->color,
            ],
            'answers' => $business->onboarding_answers,
            'alreadyCompleted' => $business->onboarding_completed_at !== null,
        ]);
    }

    public function complete(Request $request): RedirectResponse
    {
        $business = Business::findOrFail(BusinessContext::currentId());

        $data = $request->validate([
            'role' => ['required', 'string', 'in:twister,decorator,retailer'],
            'brands' => ['array'],
            'brands.*' => ['string', 'max:255'],
            'locale' => ['required', 'string', 'in:'.implode(',', array_keys(config('app.supported_locales')))],
            'timezone' => ['nullable', 'string', 'in:'.implode(',', timezone_identifiers_list())],
            'badge_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => ['nullable', 'mimes:png,jpg,jpeg,webp,svg', 'max:5120'],
            'locations' => ['array'],
            'locations.*.name' => ['required', 'string', 'max:255'],
            'locations.*.bins' => ['array'],
            'locations.*.bins.*' => ['string', 'max:255'],
        ]);

        $this->seeder->seed($business, $request->user(), $data);

        if ($request->hasFile('logo')) {
            $this->images->set($business, 'logo', $request->file('logo'));
        }

        $business->forceFill([
            'business_type' => $data['role'],
            'onboarding_answers' => Arr::except($data, ['logo']),
            'onboarding_completed_at' => now(),
        ])->save();

        return redirect()->route('dashboard')->with('success', __('flash.onboarding.completed'));
    }

    public function skip(Request $request): RedirectResponse
    {
        $business = Business::findOrFail(BusinessContext::currentId());

        $business->forceFill(['onboarding_completed_at' => now()])->save();

        return redirect()->route('dashboard');
    }

    /**
     * Remove onboarding sample products — but only the ones the owner hasn't built
     * real stock on. A sample SKU that has since received a non-sample movement is
     * kept and simply promoted to real (its sample flag cleared).
     */
    public function clearSamples(Request $request): RedirectResponse
    {
        $sampleLevels = StockLevel::where('is_sample', true)->get();

        $touchedSkuIds = StockMovement::where('is_sample', false)
            ->whereIn('sku_id', $sampleLevels->pluck('sku_id'))
            ->pluck('sku_id')
            ->unique();

        $removed = 0;

        foreach ($sampleLevels as $level) {
            if ($touchedSkuIds->contains($level->sku_id)) {
                $level->update(['is_sample' => false]);

                continue;
            }

            StockMovement::where('sku_id', $level->sku_id)
                ->where('is_sample', true)
                ->delete();

            $level->forceDelete();
            $removed++;
        }

        return back()->with('success', __('flash.onboarding.samples_cleared', ['count' => $removed]));
    }
}
