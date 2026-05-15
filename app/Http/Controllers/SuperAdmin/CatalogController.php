<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Color;
use App\Models\ColorFamily;
use App\Models\Material;
use App\Models\PackagingType;
use App\Models\PriceCode;
use App\Models\PrintColor;
use App\Models\PrintSide;
use App\Models\Shape;
use App\Models\Size;
use App\Models\Sku;
use App\Models\Texture;
use App\Models\Theme;
use App\Services\Catalog\CatalogImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function __construct(private readonly CatalogImageService $imageService) {}

    public function index(Request $request): Response
    {
        $locale = app()->getLocale();

        $with = $locale === 'en'
            ? ['brand', 'balloonSize.size', 'shape', 'texture', 'color.colorFamily', 'material', 'themes', 'packagingType', 'priceCode']
            : [
                'brand',
                'balloonSize' => fn ($q) => $q->with('size'),
                'shape' => fn ($q) => $q->withTranslations($locale),
                'texture' => fn ($q) => $q->withTranslations($locale),
                'color' => fn ($q) => $q->with('colorFamily')->withTranslations($locale),
                'material' => fn ($q) => $q->withTranslations($locale),
                'themes',
                'packagingType',
                'priceCode',
            ];

        $query = Sku::with($with)->whereNull('owned_by_business_id');

        if ($request->filled('brand')) {
            $query->where('brand_id', $request->brand);
        }

        if ($request->filled('size')) {
            // Filter by size-family through balloon_size.
            $query->whereHas('balloonSize', function ($q) use ($request) {
                $q->where('size_id', $request->size);
            });
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
                    ->orWhere('computed_name', 'like', "%{$term}%")
                    ->orWhere('warehouse_sku', 'like', "%{$term}%")
                    ->orWhere('upc', 'like', "%{$term}%");
            });
        }

        $skus = $query->orderBy('name')->paginate(50)->withQueryString();

        if ($locale !== 'en') {
            $skus->getCollection()->each(function (Sku $sku) {
                if ($sku->shape) {
                    $sku->shape->name = $sku->shape->translated('name');
                }
                if ($sku->texture) {
                    $sku->texture->name = $sku->texture->translated('name');
                }
                if ($sku->material) {
                    $sku->material->name = $sku->material->translated('name');
                }
                if ($sku->color) {
                    $sku->color->name = $sku->color->translated('name');
                }
            });
        }

        $skus->getCollection()->each(fn (Sku $sku) => $sku->images = $this->imageService->urls($sku));

        return Inertia::render('SuperAdmin/Catalog/Index', [
            'skus' => $skus,
            'filters' => $request->only(['brand', 'size', 'texture', 'material', 'search']),
            'brands' => Brand::orderBy('sort_order')->get(['id', 'name', 'abbreviation']),
            'sizes' => Size::orderBy('sort_order')->get(['id', 'name', 'size_category']),
            'textures' => $this->translated(Texture::withTranslations()->orderBy('sort_order')->get(['id', 'name'])),
            'materials' => $this->translated(Material::withTranslations()->orderBy('sort_order')->get(['id', 'name'])),
        ]);
    }

    public function create(): Response
    {
        $locale = app()->getLocale();

        $colorFamilies = ColorFamily::with(['colors' => fn ($q) => $q->orderBy('sort_order')])->orderBy('sort_order')->get();
        if ($locale !== 'en') {
            $colorFamilies->each(function (ColorFamily $family) use ($locale) {
                $family->loadTranslations($locale);
                $family->name = $family->translated('name');
                $family->colors->each(function (Color $color) use ($locale) {
                    $color->loadTranslations($locale);
                    $color->name = $color->translated('name');
                });
            });
        }

        return Inertia::render('SuperAdmin/Catalog/SkuForm', [
            'sku' => null,
            'brands' => Brand::orderBy('sort_order')->get(['id', 'name', 'abbreviation']),
            'sizes' => Size::orderBy('sort_order')->get(['id', 'name', 'size_category']),
            'shapes' => $this->translated(Shape::withTranslations()->orderBy('sort_order')->get(['id', 'name', 'material_id'])),
            'textures' => $this->translated(Texture::with('textureFamily:id,name')->withTranslations()->orderBy('sort_order')->get(['id', 'name', 'texture_family_id', 'material_id'])),
            'colorFamilies' => $colorFamilies,
            'themes' => $this->translated(Theme::withTranslations()->orderBy('sort_order')->get(['id', 'name'])),
            'materials' => $this->translated(Material::withTranslations()->orderBy('sort_order')->get(['id', 'name'])),
            'balloonSizes' => BalloonSize::with(['size', 'brand'])->orderBy('sort_order')->get(['id', 'name', 'brand_id', 'material_id', 'size_id']),
            'packagingTypes' => PackagingType::orderBy('sort_order')->get(['id', 'name']),
            'priceCodes' => PriceCode::orderBy('sort_order')->get(['id', 'brand_id', 'code']),
            'printColors' => PrintColor::orderBy('sort_order')->get(['id', 'name']),
            'printSides' => PrintSide::orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules($request));

        $sku = Sku::create($data);

        if (! empty($data['theme_ids'])) {
            $sku->themes()->sync($data['theme_ids']);
        }

        if (! empty($data['print_color_ids'])) {
            $sku->printColors()->sync($data['print_color_ids']);
        }

        if (! empty($data['print_side_ids'])) {
            $sku->printSides()->sync($data['print_side_ids']);
        }

        $this->syncImages($request, $sku);

        return redirect()->route('super-admin.catalog.skus')
            ->with('success', __('flash.catalog.sku.created', ['name' => $sku->name]));
    }

    public function edit(Sku $sku): Response
    {
        abort_if($sku->owned_by_business_id !== null, 403);

        $locale = app()->getLocale();

        $colorFamilies = ColorFamily::with(['colors' => fn ($q) => $q->orderBy('sort_order')])->orderBy('sort_order')->get();
        if ($locale !== 'en') {
            $colorFamilies->each(function (ColorFamily $family) use ($locale) {
                $family->loadTranslations($locale);
                $family->name = $family->translated('name');
                $family->colors->each(function (Color $color) use ($locale) {
                    $color->loadTranslations($locale);
                    $color->name = $color->translated('name');
                });
            });
        }

        $sku->load([
            'brand', 'balloonSize', 'shape', 'texture', 'color',
            'material', 'themes', 'packagingType', 'priceCode',
            'printColors', 'printSides',
        ]);
        $sku->images = $this->imageService->urls($sku);

        return Inertia::render('SuperAdmin/Catalog/SkuForm', [
            'sku' => $sku,
            'brands' => Brand::orderBy('sort_order')->get(['id', 'name', 'abbreviation']),
            'sizes' => Size::orderBy('sort_order')->get(['id', 'name', 'size_category']),
            'shapes' => $this->translated(Shape::withTranslations()->orderBy('sort_order')->get(['id', 'name', 'material_id'])),
            'textures' => $this->translated(Texture::with('textureFamily:id,name')->withTranslations()->orderBy('sort_order')->get(['id', 'name', 'texture_family_id', 'material_id'])),
            'colorFamilies' => $colorFamilies,
            'themes' => $this->translated(Theme::withTranslations()->orderBy('sort_order')->get(['id', 'name'])),
            'materials' => $this->translated(Material::withTranslations()->orderBy('sort_order')->get(['id', 'name'])),
            'balloonSizes' => BalloonSize::with(['size', 'brand'])->orderBy('sort_order')->get(['id', 'name', 'brand_id', 'material_id', 'size_id']),
            'packagingTypes' => PackagingType::orderBy('sort_order')->get(['id', 'name']),
            'priceCodes' => PriceCode::orderBy('sort_order')->get(['id', 'brand_id', 'code']),
            'printColors' => PrintColor::orderBy('sort_order')->get(['id', 'name']),
            'printSides' => PrintSide::orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Sku $sku): RedirectResponse
    {
        abort_if($sku->owned_by_business_id !== null, 403);

        $data = $request->validate($this->rules($request, $sku->id));

        $sku->update($data);
        $sku->themes()->sync($data['theme_ids'] ?? []);
        $sku->printColors()->sync($data['print_color_ids'] ?? []);
        $sku->printSides()->sync($data['print_side_ids'] ?? []);

        $this->syncImages($request, $sku);

        return redirect()->route('super-admin.catalog.skus')
            ->with('success', __('flash.catalog.sku.updated', ['name' => $sku->name]));
    }

    private function syncImages(Request $request, Sku $sku): void
    {
        foreach (['single', 'cluster'] as $slot) {
            if ($request->hasFile("{$slot}_image")) {
                $this->imageService->set($sku, $slot, $request->file("{$slot}_image"));
            } elseif ($request->boolean("{$slot}_image_clear")) {
                $this->imageService->clear($sku, $slot);
            }
        }
    }

    public function destroy(Sku $sku): RedirectResponse
    {
        abort_if($sku->owned_by_business_id !== null, 403);

        $sku->delete();

        return redirect()->route('super-admin.catalog.skus')
            ->with('success', __('flash.catalog.sku.deleted'));
    }

    private function translated(Collection $items): Collection
    {
        if (app()->getLocale() === 'en') {
            return $items;
        }

        return $items->map(function ($item) {
            $item->name = $item->translated('name');
            if (array_key_exists('description', $item->getAttributes())) {
                $item->description = $item->translated('description');
            }

            return $item;
        });
    }

    private function rules(Request $request, ?string $ignoreId = null): array
    {
        $whSkuUnique = Rule::unique('skus', 'warehouse_sku')
            ->where(fn ($q) => $q->where('brand_id', $request->input('brand_id'))->whereNull('deleted_at'))
            ->ignore($ignoreId);

        // ups must be unique among active rows, but nullable (multiple null = no conflict in MySQL).
        $upcUnique = Rule::unique('skus', 'upc')
            ->where(fn ($q) => $q->whereNull('deleted_at'))
            ->ignore($ignoreId);

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'brand_id' => ['required', 'uuid', 'exists:brands,id'],
            'material_id' => ['nullable', 'uuid', 'exists:materials,id'],
            'balloon_size_id' => ['nullable', 'uuid', 'exists:balloon_sizes,id'],
            'shape_id' => ['nullable', 'uuid', 'exists:shapes,id'],
            'texture_id' => ['nullable', 'uuid', 'exists:textures,id'],
            'color_id' => ['nullable', 'uuid', 'exists:colors,id'],
            'is_printed' => ['boolean'],
            'default_count_per_bag' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'warehouse_sku' => ['nullable', 'string', 'max:100', $whSkuUnique],
            'upc' => ['nullable', 'string', 'max:50', $upcUnique],
            'ean' => ['nullable', 'string', 'max:50'],
            'asin' => ['nullable', 'string', 'max:50'],
            'mfg_no' => ['nullable', 'string', 'max:100'],
            'packaging_id' => ['nullable', 'uuid', 'exists:packaging_types,id'],
            'single_image' => ['nullable', 'file', 'image', 'max:10240'],
            'single_image_clear' => ['nullable', 'boolean'],
            'cluster_image' => ['nullable', 'file', 'image', 'max:10240'],
            'cluster_image_clear' => ['nullable', 'boolean'],
            'price_code_id' => ['nullable', 'uuid', 'exists:price_codes,id'],
            'is_active' => ['boolean'],
            'discontinued_at' => ['nullable', 'date'],
            'product_version' => ['nullable', 'string', 'max:50'],
            'theme_ids' => ['nullable', 'array'],
            'theme_ids.*' => ['uuid', 'exists:themes,id'],
            'print_color_ids' => ['nullable', 'array'],
            'print_color_ids.*' => ['uuid', 'exists:print_colors,id'],
            'print_side_ids' => ['nullable', 'array'],
            'print_side_ids.*' => ['uuid', 'exists:print_sides,id'],
        ];
    }
}
