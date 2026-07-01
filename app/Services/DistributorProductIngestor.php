<?php

namespace App\Services;

use App\Models\Distributor;
use App\Models\DistributorProduct;
use App\Models\Sku;
use App\Services\DistributorPlatforms\BigCommerceProductPageParser;
use App\Services\DistributorPlatforms\Concerns\InspectsHttpResponses;
use App\Services\DistributorPlatforms\MagentoProductPageParser;
use App\Services\DistributorPlatforms\PlatformFactory;
use App\Services\Distributors\DistributorProductClassifier;
use App\Services\Distributors\JsonLdAvailabilityParser;
use App\Services\Distributors\ProductAttributeTableExtractor;
use App\Services\Distributors\ShopifyTagAttributeExtractor;
use App\Services\Distributors\TitleAttributeExtractor;
use App\Support\Gtin;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Writes raw distributor listings into the distributor_products staging table.
 *
 * Two paths, one service:
 *  - Shopify: bulk JSON API via the existing ShopifyAdapter, fast (~15k in ~30s).
 *  - BigCommerce single-page fetch: HTTP GET → BigCommerceProductPageParser →
 *    upsert. Used by the batch/resume crawl command, NOT for bulk sitemap loops
 *    (those are orchestrated by the command itself).
 *
 * The staging table is invisible to users — it feeds the cluster engine, which
 * groups by UPC and creates proposals for genuinely new products.
 */
class DistributorProductIngestor
{
    use InspectsHttpResponses;

    private const CHUNK_SIZE = 500;

    private const TIMEOUT = 30;

    private const USER_AGENT = 'Balloonventory/1.0 (+https://balloonventory.com)';

    public function __construct(
        private DistributorSkuNormalizer $normalizer,
        private PlatformFactory $platformFactory,
        private ProductAttributeTableExtractor $attributeExtractor,
        private DistributorProductClassifier $classifier,
        private ShopifyTagAttributeExtractor $tagExtractor,
        private JsonLdAvailabilityParser $availabilityParser,
        private TitleAttributeExtractor $titleExtractor,
    ) {}

    /**
     * Shopify: fetch from the bulk JSON API and upsert everything into staging.
     *
     * Returns stats: fetched, staged, fetch_report, complete.
     *
     * @return array{fetched: int, staged: int, report: array, complete: bool}
     */
    public function ingestShopify(Distributor $distributor, bool $execute, ?int $limit = null): array
    {
        $adapter = $this->platformFactory->make($distributor);
        $products = $adapter->fetchProducts($distributor);
        $report = $adapter->lastFetchReport();

        if ($limit !== null && $limit < count($products)) {
            $products = array_slice($products, 0, $limit);
        }

        $staged = 0;
        $complete = ! $report->stoppedEarly;

        if ($execute && $products !== []) {
            $config = $distributor->config ?? [];
            $distributorId = $distributor->id;

            foreach (array_chunk($products, self::CHUNK_SIZE) as $chunk) {
                $staged += $this->upsertShopifyChunk($distributorId, $chunk, $config);
            }
        } else {
            $staged = count($products);
        }

        // Advance last_synced_at on any complete pass — even when zero products
        // are returned (empty store or all filtered out). A complete pass is
        // a successful sync regardless of count.
        if ($execute && $complete) {
            $distributor->update(['last_synced_at' => now()]);
        }

        return [
            'fetched' => count($products),
            'staged' => $staged,
            'report' => $report->toArray(),
            'complete' => $complete,
        ];
    }

