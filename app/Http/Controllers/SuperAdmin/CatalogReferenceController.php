<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ColorFamily;
use App\Models\Material;
use App\Models\Shape;
use App\Models\Size;
use App\Models\Texture;
use App\Models\TextureFamily;
use App\Models\Theme;
use App\Services\Catalog\CatalogImageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class CatalogReferenceController extends Controller
{
    // Maps URL segment → [Model class, allowed fields for create/update, image slots].
    // Image slots correspond to keys configured in CatalogImageService.
    private const TABLES = [
        'sizes' => [Size::class,         ['name', 'size_category', 'sort_order', 'description'], ['single', 'cluster']],
        'shapes' => [Shape::class,        ['name', 'sort_order', 'description'],                  ['image']],
        'textures' => [Texture::class,      ['name', 'texture_family_id', 'sort_order', 'description'], ['image']],
        'color-families' => [ColorFamily::class,  ['name', 'fallback_color_hex', 'sort_order', 'description'], ['single', 'cluster']],
        'themes' => [Theme::class,        ['name', 'sort_order', 'description'],                  []],
        'materials' => [Material::class,     ['name', 'sort_order', 'description'],                  ['image']],
    ];

    public function __construct(private readonly CatalogImageService $imageService) {}

    public function index(): Response
    {
        return Inertia::render('SuperAdmin/Catalog/Reference', [
            'sizes' => $this->withImages($this->translated(Size::orderBy('sort_order')->orderBy('name')->get()), ['single', 'cluster']),
            'shapes' => $this->withImages($this->translated(Shape::withTranslations()->orderBy('sort_order')->orderBy('name')->get()), ['image']),
            'textures' => $this->withImages($this->translated(Texture::with('textureFamily:id,name')->withTranslations()->orderBy('sort_order')->orderBy('name')->get()), ['image']),
            'colorFamilies' => $this->withImages($this->translated(ColorFamily::withTranslations()->orderBy('sort_order')->orderBy('name')->get()), ['single', 'cluster']),
            'themes' => $this->translated(Theme::withTranslations()->orderBy('sort_order')->orderBy('name')->get()),
            'materials' => $this->withImages($this->translated(Material::withTranslations()->orderBy('sort_order')->orderBy('name')->get()), ['image']),
            'textureFamilies' => TextureFamily::orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
        ]);
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
     * Attach an `images` object (slot => url|null) to each item so the Vue
     * forms have everything they need for previews + the gallery component.
     */
    private function withImages(Collection $items, array $slots): Collection
    {
        return $items->map(function (Model $item) use ($slots) {
            $urls = $this->imageService->urls($item);
            $item->images = collect($slots)
                ->mapWithKeys(fn (string $slot) => [$slot => $urls[$slot] ?? null])
                ->all();

            return $item;
        });
    }

    public function store(Request $request, string $table): RedirectResponse
    {
        [$modelClass, $fields, $slots] = $this->resolveTable($table);

        $data = $request->validate($this->rules($table, $fields, $slots));
        $data['sort_order'] ??= 0;

        /** @var Model $record */
        $record = $modelClass::create($this->pickColumns($data, $fields));

        $this->syncImages($request, $record, $slots);

        return redirect()->route('super-admin.catalog.reference')
            ->with('success', __('flash.catalog.reference.added'));
    }

    public function update(Request $request, string $table, string $item): RedirectResponse
    {
        [$modelClass, $fields, $slots] = $this->resolveTable($table);

        /** @var Model $record */
        $record = $modelClass::findOrFail($item);
        $data = $request->validate($this->rules($table, $fields, $slots));
        $data['sort_order'] ??= 0;
        $record->update($this->pickColumns($data, $fields));

        $this->syncImages($request, $record, $slots);

        return redirect()->route('super-admin.catalog.reference')
            ->with('success', __('flash.catalog.reference.updated'));
    }

    public function destroy(string $table, string $item): RedirectResponse
    {
        [$modelClass] = $this->resolveTable($table);

        $modelClass::findOrFail($item)->delete();

        return redirect()->route('super-admin.catalog.reference')
            ->with('success', __('flash.catalog.reference.deleted'));
    }

    private function resolveTable(string $table): array
    {
        abort_unless(isset(self::TABLES[$table]), 404);

        return self::TABLES[$table];
    }

    private function pickColumns(array $data, array $fields): array
    {
        return array_intersect_key($data, array_flip(array_merge($fields, ['sort_order'])));
    }

    private function syncImages(Request $request, Model $record, array $slots): void
    {
        foreach ($slots as $slot) {
            $fileField = $this->fileFieldForSlot($slot);
            $clearField = $fileField.'_clear';

            if ($request->hasFile($fileField)) {
                $this->imageService->set($record, $slot, $request->file($fileField));
            } elseif ($request->boolean($clearField)) {
                $this->imageService->clear($record, $slot);
            }
        }
    }

    /**
     * Maps a service slot ('single', 'cluster', 'image') to the request field
     * name the Vue form posts. Single-slot entities post under `image` (no
     * redundant prefix); dual-slot entities post under `<slot>_image`.
     */
    private function fileFieldForSlot(string $slot): string
    {
        return $slot === 'image' ? 'image' : $slot.'_image';
    }

    private function rules(string $table, array $fields, array $slots): array
    {
        $rules = [];

        if (in_array('name', $fields)) {
            $rules['name'] = ['required', 'string', 'max:100'];
        }

        if (in_array('size_category', $fields)) {
            $rules['size_category'] = ['required', 'in:small,medium,large,giant,small_modeling,large_modeling'];
        }

        if (in_array('texture_family_id', $fields)) {
            $rules['texture_family_id'] = ['required', 'uuid', 'exists:texture_families,id'];
        }

        if (in_array('fallback_color_hex', $fields)) {
            $rules['fallback_color_hex'] = ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        }

        if (in_array('sort_order', $fields)) {
            $rules['sort_order'] = ['nullable', 'integer', 'min:0'];
        }

        if (in_array('description', $fields)) {
            $rules['description'] = ['nullable', 'string', 'max:500'];
        }

        foreach ($slots as $slot) {
            $fileField = $this->fileFieldForSlot($slot);
            $rules[$fileField] = ['nullable', 'file', 'image', 'max:10240'];
            $rules[$fileField.'_clear'] = ['nullable', 'boolean'];
        }

        return $rules;
    }
}
