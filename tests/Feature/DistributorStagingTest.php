<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\DistributorCatalogProposal;
use App\Models\DistributorProduct;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistributorStagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_distributor_carries_reference_fields(): void
    {
        $distributor = Distributor::factory()->shopify()->create([
            'contact_email' => 'orders@example.com',
            'contact_phone' => '800-555-0100',
            'shipping_minimum' => 25.00,
            'free_shipping_threshold' => 150.00,
            'shipping_policy' => 'Ships in 2 business days.',
            'hours' => 'Mon-Fri 9-5 ET',
            'notes' => 'Net-30 available.',
        ]);

        $fresh = $distributor->fresh();

        $this->assertSame('orders@example.com', $fresh->contact_email);
        $this->assertSame('25.00', $fresh->shipping_minimum);
        $this->assertSame('150.00', $fresh->free_shipping_threshold);
        $this->assertSame('Net-30 available.', $fresh->notes);
    }

    public function test_staged_product_round_trips(): void
    {
        $distributor = Distributor::factory()->bigcommerce()->create();

        $product = DistributorProduct::factory()->forDistributor($distributor)->create([
            'raw_sku' => 'BL-53012',
            'normalized_sku' => '53012',
            'upc' => '030625530125',
            'stock' => 54,
        ]);

        $this->assertDatabaseHas('distributor_products', [
            'id' => $product->id,
            'distributor_id' => $distributor->id,
            'normalized_sku' => '53012',
            'stock' => 54,
        ]);

        $this->assertSame($distributor->id, $product->distributor->id);
    }

    public function test_staged_product_is_unique_per_distributor_and_external_id(): void
    {
        $distributor = Distributor::factory()->shopify()->create();

        DistributorProduct::factory()->forDistributor($distributor)->create(['external_id' => 'var-1']);

        $this->expectException(QueryException::class);

        DistributorProduct::factory()->forDistributor($distributor)->create(['external_id' => 'var-1']);
    }

    public function test_proposal_defaults_to_pending_and_resolves(): void
    {
        $proposal = DistributorCatalogProposal::factory()->create([
            'upc' => '00030625530125',
            'normalized_sku' => '53012',
            'evidence' => [
                ['distributor' => 'havinaparty', 'raw_sku' => '53012', 'stock' => 54],
                ['distributor' => 'bargain-balloons', 'raw_sku' => 'BL-53012', 'upc' => '030625530125'],
            ],
        ]);

        $this->assertSame(DistributorCatalogProposal::STATUS_PENDING, $proposal->status);
        $this->assertFalse($proposal->isResolved());
        $this->assertCount(2, $proposal->evidence);

        $this->assertSame(1, DistributorCatalogProposal::pending()->count());

        $approved = DistributorCatalogProposal::factory()->autoApproved()->create();
        $this->assertTrue($approved->isResolved());
    }

    public function test_proposal_upc_is_unique(): void
    {
        DistributorCatalogProposal::factory()->create(['upc' => '00030625530125']);

        $this->expectException(QueryException::class);

        DistributorCatalogProposal::factory()->create(['upc' => '00030625530125']);
    }

    public function test_staging_tables_resolve_to_the_configured_connection(): void
    {
        // With DISTRIBUTORS_DB_CONNECTION unset the models follow the default
        // connection, which is what keeps tests and same-DB prod working.
        config()->set('distributors.connection', null);

        $this->assertSame(
            (new DistributorProduct)->getConnection()->getName(),
            (new Distributor)->getConnection()->getName(),
        );
    }
}
