<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Distributor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DistributorController extends Controller
{
    public function index(): Response
    {
        $distributors = Distributor::withCount('skuUrls')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Distributor $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'slug' => $d->slug,
                'platform_type' => $d->platform_type,
                'base_url' => $d->base_url,
                'is_active' => $d->is_active,
                'sort_order' => $d->sort_order,
                'last_synced_at' => $d->last_synced_at,
                'sku_urls_count' => $d->sku_urls_count,
            ]);

        return Inertia::render('SuperAdmin/Distributors/Index', [
            'distributors' => $distributors,
        ]);
    }

    public function show(Distributor $distributor): Response
    {
        $distributor->loadCount(['skuUrls', 'catalogGaps']);
        $distributor->load(['catalogGaps' => fn ($q) => $q->latest()->limit(50)]);

        return Inertia::render('SuperAdmin/Distributors/Show', [
            'distributor' => $distributor,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('SuperAdmin/Distributors/Form', [
            'distributor' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $data['sort_order'] ??= 0;

        /** @var Distributor $distributor */
        $distributor = Distributor::create($this->attributes($data));

        return redirect()->route('admin.distributors.index')
            ->with('success', __('flash.distributors.created', ['name' => $distributor->name]));
    }

    public function edit(Distributor $distributor): Response
    {
        return Inertia::render('SuperAdmin/Distributors/Form', [
            'distributor' => $distributor,
        ]);
    }

    public function update(Request $request, Distributor $distributor): RedirectResponse
    {
        $data = $request->validate($this->rules($distributor->id));
        $data['sort_order'] ??= 0;

        $distributor->update($this->attributes($data));

        return redirect()->route('admin.distributors.index')
            ->with('success', __('flash.distributors.updated', ['name' => $distributor->name]));
    }

    public function destroy(Distributor $distributor): RedirectResponse
    {
        $name = $distributor->name;
        $distributor->delete();

        return redirect()->route('admin.distributors.index')
            ->with('success', __('flash.distributors.deleted', ['name' => $name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'platform_type' => $data['platform_type'],
            'base_url' => $data['base_url'],
            'sitemap_url' => $data['sitemap_url'] ?? null,
            'config' => $data['config'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(?string $ignoreId = null): array
    {
        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('distributors', 'name')->whereNull('deleted_at')->ignore($ignoreId),
            ],
            'slug' => [
                'required', 'string', 'max:100',
                Rule::unique('distributors', 'slug')->whereNull('deleted_at')->ignore($ignoreId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'platform_type' => ['required', 'string', Rule::in(['shopify', 'bigcommerce'])],
            'base_url' => ['required', 'string', 'max:191', 'url'],
            'sitemap_url' => ['nullable', 'string', 'max:191', 'url'],
            'config' => ['nullable', 'json'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
