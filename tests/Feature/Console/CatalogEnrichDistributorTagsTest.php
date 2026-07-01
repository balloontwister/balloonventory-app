<?php

namespace Tests\Feature\Console;

use App\Models\Distributor;
use App\Models\DistributorProduct;
use App\Services\Distributors\DistributorProductClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tag-driven Shopify enrichment (LA Balloons): attributes come from products.json
 * tags + product_type (no HTML page), and the barcode from the per-product JSON.
 */
class CatalogEnrichDistributorTagsTest extends TestCase
{
    use RefreshDatabase;

    private function laBalloons(): Distributor
    {
        return Distributor::factory()->shopify()->create([
            'slug' => 'la-balloons',
            'base_url' => 'https://la-test.com',
            'config' => [
                'collection_handle' => 'all',
                'has_json_api' => true,
                'request_delay_ms' => 0,
                'extraction' => [
                    'tag_attributes' => [
                        'tag_map' => [
                            'Color_' => 'Color',
                            'Size_' => 'Size',
                            'Packaging_' => 'Package Type',
                            'Theme_' => 'Occasion / Theme',
                        ],
                        'product_type_map' => ['latex' => 'Latex', 'foil' => 'Foil', 'mylar' => 'Foil'],
                        'strip_words' => ['Latex', 'Foil', 'Mylar', 'Bubble'],
                        'required_labels' => ['Color', 'Size'],
                        'min_rows' => 2,
                    ],
                ],
                'sku_strip_suffixes' => ['-KL', '-B', '-M'],
            ],
        ]);
    }

    private function fakeCatalog(array $product, array $perProductVariant): void
    {
        Http::fake([
            'https://la-test.com/collections/all/products.json?limit=250&page=1' => Http::response(['products' => [$product]], 200),
            'https://la-test.com/collections/all/products.json?limit=250&page=2' => Http::response(['products' => []], 200),
            'https://la-test.com/products/'.$product['handle'].'.json' => Http::response([
                'product' => ['variants' => [$perProductVariant]],
            ], 200),
        ]);
    }

    private function kalisanLatex(): array
    {
        return [
            'handle' => 'kalisan-magenta-24',
            'title' => '24 inch KALISAN STANDARD MAGENTA PINK',
            'vendor' => 'Kalisan',
            'product_type' => 'Latex Balloons',
            'tags' => ['Color_Pink', 'Packaging_Packaged', 'Size_24" Latex', 'cf-vendor-kalisan'],
            'variants' => [['sku' => '12423596-KL']], // bulk feed has no barcode
        ];
    }

    public function test_tag_mode_stages_attributes_and_fetches_barcode_from_per_product_json(): void
    {
        Http::preventStrayRequests();

        $distributor = $this->laBalloons();
        $this->fakeCatalog($this->kalisanLatex(), ['sku' => '12423596-KL', 'barcode' => '8694573303065']);

        $this->artisan('catalog:ingest-distributor', [
            'slug' => $distributor->slug,
            '--enrich' => true,
            '--execute' => true,
        ])->assertSuccessful();

        $this->assertSame(1, DistributorProduct::count());
        $p = DistributorProduct::first();

        $this->assertSame('12423596-KL', $p->raw_sku);
        $this->assertSame('12423596', $p->normalized_sku);
        $this->assertSame('8694573303065', $p->upc); // from the per-product JSON
        $this->assertSame(DistributorProductClassifier::SOLID_LATEX, $p->product_type);

        $attrs = $p->raw_data['attributes'];
        $this->assertSame(['Kalisan'], $attrs['Brand']);          // injected from vendor
        $this->assertSame(['Round'], $attrs['Balloon Type / Shape']); // synthesised
        $this->assertSame(['Pink'], $attrs['Color']);
        $this->assertSame(['24"'], $attrs['Size']);               // material word stripped
        $this->assertSame(['Packaged'], $attrs['Package Type']);
        $this->assertSame(['Latex'], $attrs['Balloon Material']);  // from product_type
    }

    public function test_tag_mode_skips_foil_products(): void
    {
        Http::preventStrayRequests();

        $distributor = $this->laBalloons();
        Http::fake([
            'https://la-test.com/collections/all/products.json?limit=250&page=1' => Http::response(['products' => [[
                'handle' => 'betallic-foil-40',
                'title' => '40 inch Betallic Number 9 Foil Balloon',
                'vendor' => 'Betallic',
                'product_type' => 'Foil Balloon',
                'tags' => ['Color_Teal', 'Size_40" Foil'],
                'variants' => [['sku' => '14949-B']],
            ]]], 200),
            'https://la-test.com/collections/all/products.json?limit=250&page=2' => Http::response(['products' => []], 200),
        ]);

        $this->artisan('catalog:ingest-distributor', [
            'slug' => $distributor->slug,
            '--enrich' => true,
            '--execute' => true,
        ])->assertSuccessful();

        // Foil is filtered out before any page/JSON fetch.
        $this->assertSame(0, DistributorProduct::count());
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/products/betallic-foil-40.json'));
    }

