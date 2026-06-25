<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Jobs\RunDistributorSyncJob;
use App\Models\Distributor;
use App\Models\DistributorProduct;
use App\Services\Distributors\DistributorProbe;
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
        return Inertia::render('SuperAdmin/Distributors/Show', $this->showProps($distributor));
    }

    /**
     * Fetch one sample product URL and show how this distributor's page maps to
     * our catalog (no DB writes) — the verify-before-crawl step when onboarding a
     * brand. Re-renders the detail page with a `probe` result.
     */
    public function probe(Request $request, Distributor $distributor, DistributorProbe $probe): Response
    {
        $data = $request->validate([
            'probe_url' => ['required', 'string', 'url', 'max:2048'],
        ]);

        return Inertia::render('SuperAdmin/Distributors/Show', $this->showProps($distributor) + [
            'probe' => $probe->probe($distributor, $data['probe_url']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function showProps(Distributor $distributor): array
    {
        $distributor->loadCount(['skuUrls', 'catalogGaps']);
        $distributor->load(['catalogGaps' => fn ($q) => $q->latest()->limit(50)]);

        return [
            'distributor' => $distributor,
            'stagedTotal' => DistributorProduct::where('distributor_id', $distributor->id)->count(),
            'stagedWithUpc' => DistributorProduct::where('distributor_id', $distributor->id)
                ->whereNotNull('upc')->count(),
        ];
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

    /**
     * Dispatch a queued staging ingest/crawl for this distributor. Runs off the
     * request path via the database queue; products appear in staging as the job
     * works through them.
     */
    public function sync(Request $request, Distributor $distributor): RedirectResponse
    {
        $data = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        RunDistributorSyncJob::dispatch($distributor->id, $data['limit'] ?? 100);

        return back()->with('success', __('flash.distributors.sync_started'));
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
        // Start from the advanced (raw) JSON override blob, then layer the
        // structured fields on top so they always win.
        $advancedConfig = [];
        if (! empty($data['config_advanced'])) {
            $advancedConfig = json_decode($data['config_advanced'], true) ?? [];
        }

        $structuredConfig = array_filter([
            'sku_strip_prefixes' => $this->splitCsv($data['config_sku_strip_prefixes'] ?? null),
            'sku_strip_suffixes' => $this->splitCsv($data['config_sku_strip_suffixes'] ?? null),
            'request_delay_ms' => isset($data['config_request_delay_ms']) ? (int) $data['config_request_delay_ms'] : null,
            'request_jitter_ms' => isset($data['config_request_jitter_ms']) ? (int) $data['config_request_jitter_ms'] : null,
            'max_retries' => isset($data['config_max_retries']) ? (int) $data['config_max_retries'] : null,
            'max_pages' => isset($data['config_max_pages']) ? (int) $data['config_max_pages'] : null,
            'has_json_api' => ($data['platform_type'] ?? null) === 'shopify' ? (bool) ($data['config_has_json_api'] ?? true) : null,
            'collection_handle' => ($data['platform_type'] ?? null) === 'shopify' ? ($data['config_collection_handle'] ?? null) : null,
            // Drop nulls (field left blank → fall back to the adapter's own
            // default) and empty strings, so we only persist values the admin
            // actually set rather than baking the form defaults into every row.
        ], fn ($v) => $v !== null && $v !== '');

        $config = array_merge($advancedConfig, $structuredConfig);

        return [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'platform_type' => $data['platform_type'],
            'base_url' => $data['base_url'],
            'sitemap_url' => $data['sitemap_url'] ?? null,
            'config' => empty($config) ? null : $config,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'],
        ];
    }

    /** @return array<int, string>|null */
    private function splitCsv(?string $value): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
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
            // Structured config fields — all optional
            'config_sku_strip_prefixes' => ['nullable', 'string', 'max:500'],
            'config_sku_strip_suffixes' => ['nullable', 'string', 'max:500'],
            'config_request_delay_ms' => ['nullable', 'integer', 'min:0', 'max:30000'],
            'config_request_jitter_ms' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'config_max_retries' => ['nullable', 'integer', 'min:0', 'max:20'],
            'config_max_pages' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'config_has_json_api' => ['nullable', 'boolean'],
            'config_collection_handle' => ['nullable', 'string', 'max:191'],
            'config_advanced' => ['nullable', 'json'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
