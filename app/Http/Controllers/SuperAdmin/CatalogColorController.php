<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Color;
use App\Models\ColorFamily;
use App\Models\Texture;
use App\Services\ImageAttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CatalogColorController extends Controller
{
    public function __construct(private readonly ImageAttachmentService $images) {}

    public function index(): Response
    {
        $locale = app()->getLocale();

        $colorFamilies = ColorFamily::with([
            'colors' => fn ($q) => $q->with('brand')->orderBy('sort_order')->orderBy('name'),
        ])->orderBy('sort_order')->get();

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

        // Attach image URLs to every color so Vue can render thumbnails.
        $colorFamilies->each(function (ColorFamily $family) {
            $family->colors->each(function (Color $color) {
                $urls = $this->images->urls($color);
                $color->single_image_url = $urls['single'] ?? null;
                $color->cluster_image_url = $urls['cluster'] ?? null;
            });
        });

        return Inertia::render('SuperAdmin/Catalog/Colors', [
            'colorFamilies' => $colorFamilies,
            'brands' => Brand::orderBy('sort_order')->get(['id', 'name', 'abbreviation']),
            'textures' => Texture::with('textureFamily:id,name')->orderBy('sort_order')->get(['id', 'name', 'brand_id', 'texture_family_id']),
        ]);
    }

    public function show(Color $color): Response
    {
        $color->load(['colorFamily', 'brand', 'material', 'texture']);

        $urls = $this->images->urls($color);
        $color->single_image_url = $urls['single'] ?? null;
        $color->cluster_image_url = $urls['cluster'] ?? null;

        return Inertia::render('SuperAdmin/Catalog/ColorShow', [
            'color' => $color,
        ]);
    }

    public function edit(Color $color): Response
    {
        $color->load(['colorFamily', 'brand', 'material', 'texture']);

        $urls = $this->images->urls($color);
        $color->single_image_url = $urls['single'] ?? null;
        $color->cluster_image_url = $urls['cluster'] ?? null;

        return Inertia::render('SuperAdmin/Catalog/ColorEdit', [
            'color' => $color,
            'colorFamilies' => ColorFamily::orderBy('sort_order')->get(['id', 'name']),
            'brands' => Brand::orderBy('sort_order')->get(['id', 'name', 'abbreviation']),
            'textures' => Texture::with('textureFamily:id,name')->orderBy('sort_order')->get(['id', 'name', 'brand_id', 'texture_family_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules($request));
        $data['sort_order'] ??= 0;

        $color = Color::create([
            'name' => $data['name'],
            'color_family_id' => $data['color_family_id'],
            'brand_id' => $data['brand_id'],
            'texture_id' => $data['texture_id'],
            'color_hex' => $data['color_hex'] ?? null,
            'sort_order' => $data['sort_order'],
            'description' => $data['description'] ?? null,
        ]);

        $this->syncImages($request, $color);

        return redirect()->route('super-admin.catalog.colors')
            ->with('success', __('flash.catalog.color.added', ['name' => $color->name]));
    }

    public function update(Request $request, Color $color): RedirectResponse
    {
        $data = $request->validate($this->rules($request, $color->id));
        $data['sort_order'] ??= 0;

        $color->update([
            'name' => $data['name'],
            'color_family_id' => $data['color_family_id'],
            'brand_id' => $data['brand_id'],
            'texture_id' => $data['texture_id'],
            'color_hex' => $data['color_hex'] ?? null,
            'sort_order' => $data['sort_order'],
            'description' => $data['description'] ?? null,
        ]);

        $this->syncImages($request, $color);

        return redirect()->route('super-admin.catalog.colors.show', $color)
            ->with('success', __('flash.catalog.color.updated', ['name' => $color->name]));
    }

    public function destroy(Color $color): RedirectResponse
    {
        $color->delete();

        return redirect()->route('super-admin.catalog.colors')
            ->with('success', __('flash.catalog.color.deleted'));
    }

    private function syncImages(Request $request, Color $color): void
    {
        foreach (['single', 'cluster'] as $slot) {
            if ($request->hasFile("{$slot}_image")) {
                $this->images->set($color, $slot, $request->file("{$slot}_image"));
            } elseif ($request->boolean("{$slot}_image_clear")) {
                $this->images->clear($color, $slot);
            }
        }
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
            'brand_id' => ['required', 'uuid', 'exists:brands,id'],
            'texture_id' => ['required', 'uuid', 'exists:textures,id'],
            'color_hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:500'],
            'single_image' => ['nullable', 'file', 'image:allow_svg', 'max:10240'],
            'single_image_clear' => ['nullable', 'boolean'],
            'cluster_image' => ['nullable', 'file', 'image:allow_svg', 'max:10240'],
            'cluster_image_clear' => ['nullable', 'boolean'],
        ];
    }
}
