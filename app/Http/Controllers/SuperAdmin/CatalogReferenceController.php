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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class CatalogReferenceController extends Controller
{
    // Maps URL segment → [Model class, allowed fields for create/update]
    private const TABLES = [
        'sizes' => [Size::class,        ['name', 'size_category', 'sort_order', 'description']],
        'shapes' => [Shape::class,        ['name', 'sort_order', 'description']],
        'textures' => [Texture::class,      ['name', 'texture_family_id', 'sort_order', 'description']],
        'color-families' => [ColorFamily::class,  ['name', 'fallback_color_hex', 'sort_order', 'description']],
        'themes' => [Theme::class,        ['name', 'sort_order', 'description']],
        'materials' => [Material::class,     ['name', 'sort_order', 'description']],
    ];

    public function index(): Response
    {
        return Inertia::render('SuperAdmin/Catalog/Reference', [
            'sizes' => Size::orderBy('sort_order')->orderBy('name')->get(),
            'shapes' => $this->translated(Shape::withTranslations()->orderBy('sort_order')->orderBy('name')->get()),
            'textures' => $this->translated(Texture::with('textureFamily:id,name')->withTranslations()->orderBy('sort_order')->orderBy('name')->get()),
            'colorFamilies' => $this->translated(ColorFamily::withTranslations()->orderBy('sort_order')->orderBy('name')->get()),
            'themes' => $this->translated(Theme::withTranslations()->orderBy('sort_order')->orderBy('name')->get()),
            'materials' => $this->translated(Material::withTranslations()->orderBy('sort_order')->orderBy('name')->get()),
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

    public function store(Request $request, string $table): RedirectResponse
    {
        [$modelClass, $fields] = $this->resolveTable($table);

        $data = $request->validate($this->rules($table, $fields));
        $data['sort_order'] ??= 0;

        $modelClass::create($data);

        return redirect()->route('super-admin.catalog.reference')
            ->with('success', __('flash.catalog.reference.added'));
    }

    public function update(Request $request, string $table, string $item): RedirectResponse
    {
        [$modelClass, $fields] = $this->resolveTable($table);

        /** @var Model $record */
        $record = $modelClass::findOrFail($item);
        $data = $request->validate($this->rules($table, $fields));
        $data['sort_order'] ??= 0;
        $record->update($data);

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

    private function rules(string $table, array $fields): array
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

        return $rules;
    }
}
