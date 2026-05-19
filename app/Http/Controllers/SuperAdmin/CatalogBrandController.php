<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\ImageAttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CatalogBrandController extends Controller
{
    public function __construct(private readonly ImageAttachmentService $images) {}

    public function index(): Response
    {
        $brands = Brand::withCount('skus')->orderBy('sort_order')->get()
            ->map(fn (Brand $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'abbreviation' => $b->abbreviation,
                'primary_color_hex' => $b->primary_color_hex,
                'logo_path' => $b->logo_path,
                'logo_url' => $this->images->url($b, 'logo'),
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
        $data['sort_order'] ??= 0;

        $brand = Brand::create([
            'name' => $data['name'],
            'abbreviation' => $data['abbreviation'],
            'primary_color_hex' => $data['primary_color_hex'] ?? null,
            'sort_order' => $data['sort_order'],
        ]);

        if ($request->hasFile('logo')) {
            $this->images->set($brand, 'logo', $request->file('logo'));
        }

        return redirect()->route('super-admin.catalog.brands')
            ->with('success', __('flash.catalog.brand.added', ['name' => $brand->name]));
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $data = $request->validate($this->rules($request, $brand->id));
        $data['sort_order'] ??= 0;

        $brand->update([
            'name' => $data['name'],
            'abbreviation' => $data['abbreviation'],
            'primary_color_hex' => $data['primary_color_hex'] ?? null,
            'sort_order' => $data['sort_order'],
        ]);

        if ($request->hasFile('logo')) {
            $this->images->set($brand, 'logo', $request->file('logo'));
        } elseif ($request->boolean('logo_clear')) {
            $this->images->clear($brand, 'logo');
        }

        return redirect()->route('super-admin.catalog.brands')
            ->with('success', __('flash.catalog.brand.updated', ['name' => $brand->name]));
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
            'logo' => ['nullable', 'file', 'image:allow_svg', 'max:10240'],
            'logo_clear' => ['nullable', 'boolean'],
        ];
    }
}
