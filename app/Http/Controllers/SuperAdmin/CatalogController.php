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
use App\Models\TextureFamily;
use App\Models\Theme;
use App\Rules\ValidGtin;
use App\Services\ImageAttachmentService;
use App\Support\Gtin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function __construct(private readonly ImageAttachmentService $imageService) {}

    public function index(Request $request): Response
    {
        $request->validate([
            'brand' => ['nullable', 'uuid'],
            'size' => ['nullable', 'uuid'],
            'shape' => ['nullable', 'uuid'],
            'texture_family' => ['nullable', 'uuid'],
            'color_family' => ['nullable', 'uuid'],
            'material' => ['nullable', 'uuid'],
            'theme' => ['nullable', 'uuid'],
            'printed' => ['nullable', 'in:0,1'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $locale = app()->getLocale();

        $with = $locale === 'en'
            ? [
                'brand',
                'balloonSize' => fn ($q) => $q->with(['size', 'shape']),
                'color' => fn ($q) => $q->with(['colorFamily', 'texture']),
                'material',
                'themes',
                'packagingType',
                'priceCode',
            ]
            : [
                'brand',
                'balloonSize' => fn ($q) => $q->with(['size', 'shape' => fn ($sq) => $sq->withTranslations($locale)]),
                'color' => fn ($q) => $q->with(['colorFamily', 'texture' => fn ($tq) => $tq->withTranslations($locale)])->withTranslations($locale),
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

        if ($request->filled('shape')) {
            // Shape lives on balloon_size, not directly on sku.
            $query->whereHas('balloonSize', fn ($q) => $q->where('shape_id', $request->shape));
        }

        if ($request->filled('theme')) {
            $query->whereHas('themes', fn ($q) => $q->where('themes.id', $request->theme));
        }

        if ($request->filled('texture_family')) {
            $query->whereHas('color.texture', fn ($q) => $q->where('texture_family_id', $request->texture_family));
        }

        if ($request->filled('color_family')) {
            $query->whereHas('color', fn ($q) => $q->where('color_family_id', $request->color_family));
        }

        if ($request->filled('material')) {
            $query->where('material_id', $request->material);
        }

        if ($request->filled('printed')) {
            $query->where('is_printed', $request->boolean('printed'));
        }

        if ($request->filled('search')) {
            // Single source of truth for catalog search — same fields and
            // word-splitting as the Scan fallback and Inventory search.
            $query->matchesSearch($request->search);
        }

        $skus = $query->orderBy('name')->paginate(100)->withQueryString();

        if ($locale !== 'en') {
            $skus->getCollection()->each(function (Sku $sku) {
                if ($sku->balloonSize?->shape) {
                    $sku->balloonSize->shape->name = $sku->balloonSize->shape->translated('name');
                }
                if ($sku->color?->texture) {
                    $sku->color->texture->name = $sku->color->texture->translated('name');
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
            'filters' => $request->only(['brand', 'size', 'shape', 'texture_family', 'color_family', 'material', 'theme', 'printed', 'search']),
            'brands' => Brand::orderBy('sort_order')->get(['id', 'name', 'abbreviation']),
            'sizes' => Size::orderBy('sort_order')->get(['id', 'name']),
            'shapes' => $this->translated(Shape::withTranslations()->orderBy('sort_order')->get(['id', 'name'])),
            'textureFamilies' => TextureFamily::orderBy('sort_order')->get(['id', 'name']),
            'colorFamilies' => ColorFamily::orderBy('sort_order')->get(['id', 'name']),
            'materials' => $this->translated(Material::withTranslations()->orderBy('sort_order')->get(['id', 'name'])),
            'themes' => $this->translated(Theme::withTranslations()->orderBy('sort_order')->get(['id', 'name'])),
        ]);
    }

    public function create(): Response
    {
        $locale = app()->getLocale();

        $colorFamilies = ColorFamily::with(['colors' => fn ($q) => $q->with('texture:id,name')->orderBy('sort_order')])->orderBy('sort_order')->get();
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
            'sizes' => Size::orderBy('sort_order')->get(['id', 'name']),
            'shapes' => $this->translated(Shape::withTranslations()->orderBy('sort_order')->get(['id', 'name', 'material_id'])),
            'colorFamilies' => $colorFamilies,
            'themes' => $this->translated(Theme::withTranslations()->orderBy('sort_order')->get(['id', 'name'])),
            'materials' => $this->translated(Material::withTranslations()->orderBy('sort_order')->get(['id', 'name'])),
            'balloonSizes' => BalloonSize::with(['size', 'brand', 'shape'])->orderBy('sort_order')->get(['id', 'name', 'brand_id', 'material_id', 'size_id', 'shape_id']),
            'packagingTypes' => PackagingType::orderBy('sort_order')->get(['id', 'name']),
            'priceCodes' => PriceCode::orderBy('sort_order')->get(['id', 'brand_id', 'code']),
            'printColors' => PrintColor::orderBy('sort_order')->get(['id', 'name']),
            'printSides' => PrintSide::orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->normalizeBarcodeInput($request);

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

        return redirect()->route('admin.catalog.skus.show', $sku)
            ->with('success', __('flash.catalog.sku.created', ['name' => $sku->name]));
    }

    public function show(Request $request, Sku $sku): Response
    {
        abort_if($sku->owned_by_business_id !== null, 403);

        $locale = app()->getLocale();

        $withIdentical = fn ($q) => $q->with(['packagingType', 'balloonSize']);

        $with = $locale === 'en'
            ? [
                'brand',
                'balloonSize.shape',
                'color.texture',
                'material',
                'themes',
                'packagingType',
                'priceCode',
                'printColors',
                'printSides',
                'identicalSkus' => $withIdentical,
            ]
            : [
                'brand',
                'balloonSize' => fn ($q) => $q->with(['shape' => fn ($sq) => $sq->withTranslations($locale)]),
                'color' => fn ($q) => $q->with(['texture' => fn ($tq) => $tq->withTranslations($locale)])->withTranslations($locale),
                'material' => fn ($q) => $q->withTranslations($locale),
                'themes' => fn ($q) => $q->withTranslations($locale),
                'packagingType',
                'priceCode',
                'printColors',
                'printSides',
                'identicalSkus' => $withIdentical,
            ];

        $sku->load($with);

        if ($locale !== 'en') {
            if ($sku->balloonSize?->shape) {
                $sku->balloonSize->shape->name = $sku->balloonSize->shape->translated('name');
            }
            if ($sku->color?->texture) {
                $sku->color->texture->name = $sku->color->texture->translated('name');
            }
            if ($sku->material) {
                $sku->material->name = $sku->material->translated('name');
            }
            if ($sku->color) {
                $sku->color->name = $sku->color->translated('name');
            }
            $sku->themes->each(function (Theme $theme) {
                $theme->name = $theme->translated('name');
            });
        }

        $sku->images = $this->imageService->urls($sku);

        return Inertia::render('SuperAdmin/Catalog/SkuShow', [
            'sku' => $sku,
            // Query string of the originating list, so the back link can
            // restore the user's filters, page, and scroll position.
            'returnQuery' => $request->query('return', ''),
        ]);
    }

    public function edit(Sku $sku): Response
    {
        abort_if($sku->owned_by_business_id !== null, 403);

        $locale = app()->getLocale();

        $colorFamilies = ColorFamily::with(['colors' => fn ($q) => $q->with('texture:id,name')->orderBy('sort_order')])->orderBy('sort_order')->get();
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
            'brand', 'balloonSize.shape', 'color',
            'material', 'themes', 'packagingType', 'priceCode',
            'printColors', 'printSides',
        ]);
        $sku->images = $this->imageService->urls($sku);

        return Inertia::render('SuperAdmin/Catalog/SkuForm', [
            'sku' => $sku,
            'brands' => Brand::orderBy('sort_order')->get(['id', 'name', 'abbreviation']),
            'sizes' => Size::orderBy('sort_order')->get(['id', 'name']),
            'shapes' => $this->translated(Shape::withTranslations()->orderBy('sort_order')->get(['id', 'name', 'material_id'])),
            'colorFamilies' => $colorFamilies,
            'themes' => $this->translated(Theme::withTranslations()->orderBy('sort_order')->get(['id', 'name'])),
            'materials' => $this->translated(Material::withTranslations()->orderBy('sort_order')->get(['id', 'name'])),
            'balloonSizes' => BalloonSize::with(['size', 'brand', 'shape'])->orderBy('sort_order')->get(['id', 'name', 'brand_id', 'material_id', 'size_id', 'shape_id']),
            'packagingTypes' => PackagingType::orderBy('sort_order')->get(['id', 'name']),
            'priceCodes' => PriceCode::orderBy('sort_order')->get(['id', 'brand_id', 'code']),
            'printColors' => PrintColor::orderBy('sort_order')->get(['id', 'name']),
            'printSides' => PrintSide::orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Sku $sku): RedirectResponse
    {
        abort_if($sku->owned_by_business_id !== null, 403);

        $this->normalizeBarcodeInput($request);

        $data = $request->validate($this->rules($request, $sku->id));

        $sku->update($data);
        $sku->themes()->sync($data['theme_ids'] ?? []);
        $sku->printColors()->sync($data['print_color_ids'] ?? []);
        $sku->printSides()->sync($data['print_side_ids'] ?? []);

        $this->syncImages($request, $sku);

        return redirect()->route('admin.catalog.skus.show', $sku)
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

        return redirect()->route('admin.catalog.skus')
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

    /**
     * Normalize barcode inputs to digit-only form BEFORE validation, so the
     * uniqueness rule compares against the same canonical value the model will
     * store (Sku::setUpcAttribute strips separators on save). Without this a
     * differently-formatted duplicate ("012-345-678905" vs "012345678905")
     * passes the unique rule and then collides on the DB index, surfacing as a
     * 500 instead of a clean validation error. Inputs with no digits (e.g.
     * "na") are left untouched so ValidGtin handles them as before.
     */
    private function normalizeBarcodeInput(Request $request): void
    {
        foreach (['upc', 'ean'] as $field) {
            $value = $request->input($field);

            if (! is_string($value)) {
                continue;
            }

            $digits = Gtin::digitsOnly($value);

            if ($digits !== '' && $digits !== $value) {
                $request->merge([$field => $digits]);
            }
        }
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
            'color_id' => ['nullable', 'uuid', 'exists:colors,id'],
            'is_printed' => ['boolean'],
            'default_count_per_bag' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'warehouse_sku' => ['nullable', 'string', 'max:100', $whSkuUnique],
            'upc' => ['nullable', 'string', 'max:50', new ValidGtin, $upcUnique],
            'ean' => ['nullable', 'string', 'max:50', new ValidGtin],
            'asin' => ['nullable', 'string', 'max:50'],
            'mfg_no' => ['nullable', 'string', 'max:100'],
            'packaging_id' => ['nullable', 'uuid', 'exists:packaging_types,id'],
            'single_image' => ['nullable', 'file', 'image:allow_svg', 'max:10240'],
            'single_image_clear' => ['nullable', 'boolean'],
            'cluster_image' => ['nullable', 'file', 'image:allow_svg', 'max:10240'],
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