    /**
     * Shopify page-enrichment: the targeted second pass over a Shopify catalog.
     *
     * The bulk {@see ingestShopify} pass already stages a barcode/title/price for
     * every product (enough to reconcile a shared-UPC product with our catalog at
     * cluster time). This pass fetches the product *page* — but only for the slice
     * that actually needs it: a net-new product (UPC not already ours or another
     * distributor's) that looks like solid latex. For each, it reads the page's
     * "Additional Product Details" spec list, injects the brand (from the JSON
     * vendor) and a synthesised shape (the page omits it), classifies it, and
     * upserts a fully-attributed staged product that clusters/proposes like a
     * crawled BigCommerce one.
     *
     * @return array{fetched: int, candidates: int, enriched: int, skipped_existing: int, skipped_non_latex: int, skipped_fresh: int, failed: int, report: array, hit_limit: bool}
     */
    public function enrichShopify(Distributor $distributor, bool $execute, ?int $limit = null, bool $force = false): array
    {
        $adapter = $this->platformFactory->make($distributor);
        $products = $adapter->fetchProducts($distributor);
        $report = $adapter->lastFetchReport();
        $config = $distributor->config ?? [];

        $knownUpcs = $this->knownCanonicalUpcs($distributor->id);
        $staged = DistributorProduct::where('distributor_id', $distributor->id)
            ->get(['external_id', 'raw_data', 'fetched_at'])
            ->keyBy('external_id');

        $delayMs = $this->configInt($config, 'request_delay_ms', 500);
        $jitterMs = $this->configInt($config, 'request_jitter_ms', 0);

        $candidates = 0;
        $enriched = 0;
        $skippedExisting = 0;
        $skippedNonLatex = 0;
        $skippedFresh = 0;
        $failed = 0;
        $hitLimit = false;

        foreach ($products as $product) {
            // Net-new gate: a product whose barcode is already in our catalog or
            // staged by another distributor reconciles via the bulk pass — no page
            // fetch needed. (Products without a barcode still need the page, since
            // a barcode-less listing can't reconcile.)
            $barcode = $product['barcode'] ?? null;
            $canonical = ! empty($barcode) ? Gtin::canonicalize((string) $barcode) : null;

            if ($canonical !== null && $knownUpcs->has($canonical)) {
                $skippedExisting++;

                continue;
            }

            // Cheap pre-filter: only latex looks worth the page fetch. The real
            // type classification still happens from the page, so a misfilter just
            // parks as a non-proposing type rather than creating a bad SKU.
            if (! $this->looksSolidLatex($product, $config)) {
                $skippedNonLatex++;

                continue;
            }

            $externalId = $product['identifier'];

            if (! $force && $this->alreadyEnriched($staged->get($externalId), $product['updated_at'] ?? null)) {
                $skippedFresh++;

                continue;
            }

            if ($limit !== null && $enriched >= $limit) {
                $hitLimit = true;

                break;
            }

            $candidates++;

            if (! $execute) {
                $enriched++;

                continue;
            }

            if ($enriched > 0) {
                $this->throttle($delayMs, $jitterMs);
            }

            // Where a Shopify store keeps its attributes decides the enrich source:
            //  - namespaced tags (LA Balloons): read tags, fetch only the barcode;
            //  - product body_html table (Joker Party Supply): one per-product .json
            //    fetch yields both the table and the barcode — no heavy HTML page;
            //  - product page HTML accordion (BargainBalloons): parse the page.
            if (isset($config['extraction']['tag_attributes'])) {
                $parsed = $this->enrichShopifyFromTags($distributor, $product, $config);
            } elseif (($config['enrich_from_product_json'] ?? false) === true) {
                $parsed = $this->enrichShopifyFromProductJson($distributor, $product, $config);
            } else {
                $parsed = $this->enrichShopifyPage($distributor, $product, $config);
            }

            if ($parsed !== null) {
                $enriched++;
            } else {
                $failed++;
            }
        }

        return [
            'fetched' => count($products),
            'candidates' => $candidates,
            'enriched' => $enriched,
            'skipped_existing' => $skippedExisting,
            'skipped_non_latex' => $skippedNonLatex,
            'skipped_fresh' => $skippedFresh,
            'failed' => $failed,
            'report' => $report->toArray(),
            'hit_limit' => $hitLimit,
        ];
    }

