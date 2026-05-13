<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Support\BusinessContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /**
     * Locales the UI is currently translated into. Update when a new
     * `lang/<locale>/` directory is added.
     */
    private const SUPPORTED_LOCALES = ['en'];

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Settings/Index', [
            'preferences' => [
                'locale' => $user->locale ?? 'en',
                'timezone' => $user->timezone,
            ],
            'supportedLocales' => self::SUPPORTED_LOCALES,
        ]);
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:'.implode(',', self::SUPPORTED_LOCALES)],
            'timezone' => ['nullable', 'string', 'in:'.implode(',', timezone_identifiers_list())],
        ]);

        $request->user()->forceFill($validated)->save();

        return back()->with('success', __('flash.settings.preferences_updated'));
    }

    public function businesses(): Response
    {
        $business = Business::findOrFail(BusinessContext::currentId());

        return Inertia::render('Settings/Businesses', [
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'slug' => $business->slug,
                'logo_path' => $business->logo_path,
            ],
        ]);
    }

    public function updateBusiness(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $business = Business::findOrFail(BusinessContext::currentId());

        Gate::authorize('business.edit_settings', $business);

        $business->update([
            'name' => $request->name,
            'slug' => $this->uniqueSlug($request->name, $business->id),
        ]);

        return back()->with('success', __('flash.settings.business_name_updated'));
    }

    private function uniqueSlug(string $name, string $excludeId): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;

        while (Business::where('slug', $slug)->where('id', '!=', $excludeId)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
