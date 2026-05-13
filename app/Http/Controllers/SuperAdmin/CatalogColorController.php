<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Color;
use App\Models\ColorFamily;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CatalogColorController extends Controller
{
    public function index(): Response
    {
        $colorFamilies = ColorFamily::with([
            'colors' => fn ($q) => $q->with('brand')->orderBy('sort_order')->orderBy('name'),
        ])->orderBy('sort_order')->get();

        return Inertia::render('SuperAdmin/Catalog/Colors', [
            'colorFamilies' => $colorFamilies,
            'brands' => Brand::orderBy('sort_order')->get(['id', 'name', 'abbreviation']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules($request));

        Color::create($data);

        return redirect()->route('super-admin.catalog.colors')
            ->with('success', __('flash.catalog.color.added', ['name' => $data['name']]));
    }

    public function update(Request $request, Color $color): RedirectResponse
    {
        $data = $request->validate($this->rules($request, $color->id));

        $color->update($data);

        return redirect()->route('super-admin.catalog.colors')
            ->with('success', __('flash.catalog.color.updated', ['name' => $color->name]));
    }

    public function destroy(Color $color): RedirectResponse
    {
        $color->delete();

        return redirect()->route('super-admin.catalog.colors')
            ->with('success', __('flash.catalog.color.deleted'));
    }

    private function rules(Request $request, ?string $ignoreId = null): array
    {
        // Uniqueness: a brand cannot have two active colors with the same name.
        // For generic (null brand) colors we still enforce name uniqueness across all generics.
        $uniqueRule = Rule::unique('colors', 'name')
            ->where(fn ($q) => $q->where('brand_id', $request->input('brand_id'))->whereNull('deleted_at'))
            ->ignore($ignoreId);

        return [
            'name' => ['required', 'string', 'max:100', $uniqueRule],
            'color_family_id' => ['required', 'uuid', 'exists:color_families,id'],
            'brand_id' => ['nullable', 'uuid', 'exists:brands,id'],
            'color_hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
