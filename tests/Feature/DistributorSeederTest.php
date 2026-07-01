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
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(DistributorSeeder::class);
        $this->seed(DistributorSeeder::class);

        $this->assertSame(1, Distributor::where('slug', 'havin-a-party')->count());
    }
}