    public function test_tag_mode_skips_non_latex_product_types_despite_stray_latex_tag(): void
    {
        Http::preventStrayRequests();

        $distributor = $this->laBalloons();
        // An accessory (glitter) whose product_type is NOT latex, but a stray tag
        // mentions latex. The product_type gate must keep it out.
        Http::fake([
            'https://la-test.com/collections/all/products.json?limit=250&page=1' => Http::response(['products' => [[
                'handle' => 'glitter-blue',
                'title' => '1 oz. GLITTER - METALLIC BLUE',
                'vendor' => 'LA Balloons',
                'product_type' => 'Party Decoration',
                'tags' => ['Color_Blue', 'compatible-with-latex'],
                'variants' => [['sku' => '401736-PB']],
            ]]], 200),
            'https://la-test.com/collections/all/products.json?limit=250&page=2' => Http::response(['products' => []], 200),
        ]);

        $this->artisan('catalog:ingest-distributor', [
            'slug' => $distributor->slug,
            '--enrich' => true,
            '--execute' => true,
        ])->assertSuccessful();

        $this->assertSame(0, DistributorProduct::count());
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/products/glitter-blue.json'));
    }

    public function test_tag_mode_stages_even_when_barcode_is_unavailable(): void
    {
        Http::preventStrayRequests();

        $distributor = $this->laBalloons();
        $this->fakeCatalog($this->kalisanLatex(), ['sku' => '12423596-KL']); // no barcode in per-product JSON either

        $this->artisan('catalog:ingest-distributor', [
            'slug' => $distributor->slug,
            '--enrich' => true,
            '--execute' => true,
        ])->assertSuccessful();

        $p = DistributorProduct::first();
        $this->assertNotNull($p);
        $this->assertNull($p->upc); // staged with attributes, just won't cluster
        $this->assertSame(DistributorProductClassifier::SOLID_LATEX, $p->product_type);
    }

    public function test_tag_mode_captures_stock_from_page_when_opted_in(): void
    {
        Http::preventStrayRequests();

        // Opt the tag path into the one-extra page fetch for a stock signal.
        $distributor = $this->laBalloons();
        $config = $distributor->config;
        $config['stock_from_page'] = true;
        $distributor->update(['config' => $config]);

        $product = $this->kalisanLatex();
        Http::fake([
            'https://la-test.com/collections/all/products.json?limit=250&page=1' => Http::response(['products' => [$product]], 200),
            'https://la-test.com/collections/all/products.json?limit=250&page=2' => Http::response(['products' => []], 200),
            'https://la-test.com/products/'.$product['handle'].'.json' => Http::response([
                'product' => ['variants' => [['sku' => '12423596-KL', 'barcode' => '8694573303065']]],
            ], 200),
            // The HTML page renders a JSON-LD Offer with availability.
            'https://la-test.com/products/'.$product['handle'] => Http::response(
                '<script type="application/ld+json">{"@type":"Product","offers":{"availability":"https://schema.org/InStock"}}</script>',
                200,
            ),
        ]);

        $this->artisan('catalog:ingest-distributor', [
            'slug' => $distributor->slug,
            '--enrich' => true,
            '--execute' => true,
        ])->assertSuccessful();

        $this->assertTrue(DistributorProduct::first()->in_stock);
        Http::assertSent(fn ($r) => $r->url() === 'https://la-test.com/products/'.$product['handle']);
    }

    public function test_tag_mode_does_not_fetch_page_when_not_opted_in(): void
    {
        Http::preventStrayRequests();

        // Default config (no stock_from_page) must not fetch the HTML page; the
        // faked endpoints below deliberately omit it, so a fetch would error.
        $distributor = $this->laBalloons();
        $this->fakeCatalog($this->kalisanLatex(), ['sku' => '12423596-KL', 'barcode' => '8694573303065']);

        $this->artisan('catalog:ingest-distributor', [
            'slug' => $distributor->slug,
            '--enrich' => true,
            '--execute' => true,
        ])->assertSuccessful();

        $this->assertNull(DistributorProduct::first()->in_stock);
        Http::assertNotSent(fn ($r) => $r->url() === 'https://la-test.com/products/kalisan-magenta-24');
    }