    /**
     * Fetch + enrich a single Shopify product page: read its spec list, inject the
     * brand (JSON vendor) and a synthesised shape, classify, and upsert the
     * fully-attributed staged row. Returns the parsed fields, or null on a fetch /
     * parse failure.
     *
     * @param  array<string, mixed>  $product  one variant row from the ShopifyAdapter
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>|null
     */
    private function enrichShopifyPage(Distributor $distributor, array $product, array $config): ?array
    {
        $url = $product['url'];

        $response = Http::timeout(self::TIMEOUT)
            ->withUserAgent(self::USER_AGENT)
            ->get($url);

        if ($this->classifyResponse($response) !== null) {
            return null;
        }

        $extraction = $this->attributeExtractor->extract($response->body(), $config);

        if (! ($extraction['has_recipe'] ?? false)) {
            return null;
        }

        // Shopify's public feed exposes no stock field, but the rendered page
        // embeds a JSON-LD Offer.availability. Prefer that real signal; fall back
        // to the (typically null) feed value when the page omits availability.
        $pageStock = $this->availabilityParser->parse($response->body());
        if ($pageStock !== null) {
            $product['in_stock'] = $pageStock;
        }

        // The page omits the brand and the shape; supply them from the JSON vendor
        // and a synthesised shape so the matcher's brand-scoped + shape→size logic
        // runs exactly as it does for a crawled BigCommerce product.
        $extraction = $this->injectShopifyAttributes($extraction, $product, $config);

        $productType = $this->classifier->classify($extraction);

        $externalId = $product['identifier'];

        // Drift guard: a failed extraction must not clobber attributes we already
        // hold for this product (same protection as the BigCommerce path).
        if (! ($extraction['ok'] ?? false) && $this->hasStagedAttributes($distributor->id, $externalId)) {
            return $extraction;
        }

        $this->upsertEnrichedShopify($distributor->id, $externalId, $url, $product, $extraction, $productType, $config);

        return $extraction;
    }

    /**
     * Tag-driven enrichment (LA Balloons): the product's attributes are already in
     * the bulk products.json tags + product_type, so no page fetch is needed for
     * them — only the barcode, which Shopify withholds from the bulk feed, is
     * fetched (the light per-product `.json`). Mirrors {@see enrichShopifyPage}'s
     * inject → classify → upsert, but with the tag extractor as the source.
     *
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>|null
     */
    private function enrichShopifyFromTags(Distributor $distributor, array $product, array $config): ?array
    {
        $extraction = $this->tagExtractor->extract($product, $config);

        if (! ($extraction['has_recipe'] ?? false)) {
            return null;
        }

        // Brand (vendor) + synthesised shape, same as the HTML Shopify path.
        $extraction = $this->injectShopifyAttributes($extraction, $product, $config);

        $productType = $this->classifier->classify($extraction);

        // The barcode is the one field the bulk feed strips — fetch the light
        // per-product JSON for it so the product can cluster (clustering is
        // UPC-gated). A barcode-less product still stages (it just won't cluster).
        $barcode = $this->fetchShopifyProductBarcode($product['url'], $product['identifier']);
        if ($barcode !== null) {
            $product['barcode'] = $barcode;
        }

        // The tag path skips the HTML page, so the bulk/per-product JSON gives no
        // stock (Shopify exposes none). Stores that render a reliable JSON-LD
        // Offer.availability can opt in via `stock_from_page` to spend one extra
        // page fetch for a real in-stock signal.
        if (($config['stock_from_page'] ?? false) === true) {
            $pageStock = $this->fetchPageAvailability($product['url']);
            if ($pageStock !== null) {
                $product['in_stock'] = $pageStock;
            }
        }

        $externalId = $product['identifier'];

        // Drift guard: don't clobber good staged attributes with an empty extraction.
        if (! ($extraction['ok'] ?? false) && $this->hasStagedAttributes($distributor->id, $externalId)) {
            return $extraction;
        }

        $this->upsertEnrichedShopify($distributor->id, $externalId, $product['url'], $product, $extraction, $productType, $config);

        return $extraction;
    }

