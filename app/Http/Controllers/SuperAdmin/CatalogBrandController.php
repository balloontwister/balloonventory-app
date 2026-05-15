<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CatalogBrandController extends Controller
{
    public function index(): Response
    {
        $brands = Brand::withCount('skus')->orderBy('sort_order')->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'abbreviation' => $b->abbreviation,
                'primary_color_hex' => $b->primary_color_hex,
                'logo_path' => $b->logo_path,
                'logo_url' => $b->logo_path ? Storage::disk('public')->url($b->logo_path) : null,
                'sort_order' => $b->sort_order,
                'skus_count' => $b->skus_count,
            ]);

        return Inertia::render('SuperAdmin/Catalog/Brands', [
            'brands' => $brands,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules($request));

        $logoPath = $this->handleLogoUpload($request, null);
        if ($logoPath !== null) {
            $data['logo_path'] = $logoPath;
        }

        // logo file is not a column; drop it after extracting the path.
        unset($data['logo']);
        $data['sort_order'] ??= 0;

        Brand::create($data);

        return redirect()->route('super-admin.catalog.brands')
            ->with('success', __('flash.catalog.brand.added', ['name' => $data['name']]));
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $data = $request->validate($this->rules($request, $brand->id));

        $logoPath = $this->handleLogoUpload($request, $brand);
        if ($logoPath !== null) {
            $data['logo_path'] = $logoPath;
        } elseif ($request->boolean('logo_clear')) {
            $this->deleteLogo($brand);
            $data['logo_path'] = null;
        }

        unset($data['logo'], $data['logo_clear']);
        $data['sort_order'] ??= 0;

        $brand->update($data);

        return redirect()->route('super-admin.catalog.brands')
            ->with('success', __('flash.catalog.brand.updated', ['name' => $brand->name]));
    }

    /**
     * Stores the uploaded logo (if any) and returns the storage path. Deletes
     * any previously-attached logo on the brand. Returns null if no upload.
     */
    private function handleLogoUpload(Request $request, ?Brand $brand): ?string
    {
        if (! $request->hasFile('logo')) {
            return null;
        }

        if ($brand) {
            $this->deleteLogo($brand);
        }

        return $request->file('logo')->store('brand-logos', 'public');
    }

    private function deleteLogo(Brand $brand): void
    {
        if ($brand->logo_path && Storage::disk('public')->exists($brand->logo_path)) {
            Storage::disk('public')->delete($brand->logo_path);
        }
    }

    private function rules(Request $request, ?string $ignoreId = null): array
    {
        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('brands', 'name')->whereNull('deleted_at')->ignore($ignoreId),
            ],
            'abbreviation' => [
                'required', 'string', 'max:10',
                Rule::unique('brands', 'abbreviation')->whereNull('deleted_at')->ignore($ignoreId),
            ],
            'primary_color_hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg', 'max:1024'],
            'logo_clear' => ['nullable', 'boolean'],
        ];
    }
}