    /**
     * All American Balloons variant of the tag path: latex_type_keywords adds
     * "twisting" (its Sempertex modeling range) to the pre-filter, and
     * printed_type_keywords parks printed latex.
     */
    private function allAmerican(): Distributor
    {
        return Distributor::factory()->shopify()->create([
            'slug' => 'all-american-balloons',
            'base_url' => 'https://aab-test.com',
            'config' => [
                'collection_handle' => 'all',
                'has_json_api' => true,
                'request_delay_ms' => 0,
                'latex_type_keywords' => ['latex', 'twisting'],
                'extraction' => [
                    'tag_attributes' => [
                        'tag_map' => ['Color_' => 'Color', 'Size_' => 'Size', 'Theme_' => 'Occasion / Theme'],
                        'product_type_map' => ['latex' => 'Latex', 'twisting' => 'Latex', 'foil' => 'Foil', 'mylar' => 'Foil'],
                        'printed_type_keywords' => ['printed'],
                        'strip_words' => ['Latex', 'Foil', 'Mylar', 'Bubble'],
                        'required_labels' => ['Color', 'Size'],
                        'min_rows' => 2,
                    ],
                ],
                'attribute_aliases' => ['brand' => ['Betallic' => 'Sempertex']],
            ],
        ]);
    }

    public function test_twisting_product_type_is_enriched_as_solid_latex(): void
    {
        Http::preventStrayRequests();

        $distributor = $this->allAmerican();
        // A Sempertex modeling balloon: product_type "Twisting Balloons" (no "latex"
        // word) would be skipped by the default pre-filter, but latex_type_keywords
        // includes it.
        $product = [
            'handle' => '360s-sempertex-honey-yellow',
            'title' => '360s Sempertex Deluxe Honey Yellow Twisting Balloons | 50 Count',
            'vendor' => 'Betallic',
            'product_type' => 'Twisting Balloons',
            'tags' => ['Color_Honey Yellow', 'Size_360'],
            'variants' => [['sku' => '57826']],
        ];
        Http::fake([
            'https://aab-test.com/collections/all/products.json?limit=250&page=1' => Http::response(['products' => [$product]], 200),
            'https://aab-test.com/collections/all/products.json?limit=250&page=2' => Http::response(['products' => []], 200),
            'https://aab-test.com/products/'.$product['handle'].'.json' => Http::response([
                'product' => ['variants' => [['sku' => '57826', 'barcode' => '030625578264']]],
            ], 200),
        ]);

        $this->artisan('catalog:ingest-distributor', [
            'slug' => $distributor->slug,
            '--enrich' => true,
            '--execute' => true,
        ])->assertSuccessful();

        $p = DistributorProduct::first();
        $this->assertNotNull($p);
        $this->assertSame('030625578264', $p->upc);
        $this->assertSame(DistributorProductClassifier::SOLID_LATEX, $p->product_type);
        $this->assertSame(['Latex'], $p->raw_data['attributes']['Balloon Material']);
    }

    public function test_printed_latex_product_type_parks_as_printed(): void
    {
        Http::preventStrayRequests();

        $distributor = $this->allAmerican();
        // "Printed Latex Balloons" passes the latex pre-filter (contains "latex"),
        // but printed_type_keywords must flag it so it classifies as printed, not
        // solid — even though it carries no Theme_ tag.
        $product = [
            'handle' => 'sempertex-soccer-24',
            'title' => '24" Sempertex Soccer Printed Latex Balloons',
            'vendor' => 'Betallic',
            'product_type' => 'Printed Latex Balloons',
            'tags' => ['Color_Black', 'Color_White', 'Size_24 Inch'],
            'variants' => [['sku' => '50123']],
        ];
        Http::fake([
            'https://aab-test.com/collections/all/products.json?limit=250&page=1' => Http::response(['products' => [$product]], 200),
            'https://aab-test.com/collections/all/products.json?limit=250&page=2' => Http::response(['products' => []], 200),
            'https://aab-test.com/products/'.$product['handle'].'.json' => Http::response([
                'product' => ['variants' => [['sku' => '50123', 'barcode' => '030625501231']]],
            ], 200),
        ]);

        $this->artisan('catalog:ingest-distributor', [
            'slug' => $distributor->slug,
            '--enrich' => true,
            '--execute' => true,
        ])->assertSuccessful();

        $p = DistributorProduct::first();
        $this->assertNotNull($p);
        $this->assertSame(DistributorProductClassifier::PRINTED, $p->product_type);
    }
}
