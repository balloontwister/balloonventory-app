<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\ColorFamily;
use App\Models\Material;
use App\Models\Shape;
use App\Models\Size;
use App\Models\Sku;
use App\Models\Texture;
use App\Models\Theme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Sku::with(['brand', 'size', 'shape', 'texture', 'color.colorFamily', 'material', 'themes'])
            ->whereNull('owned_by_business_id');

        if ($request->filled('brand')) {
            $query->where('brand_id', $request->brand);
        }

        if ($request->filled('size')) {
            $query->where('size_id', $request->size);
        }

        if ($request->filled('texture')) {
            $query->where('texture_id', $request->texture);
        }

        if ($request->filled('material')) {
            $query->where('material_id', $request->material);
        }

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('manufacturer_sku', 'like', "%{$term}%")
                    ->orWhere('price_code', 'like', "%{$term}%");
            });
        }

        $skus = $query->orderBy('name')->paginate(50)->withQueryString();

        return Inertia::render('SuperAdmin/Catalog/Index', [
            'skus' => $skus,
            'filters' => $request->only(['brand', 'size', 'texture', 'material', 'search']),
            'brands' => Brand::orderBy('sort_order')->get(['id', 'name', 'abbreviation']),
            'sizes' => Size::orderBy('sort_order')->get(['id', 'name', 'size_category']),
            'textures' => Texture::orderBy('sort_order')->get(['id', 'name', 'texture_family']),
            'materials' => Material::orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('SuperAdmin/Catalog/SkuForm', [
            'sku' => null,
            'brands' => Brand::orderBy('sort_order')->get(['id', 'name', 'abbreviation']),
            'sizes' => Size::orderBy('sort_order')->get(['id', 'name', 'size_category']),
            'shapes' => Shape::orderBy('sort_order')->get(['id', 'name']),
            'textures' => Texture::orderBy('sort_order')->get(['id', 'name', 'texture_family']),
            'colorFamilies' => ColorFamily::with(['colors' => fn ($q) => $q->orderBy('sort_order')])->orderBy('sort_order')->get(),
            'themes' => Theme::orderBy('sort_order')->get(['id', 'name']),
            'materials' => Material::orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules($request));

        $sku = Sku::create($data);

        if (! empty($data['theme_ids'])) {
            $sku->themes()->sync($data['theme_ids']);
        }

        return redirect()->route('super-admin.catalog.skus')
            ->with('success', 'SKU "'.$sku->name.'" created.');
    }

    public function edit(Sku $sku): Response
    {
        // Only allow editing shared catalog SKUs from this controller.
        abort_if($sku->owned_by_business_id !== null, 403);

        return Inertia::render('SuperAdmin/Catalog/SkuForm', [
            'sku' => $sku->load(['brand', 'size', 'shape', 'texture', 'color', 'material', 'themes']),
            'brands' => Brand::orderBy('sort_order')->get(['id', 'name', 'abbreviation']),
            'sizes' => Size::orderBy('sort_order')->get(['id', 'name', 'size_category']),
            'shapes' => Shape::orderBy('sort_order')->get(['id', 'name']),
            'textures' => Texture::orderBy('sort_order')->get(['id', 'name', 'texture_family']),
            'colorFamilies' => ColorFamily::with(['colors' => fn ($q) => $q->orderBy('sort_order')])->orderBy('sort_order')->get(),
            'themes' => Theme::orderBy('sort_order')->get(['id', 'name']),
            'materials' => Material::orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Sku $sku): RedirectResponse
    {
        abort_if($sku->owned_by_business_id !== null, 403);

        $data = $request->validate($this->rules($request, $sku->id));

        $sku->update($data);
        $sku->themes()->sync($data['theme_ids'] ?? []);

        return redirect()->route('super-admin.catalog.skus')
            ->with('success', 'SKU "'.$sku->name.'" updated.');
    }

    public function destroy(Sku $sku): RedirectResponse
    {
        abort_if($sku->owned_by_business_id !== null, 403);

        $sku->delete();

        return redirect()->route('super-admin.catalog.skus')
            ->with('success', 'SKU deleted.');
    }

    private function rules(Request $request, ?string $ignoreId = null): array
    {
        // (brand_id, manufacturer_sku) must be unique among active SKUs when
        // manufacturer_sku is filled. Multiple NULL-manufacturer_sku rows are
        // allowed — the admin may not have product numbers for every variant.
        $mfrSkuUnique = Rule::unique('skus', 'manufacturer_sku')
            ->where(fn ($q) => $q->where('brand_id', $request->input('brand_id'))->whereNull('deleted_at'))
            ->ignore($ignoreId);

        return [
            'name' => ['required', 'string', 'max:255'],
            'brand_id' => ['required', 'uuid', 'exists:brands,id'],
            'size_id' => ['nullable', 'uuid', 'exists:sizes,id'],
            'shape_id' => ['nullable', 'uuid', 'exists:shapes,id'],
            'texture_id' => ['nullable', 'uuid', 'exists:textures,id'],
            'color_id' => ['nullable', 'uuid', 'exists:colors,id'],
            'material_id' => ['nullable', 'uuid', 'exists:materials,id'],
            'is_printed' => ['boolean'],
            'default_count_per_bag' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'manufacturer_sku' => ['nullable', 'string', 'max:100', $mfrSkuUnique],
            'price_code' => ['nullable', 'string', 'max:50'],
            'theme_ids' => ['nullable', 'array'],
            'theme_ids.*' => ['uuid', 'exists:themes,id'],
        ];
    }
}
