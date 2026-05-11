<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ColorFamily;
use App\Models\Material;
use App\Models\Shape;
use App\Models\Size;
use App\Models\Texture;
use App\Models\Theme;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogReferenceController extends Controller
{
    // Maps URL segment → [Model class, allowed fields for create/update]
    private const TABLES = [
        'sizes' => [Size::class,        ['name', 'size_category', 'sort_order', 'description']],
        'shapes' => [Shape::class,        ['name', 'sort_order', 'description']],
        'textures' => [Texture::class,      ['name', 'texture_family', 'sort_order', 'description']],
        'color-families' => [ColorFamily::class,  ['name', 'color_hex', 'sort_order', 'description']],
        'themes' => [Theme::class,        ['name', 'sort_order', 'description']],
        'materials' => [Material::class,     ['name', 'sort_order', 'description']],
    ];

    public function index(): Response
    {
        return Inertia::render('SuperAdmin/Catalog/Reference', [
            'sizes' => Size::orderBy('sort_order')->orderBy('name')->get(),
            'shapes' => Shape::orderBy('sort_order')->orderBy('name')->get(),
            'textures' => Texture::orderBy('sort_order')->orderBy('name')->get(),
            'colorFamilies' => ColorFamily::orderBy('sort_order')->orderBy('name')->get(),
            'themes' => Theme::orderBy('sort_order')->orderBy('name')->get(),
            'materials' => Material::orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, string $table): RedirectResponse
    {
        [$modelClass, $fields] = $this->resolveTable($table);

        $data = $request->validate($this->rules($table, $fields));

        $modelClass::create($data);

        return redirect()->route('super-admin.catalog.reference')
            ->with('success', 'Item added.');
    }

    public function update(Request $request, string $table, string $item): RedirectResponse
    {
        [$modelClass, $fields] = $this->resolveTable($table);

        /** @var Model $record */
        $record = $modelClass::findOrFail($item);
        $data = $request->validate($this->rules($table, $fields));
        $record->update($data);

        return redirect()->route('super-admin.catalog.reference')
            ->with('success', 'Item updated.');
    }

    public function destroy(string $table, string $item): RedirectResponse
    {
        [$modelClass] = $this->resolveTable($table);

        $modelClass::findOrFail($item)->delete();

        return redirect()->route('super-admin.catalog.reference')
            ->with('success', 'Item deleted.');
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

        if (in_array('texture_family', $fields)) {
            $rules['texture_family'] = ['required', 'string', 'max:50'];
        }

        if (in_array('color_hex', $fields)) {
            $rules['color_hex'] = ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'];
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