    /**
     * Product-JSON enrichment (Joker Party Supply): the store renders its full
     * structured attribute table inside the product's `body_html`, and the barcode
     * lives on the variant — both carried by the light per-product `.json`. So a
     * single JSON fetch (no heavy HTML page download) yields everything: read
     * body_html through the `attribute_rows` recipe, take the barcode from the same
     * response, inject brand (vendor) + a synthesised shape, classify, and upsert.
     * Mirrors {@see enrichShopifyPage} without the page fetch.
     *
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>|null
     */
    private function enrichShopifyFromProductJson(Distributor $distributor, array $product, array $config): ?array
    {
        $response = Http::timeout(self::TIMEOUT)
            ->withUserAgent(self::USER_AGENT)
            ->get($product['url'].'.json');

        if ($this->classifyResponse($response) !== null) {
            return null;
        }

        $bodyHtml = (string) ($response->json('product.body_html') ?? '');

        $extraction = $this->attributeExtractor->extract($bodyHtml, $config);

        if (! ($extraction['has_recipe'] ?? false)) {
            return null;
        }

        // The bulk collection feed strips the barcode; the per-product .json variant
        // carries it. Take it from the same fetch. (The body_html "UPC" row is a
        // fallback that resolveEnrichedUpc already handles if the variant lacks one.)
        $barcode = $this->variantBarcode($response->json('product.variants') ?? [], $product['identifier']);
        if ($barcode !== null) {
            $product['barcode'] = $barcode;
        }

        // Some products ("auto-info") have a narrative body_html with no spec table,
        // but the rendered page still carries the same table (from metafields). When
        // body_html yields no usable table, fall back to the page for those.
        if (! ($extraction['ok'] ?? false)) {
            $pageExtraction = $this->extractAttributesFromPage($product['url'], $config);

            if ($pageExtraction !== null && ($pageExtraction['ok'] ?? false)) {
                $extraction = $pageExtraction;
            }
        }

        // Brand (vendor) + synthesised shape, same as the HTML Shopify path.
        $extraction = $this->injectShopifyAttributes($extraction, $product, $config);

        $productType = $this->classifier->classify($extraction);

        $externalId = $product['identifier'];

        // Drift guard: don't clobber good staged attributes with an empty extraction.
        if (! ($extraction['ok'] ?? false) && $this->hasStagedAttributes($distributor->id, $externalId)) {
            return $extraction;
        }

        $this->upsertEnrichedShopify($distributor->id, $externalId, $product['url'], $product, $extraction, $productType, $config);

        return $extraction;
    }

    /**
     * Run the attribute extractor against a product's rendered page — the fallback
     * for {@see enrichShopifyFromProductJson} when a product's body_html carries no
     * spec table. Returns the extraction result, or null on a failed/blocked fetch.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>|null
     */
    private function extractAttributesFromPage(string $url, array $config): ?array
    {
        $response = Http::timeout(self::TIMEOUT)
            ->withUserAgent(self::USER_AGENT)
            ->get($url);

        if ($this->classifyResponse($response) !== null) {
            return null;
        }

        return $this->attributeExtractor->extract($response->body(), $config);
    }

    /**
     * Best-effort stock read from a product page's JSON-LD Offer.availability.
     * Returns null on a failed/blocked fetch or a page without availability, so a
     * page hiccup never blocks staging — the product just stays stock-unknown.
     */
    private function fetchPageAvailability(string $url): ?bool
    {
        $response = Http::timeout(self::TIMEOUT)
            ->withUserAgent(self::USER_AGENT)
            ->get($url);

        if ($this->classifyResponse($response) !== null) {
            return null;
        }

        return $this->availabilityParser->parse($response->body());
    }

    /**
     * Fetch a Shopify product's barcode from its per-product `.json` (the bulk
     * collection feed omits it). Matches the variant by SKU, falling back to the
     * first variant. Returns the bare value, or null on a failed fetch / no barcode.
     */
    private function fetchShopifyProductBarcode(string $productUrl, string $sku): ?string
    {
        $response = Http::timeout(self::TIMEOUT)
            ->withUserAgent(self::USER_AGENT)
            ->get($productUrl.'.json');

        if ($this->classifyResponse($response) !== null) {
            return null;
        }

        return $this->variantBarcode($response->json('product.variants') ?? [], $sku);
    }

    /**
     * The barcode of the variant matching $sku (falling back to the first variant),
     * from an already-fetched per-product `.json` variants array. Returns the bare
     * value, or null when the variant has no barcode.
     *
     * @param  array<int, array<string, mixed>>  $variants
     */
    private function variantBarcode(array $variants, string $sku): ?string
    {
        $match = collect($variants)->first(fn ($v) => ($v['sku'] ?? null) === $sku)
            ?? ($variants[0] ?? null);

        $barcode = $match['barcode'] ?? null;

        return ($barcode !== null && $barcode !== '') ? (string) $barcode : null;
    }

