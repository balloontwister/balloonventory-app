<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\BrandGs1Prefix;
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

    public function show(Brand $brand): Response
    {
        $brand->loadCount('skus');
        $brand->load(['gs1Prefixes' => fn ($q) => $q->orderBy('prefix')]);
        $brand->logo_url = $this->images->url($brand, 'logo');

        return Inertia::render('SuperAdmin/Catalog/BrandShow', [
            'brand' => $brand,
        ]);
    }

    public function edit(Brand $brand): Response
    {
        $brand->load(['gs1Prefixes' => fn ($q) => $q->orderBy('prefix')]);
        $brand->logo_url = $this->images->url($brand, 'logo');

        return Inertia::render('SuperAdmin/Catalog/BrandEdit', [
            'brand' => $brand,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules($request));
        $data['sort_order'] ??= 0;

        $brand = Brand::create($this->attributes($data));

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

        $brand->update($this->attributes($data));

        if ($request->hasFile('logo')) {
            $this->images->set($brand, 'logo', $request->file('logo'));
        } elseif ($request->boolean('logo_clear')) {
            $this->images->clear($brand, 'logo');
        }

        $redirect = $request->boolean('return_to_show')
            ? redirect()->route('super-admin.catalog.brands.show', $brand)
            : redirect()->route('super-admin.catalog.brands');

        return $redirect->with('success', __('flash.catalog.brand.updated', ['name' => $brand->name]));
    }

    public function storeGs1Prefix(Request $request, Brand $brand): RedirectResponse
    {
        $data = $request->validate([
            'prefix' => [
                'required', 'string', 'regex:/^[0-9]{6,12}$/',
                Rule::unique('brand_gs1_prefixes', 'prefix')->where('brand_id', $brand->id),
            ],
        ]);

        $brand->gs1Prefixes()->create(['prefix' => $data['prefix']]);

        return redirect()->route('super-admin.catalog.brands.show', $brand)
            ->with('success', __('flash.catalog.brand.gs1_added', ['prefix' => $data['prefix']]));
    }

    public function destroyGs1Prefix(Brand $brand, BrandGs1Prefix $prefix): RedirectResponse
    {
        abort_unless($prefix->brand_id === $brand->id, 404);

        $value = $prefix->prefix;
        $prefix->delete();

        return redirect()->route('super-admin.catalog.brands.show', $brand)
            ->with('success', __('flash.catalog.brand.gs1_removed', ['prefix' => $value]));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'abbreviation' => $data['abbreviation'],
            'description' => $data['description'] ?? null,
            'url_1' => $data['url_1'] ?? null,
            'url_2' => $data['url_2'] ?? null,
            'primary_color_hex' => $data['primary_color_hex'] ?? null,
            'secondary_color_hex' => $data['secondary_color_hex'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
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
            'description' => ['nullable', 'string', 'max:2000'],
            'url_1' => ['nullable', 'string', 'max:191', 'url'],
            'url_2' => ['nullable', 'string', 'max:191', 'url'],
            'primary_color_hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color_hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'logo' => ['nullable', 'file', 'image:allow_svg', 'max:10240'],
            'logo_clear' => ['nullable', 'boolean'],
            'return_to_show' => ['nullable', 'boolean'],
        ];
    }
}
