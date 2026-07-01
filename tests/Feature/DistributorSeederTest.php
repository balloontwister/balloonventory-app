<?php

namespace Tests\Feature;

use App\Models\Distributor;
use Database\Seeders\DistributorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistributorSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_havin_a_party_as_a_bigcommerce_distributor(): void
    {
        $this->seed(DistributorSeeder::class);

        $distributor = Distributor::where('slug', 'havin-a-party')->first();

        $this->assertNotNull($distributor);
        $this->assertSame("Havin' A Party", $distributor->name);
        $this->assertSame('bigcommerce', $distributor->platform_type);
        $this->assertSame('https://havinaparty.com', $distributor->base_url);
        $this->assertTrue($distributor->is_active);

        // Slow, jittered crawl (~1 MB pages behind Cloudflare).
        $this->assertSame(1500, $distributor->config['request_delay_ms']);
        $this->assertSame(1000, $distributor->config['request_jitter_ms']);
        $this->assertSame(['11' => '12'], $distributor->config['size_number_aliases']['Sempertex']);
    }

    public function test_it_seeds_joker_party_supply_with_the_product_json_recipe(): void
    {
        $this->seed(DistributorSeeder::class);

        $distributor = Distributor::where('slug', 'joker-party-supply')->first();

        $this->assertNotNull($distributor);
        $this->assertSame('Joker Party Supply', $distributor->name);
        $this->assertSame('shopify', $distributor->platform_type);
        $this->assertTrue($distributor->is_active);

        // Enrich from the per-product JSON body_html, latex collection only.
        $this->assertSame('latex', $distributor->config['collection_handle']);
        $this->assertTrue($distributor->config['enrich_from_product_json']);
        $this->assertSame(
            ['section_marker' => 'Product Information'],
            $distributor->config['extraction']['attribute_rows'],
        );
        $this->assertSame('Quantity', $distributor->config['extraction']['label_map']['count']);
        $this->assertSame(['BT-'], $distributor->config['sku_strip_prefixes']);
        // Rescue barcode-less (Gemar) listings by SKU, bridging our "G" prefix.
        $this->assertTrue($distributor->config['match_by_warehouse_sku']);
        $this->assertSame(['G'], $distributor->config['warehouse_sku_prefixes']);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(DistributorSeeder::class);
        $this->seed(DistributorSeeder::class);

        $this->assertSame(1, Distributor::where('slug', 'havin-a-party')->count());
    }

    public function test_it_seeds_all_american_balloons_with_the_tag_recipe(): void
    {
        $this->seed(DistributorSeeder::class);

        $distributor = Distributor::where('slug', 'all-american-balloons')->first();

        $this->assertNotNull($distributor);
        $this->assertSame('All American Balloons', $distributor->name);
        $this->assertSame('shopify', $distributor->platform_type);
        $this->assertSame('https://www.allamericanballoons.net', $distributor->base_url);
        $this->assertTrue($distributor->is_active);

        // Tag-driven Shopify recipe (LA Balloons archetype) + per-product barcode.
        $this->assertSame('all', $distributor->config['collection_handle']);
        $this->assertTrue($distributor->config['stock_from_page']);
        $this->assertSame(
            ['Color_' => 'Color', 'Size_' => 'Size', 'Theme_' => 'Occasion / Theme'],
            $distributor->config['extraction']['tag_attributes']['tag_map'],
        );
        // "Twisting Balloons" (Sempertex modeling) counts as latex.
        $this->assertSame(['latex', 'twisting'], $distributor->config['latex_type_keywords']);
        $this->assertSame('Latex', $distributor->config['extraction']['tag_attributes']['product_type_map']['twisting']);
        $this->assertSame(['printed'], $distributor->config['extraction']['tag_attributes']['printed_type_keywords']);
        // Vendor (manufacturer parent) → our brand.
        $this->assertSame('Sempertex', $distributor->config['attribute_aliases']['brand']['Betallic']);
        $this->assertSame(['11' => '12'], $distributor->config['size_number_aliases']['Sempertex']);
        $this->assertTrue($distributor->config['match_by_warehouse_sku']);
    }
}