    /**
     * Inject the brand (from the Shopify vendor) and a synthesised shape into the
     * extracted attribute map, under the labels the matcher reads. Existing page
     * values are never overwritten.
     *
     * @param  array<string, mixed>  $extraction
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function injectShopifyAttributes(array $extraction, array $product, array $config): array
    {
        $labelMap = $config['extraction']['label_map'] ?? [];
        $brandLabel = $labelMap['brand'] ?? 'Brand';
        $shapeLabel = $labelMap['shape'] ?? 'Balloon Type / Shape';

        $attributes = $extraction['attributes'] ?? [];

        $vendor = $product['vendor'] ?? null;
        if ($vendor !== null && $vendor !== '' && ! $this->hasLabel($attributes, $brandLabel)) {
            $attributes[$brandLabel] = [(string) $vendor];
        }

        if (! $this->hasLabel($attributes, $shapeLabel)) {
            $attributes[$shapeLabel] = [$this->synthesizeShape($product)];
        }

        $extraction['attributes'] = $attributes;
        $extraction['row_count'] = array_sum(array_map('count', $attributes));

        return $extraction;
    }

    /**
     * Synthesise the balloon shape a Shopify store doesn't state: a SKU shape
     * prefix (Decomex "R12"/"H07"/"L11") wins, then a title keyword, else Round —
     * the right default for latex.
     *
     * @param  array<string, mixed>  $product
     */
    private function synthesizeShape(array $product): string
    {
        foreach ([$product['identifier'] ?? '', $product['raw_sku'] ?? ''] as $sku) {
            if (preg_match('/^([rhl])\d/i', (string) $sku, $m)) {
                return ['r' => 'Round', 'h' => 'Heart', 'l' => 'Link'][strtolower($m[1])];
            }
        }

        $title = strtolower((string) ($product['name'] ?? ''));

        if (str_contains($title, 'link')) { // covers "link" and "linking"
            return 'Link';
        }

        if (str_contains($title, 'heart')) {
            return 'Heart';
        }

        return 'Round';
    }

