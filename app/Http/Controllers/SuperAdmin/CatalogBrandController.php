<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogBrandController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('SuperAdmin/Catalog/Brands', [
            'brands' => Brand::withCount('skus')->orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'abbreviation' => ['required', 'string', 'max:10'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $brand->update($data);

        return redirect()->route('super-admin.catalog.brands')
            ->with('success', 'Brand "'.$brand->name.'" updated.');
    }
}
