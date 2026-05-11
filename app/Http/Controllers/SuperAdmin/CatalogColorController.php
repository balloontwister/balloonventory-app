<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Color;
use App\Models\ColorFamily;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $data = $request->validate($this->rules());

        Color::create($data);

        return redirect()->route('super-admin.catalog.colors')
            ->with('success', 'Color "'.$data['name'].'" added.');
    }

    public function update(Request $request, Color $color): RedirectResponse
    {
        $data = $request->validate($this->rules($color->id));

        $color->update($data);

        return redirect()->route('super-admin.catalog.colors')
            ->with('success', 'Color "'.$color->name.'" updated.');
    }

    public function destroy(Color $color): RedirectResponse
    {
        $color->delete();

        return redirect()->route('super-admin.catalog.colors')
            ->with('success', 'Color deleted.');
    }

    private function rules(?string $ignoreId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'color_family_id' => ['required', 'uuid', 'exists:color_families,id'],
            'brand_id' => ['nullable', 'uuid', 'exists:brands,id'],
            'color_hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