    /**
     * Cheap solid-latex pre-filter from the bulk JSON: the title/tags must mention
     * latex and must not look like foil / mylar / printed.
     *
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $config  the distributor's config
     */
    private function looksSolidLatex(array $product, array $config = []): bool
    {
        // When the store classifies its own products (LA Balloons' product_type),
        // trust that signal: only a latex product type qualifies. This keeps out
        // accessories/decor (conduit, glitter) that merely mention "latex" in a
        // tag. Stores that leave product_type empty (BargainBalloons) fall back to
        // the title/tags heuristic below.
        //
        // A store may name a latex family with a word other than "latex" — e.g. All
        // American Balloons sells its modeling range as "Twisting Balloons" — so the
        // set of latex-indicating keywords is configurable (`latex_type_keywords`),
        // defaulting to ["latex"]. Foil/mylar are always excluded.
        $type = strtolower((string) ($product['product_type'] ?? ''));
        $latexKeywords = (array) ($config['latex_type_keywords'] ?? ['latex']);

        if ($type !== '') {
            if (str_contains($type, 'foil') || str_contains($type, 'mylar')) {
                return false;
            }

            foreach ($latexKeywords as $keyword) {
                if ($keyword !== '' && str_contains($type, strtolower((string) $keyword))) {
                    return true;
                }
            }

            return false;
        }

        $haystack = strtolower(
            ($product['name'] ?? '').' '
            .implode(' ', (array) ($product['tags'] ?? []))
        );

        if (! str_contains($haystack, 'latex')) {
            return false;
        }

        foreach (['foil', 'mylar', 'printed'] as $exclude) {
            if (str_contains($haystack, $exclude)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Has this product already been page-enriched (holds an attribute table) and
     * not changed since? Enrichment skips it then. A barcode-only row written by
     * the bulk pass has no attributes, so it is never considered fresh here.
     */
    private function alreadyEnriched(?DistributorProduct $staged, ?string $updatedAt): bool
    {
        if ($staged === null || $staged->fetched_at === null) {
            return false;
        }

        if (empty($staged->raw_data['attributes'] ?? [])) {
            return false; // bulk-staged but never enriched
        }

        if ($updatedAt !== null && ($changedAt = $this->parseTimestamp($updatedAt)) !== null) {
            return $staged->fetched_at->greaterThanOrEqualTo($changedAt);
        }

        return true;
    }

    private function parseTimestamp(string $value): ?Carbon
    {
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Does the attribute map already carry this label (case-insensitive)?
     *
     * @param  array<string, array<int, string>>  $attributes
     */
    private function hasLabel(array $attributes, string $label): bool
    {
        foreach (array_keys($attributes) as $key) {
            if (strcasecmp((string) $key, $label) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Canonical GTIN-14s already known to us: every barcoded catalog SKU plus
     * every barcoded product staged by *another* distributor. A Shopify product
     * matching one of these reconciles via the bulk pass and never needs a page
     * fetch.
     *
     * @return Collection<string, true>
     */
    private function knownCanonicalUpcs(string $excludeDistributorId): Collection
    {
        $known = collect();

        Sku::where(fn ($query) => $query->whereNotNull('upc')->orWhereNotNull('ean'))
            ->get(['upc', 'ean'])
            ->each(function (Sku $sku) use ($known) {
                foreach ([$sku->upc, $sku->ean] as $code) {
                    if ($code && ($canonical = Gtin::canonicalize((string) $code)) !== null) {
                        $known->put($canonical, true);
                    }
                }
            });

        DistributorProduct::where('distributor_id', '!=', $excludeDistributorId)
            ->whereNotNull('upc')
            ->pluck('upc')
            ->each(function (string $upc) use ($known) {
                if (($canonical = Gtin::canonicalize($upc)) !== null) {
                    $known->put($canonical, true);
                }
            });

        return $known;
    }

    /**
     * Upsert a Shopify-enriched product: the bulk JSON fields (sku/barcode/price/
     * stock) plus the page's attribute table and classified product_type.
     *
     * @param  array<string, mixed>  $product  the Shopify variant row
     * @param  array<string, mixed>  $extraction
     * @param  array<string, mixed>  $config
     */
    /**
     * The UPC for an enriched Shopify product: the bulk products.json barcode when
     * the store exposes it, else the "UPC" row the page spec list carries (Shopify's
     * collection products.json omits the barcode, so the page is the only source).
     * Returns the bare digits, or null when neither source has one.
     *
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $extraction
     */
    private function resolveEnrichedUpc(array $product, array $extraction): ?string
    {
        $candidates = [];

        if (! empty($product['barcode'])) {
            $candidates[] = (string) $product['barcode'];
        }

        foreach ($extraction['attributes'] ?? [] as $label => $values) {
            if (strcasecmp((string) $label, 'UPC') === 0) {
                $candidates[] = (string) ($values[0] ?? '');
            }
        }

        foreach ($candidates as $candidate) {
            $digits = Gtin::digitsOnly($candidate);

            if ($digits !== '') {
                return $digits;
            }
        }

        return null;
    }

    private function upsertEnrichedShopify(
        string $distributorId,
        string $externalId,
        string $url,
        array $product,
        array $extraction,
        string $productType,
        array $config,
    ): void {
        $existing = DistributorProduct::where('distributor_id', $distributorId)
            ->where('external_id', $externalId)
            ->value('id');

        // The barcode source differs by store. Shopify's *collection* products.json
        // (the bulk pass) omits the barcode entirely; the UPC instead appears on the
        // product page itself (BargainBalloons lists it in the spec accordion). So
        // prefer the bulk barcode when present, else fall back to the UPC the page
        // extractor read — without it the product can't cluster (clustering is
        // UPC-gated).
        $upc = $this->resolveEnrichedUpc($product, $extraction);

        $rawData = [
            'attributes' => $extraction['attributes'] ?? [],
            'extraction' => [
                'ok' => $extraction['ok'] ?? false,
                'row_count' => $extraction['row_count'] ?? 0,
                'missing_required' => $extraction['missing_required'] ?? [],
            ],
        ];

        DistributorProduct::upsert(
            [[
                'id' => $existing ?? (string) Str::uuid7(),
                'distributor_id' => $distributorId,
                'external_id' => $externalId,
                'raw_sku' => $product['identifier'],
                'normalized_sku' => $this->normalizer->normalize($product['identifier'], $config),
                'upc' => $upc,
                'title' => $product['name'] ?? '',
                'product_type' => $productType,
                'url' => $url,
                'price' => $product['price'] ?? null,
                'currency' => $product['currency'] ?? 'USD',
                'stock' => null,
                'in_stock' => $product['in_stock'] ?? null,
                'raw_data' => json_encode($rawData),
                'fetched_at' => now(),
            ]],
            ['distributor_id', 'external_id'],
            ['raw_sku', 'normalized_sku', 'upc', 'title', 'product_type', 'url', 'price', 'currency', 'stock', 'in_stock', 'raw_data', 'fetched_at'],
        );
    }

    /**
     * Fetch a single BigCommerce product page, parse it, and upsert to staging.
     *
     * Returns the parsed fields on success, or null when the page couldn't be
     * fetched or parsed (caller handles retry / failure accounting).
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>|null
     */
    public function crawlBigCommercePage(
        Distributor $distributor,
        string $url,
        string $externalId,
        array $config,
        bool $execute,
    ): ?array {
        $parser = app(BigCommerceProductPageParser::class);

        $response = Http::timeout(self::TIMEOUT)
            ->withUserAgent(self::USER_AGENT)
            ->get($url);

        $reason = $this->classifyResponse($response);

        if ($reason !== null) {
            return null;
        }

        $parsed = $parser->parse($response->body(), $config);

        if ($parsed === null || ($parsed['raw_sku'] ?? null) === null) {
            return null;
        }

        // Read the product's structured attributes and classify the product type,
        // so non-latex types can be parked with their attributes rather than
        // guessed later. Most BigCommerce stores render an attribute table
        // (Larocks); stores that don't (havinaparty) carry the attributes only in
        // the title, read via the title_attributes recipe.
        $extraction = isset($config['extraction']['title_attributes'])
            ? $this->titleExtractor->extract($parsed, $config)
            : $this->attributeExtractor->extract($response->body(), $config);
        $parsed['product_type'] = $this->classifier->classify($extraction);
        $parsed['attributes'] = $extraction['attributes'];
        $parsed['extraction'] = [
            'ok' => $extraction['ok'],
            'row_count' => $extraction['row_count'],
            'missing_required' => $extraction['missing_required'],
        ];

        // Drift guard: if the recipe didn't match this page (e.g. the site changed
        // its template) but we already hold good attributes for this product, keep
        // the good data rather than overwriting it with an empty extraction.
        if ($execute && ! ($parsed['extraction']['ok'] ?? false) && $this->hasStagedAttributes($distributor->id, $externalId)) {
            return $parsed;
        }

        if ($execute) {
            $this->upsertPage($distributor->id, $externalId, $url, $parsed);
        }

        return $parsed;
    }

    /**
     * Magento single-page fetch: HTTP GET → MagentoProductPageParser (JSON-LD) →
     * upsert. Magento exposes NO barcode, so these listings never cluster or
     * propose; their value is the barcode-less rescue tier, which needs only the
     * item number (reduced to its bare core) + the canonical brand for scoping.
     * Returns the parsed row (for the crawl command's stats), or null on a failed
     * fetch / a page with no usable JSON-LD product.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>|null
     */
    public function crawlMagentoPage(
        Distributor $distributor,
        string $url,
        string $externalId,
        array $config,
        bool $execute,
    ): ?array {
        $parser = app(MagentoProductPageParser::class);

        $response = Http::timeout(self::TIMEOUT)
            ->withUserAgent(self::USER_AGENT)
            ->get($url);

        if ($this->classifyResponse($response) !== null) {
            return null;
        }

        $parsed = $parser->parse($response->body());

        if ($parsed === null || ($parsed['raw_sku'] ?? '') === '') {
            return null;
        }

        // The Magento sku is the manufacturer item number. Reduce it to the bare
        // core (strip the Betallic "B" suffix etc.) so it meets our catalog
        // warehouse_sku / mfg_no in the barcode-less rescue tier.
        $parsed['normalized_sku'] = $this->normalizer->normalize($parsed['raw_sku'], $config);

        // Map the store's manufacturer name to our canonical brand (BETALLIC INC →
        // Sempertex, PIONEER BALLOON → Qualatex) so the rescue tier's brand scope
        // resolves it. Stored under the "Brand" attribute the cluster engine reads.
        $brand = $this->canonicalBrand($parsed['brand'] ?? null, $config);
        $parsed['attributes'] = $brand !== null ? ['Brand' => [$brand]] : [];

        // We harvest only solid-latex category pages and, with no barcode, these
        // never cluster or self-propose — so no per-page classification is needed.
        // Leave the type unset; the rescue attaches Reorder links regardless of type.
        $parsed['product_type'] = null;
        $parsed['in_stock'] = $this->availabilityParser->parse($response->body());
        $parsed['stock'] = null;
        // For the lean Magento path the "extraction" success signal is simply that
        // we read the JSON-LD product (we got here with a raw_sku) — there's no
        // attribute table to grade. Marking it ok keeps the crawl command's
        // extraction-drift guard (built for BigCommerce tables) from mistaking
        // every page for a broken template and aborting the run. A genuine template
        // break drops the JSON-LD → parse returns null earlier → counted as failed.
        $parsed['extraction'] = ['ok' => true, 'row_count' => 1, 'missing_required' => []];

        if ($execute) {
            $this->upsertPage($distributor->id, $externalId, $url, $parsed);
        }

        return $parsed;
    }

    /**
     * Map a distributor's manufacturer name to our canonical brand via the config
     * `attribute_aliases.brand` map (case-insensitive), else return it unchanged.
     *
     * @param  array<string, mixed>  $config
     */
    private function canonicalBrand(?string $brand, array $config): ?string
    {
        if ($brand === null || $brand === '') {
            return null;
        }

        foreach (($config['attribute_aliases']['brand'] ?? []) as $from => $to) {
            if (mb_strtolower((string) $from) === mb_strtolower($brand)) {
                return (string) $to;
            }
        }

        return $brand;
    }

    /**
     * Does a staged row for this product already hold a non-empty attribute table?
     */
    private function hasStagedAttributes(string $distributorId, string $externalId): bool
    {
        $existing = DistributorProduct::where('distributor_id', $distributorId)
            ->where('external_id', $externalId)
            ->first(['raw_data']);

        return ! empty($existing?->raw_data['attributes'] ?? []);
    }

    /**
     * Upsert a batch of Shopify products into staging.
     *
     * @param  array<int, array>  $products  One-per-variant from ShopifyAdapter
     * @param  array<string, mixed>  $config
     */
    private function upsertShopifyChunk(string $distributorId, array $products, array $config): int
    {
        $externalIds = array_map(fn (array $p) => $p['identifier'], $products);
        $existing = DistributorProduct::where('distributor_id', $distributorId)
            ->whereIn('external_id', $externalIds)
            ->pluck('id', 'external_id');

        $rows = [];

        foreach ($products as $product) {
            $externalId = $product['identifier'];
            $upc = null;

            if (! empty($product['barcode'])) {
                $digits = Gtin::digitsOnly((string) $product['barcode']);
                if ($digits !== '') {
                    $upc = $digits;
                }
            }

            $rows[] = [
                'id' => $existing[$externalId] ?? (string) Str::uuid7(),
                'distributor_id' => $distributorId,
                'external_id' => $externalId,
                'raw_sku' => $product['identifier'],
                'normalized_sku' => $this->normalizer->normalize($product['identifier'], $config),
                'upc' => $upc,
                'title' => $product['name'],
                'url' => $product['url'],
                'price' => $product['price'],
                'currency' => $product['currency'] ?? 'USD',
                'stock' => null,
                'in_stock' => $product['in_stock'],
                'raw_data' => null,
                'fetched_at' => now(),
            ];
        }

        DistributorProduct::upsert(
            $rows,
            ['distributor_id', 'external_id'],
            ['raw_sku', 'normalized_sku', 'upc', 'title', 'url', 'price', 'currency', 'in_stock', 'fetched_at'],
        );

        return count($rows);
    }

    /**
     * Upsert a single BigCommerce-parsed product page into staging.
     *
     * @param  array<string, mixed>  $parsed  From BigCommerceProductPageParser::parse()
     */
    private function upsertPage(string $distributorId, string $externalId, string $url, array $parsed): void
    {
        $existing = DistributorProduct::where('distributor_id', $distributorId)
            ->where('external_id', $externalId)
            ->value('id');

        $rawData = [
            'attributes' => $parsed['attributes'] ?? [],
            'extraction' => $parsed['extraction'] ?? [],
        ];

        DistributorProduct::upsert(
            [[
                'id' => $existing ?? (string) Str::uuid7(),
                'distributor_id' => $distributorId,
                'external_id' => $externalId,
                'raw_sku' => $parsed['raw_sku'] ?? '',
                'normalized_sku' => $parsed['normalized_sku'],
                'upc' => $parsed['upc'] ?? null,
                'title' => $parsed['title'] ?? '',
                'product_type' => $parsed['product_type'] ?? null,
                'url' => $url,
                'price' => $parsed['price'] ?? null,
                'currency' => 'USD',
                'stock' => $parsed['stock'] ?? null,
                'in_stock' => $parsed['in_stock'] ?? null,
                'raw_data' => json_encode($rawData),
                'fetched_at' => now(),
            ]],
            ['distributor_id', 'external_id'],
            ['raw_sku', 'normalized_sku', 'upc', 'title', 'product_type', 'url', 'price', 'currency', 'stock', 'in_stock', 'raw_data', 'fetched_at'],
        );
    }
}
