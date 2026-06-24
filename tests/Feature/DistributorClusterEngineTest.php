<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\DistributorCatalogProposal;
use App\Models\DistributorProduct;
use App\Models\DistributorSkuUrl;
use App\Models\Sku;
use App\Services\DistributorClusterEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistributorClusterEngineTest extends TestCase
{
    use RefreshDatabase;

    private DistributorClusterEngine $engine;

    private Distributor $havin;

    private Distributor $bargain;

    private Distributor $laballoons;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = app(DistributorClusterEngine::class);
        $this->havin = Distributor::factory()->bigcommerce()->create(['slug' => 'havinaparty']);
        $this->bargain = Distributor::factory()->shopify()->create(['slug' => 'bargain-balloons']);
        $this->laballoons = Distributor::factory()->shopify()->create(['slug' => 'laballoons']);
    }

    private function stage(Distributor $d, array $attrs): DistributorProduct
    {
        return DistributorProduct::factory()->forDistributor($d)->create($attrs);
    }

    private function the100ctTrio(): void
    {
        // havinaparty: no barcode, no price (login-gated).
        $this->stage($this->havin, [
            'external_id' => 'h-1', 'raw_sku' => '53012', 'normalized_sku' => '53012',
            'upc' => null, 'price' => null, 'title' => '11"S Red Fashion (100 count)', 'stock' => 54,
        ]);
        // BargainBalloons + LA Balloons: same product, same UPC, decorated SKUs.
        $this->stage($this->bargain, [
            'external_id' => 'b-1', 'raw_sku' => 'BL-53012', 'normalized_sku' => '53012',
            'upc' => '030625530125', 'price' => 13.97, 'title' => '11 inch Latex Balloons 100 Per Bag Fashion Red Betallatex',
        ]);
        $this->stage($this->laballoons, [
            'external_id' => 'l-1', 'raw_sku' => '53012-B', 'normalized_sku' => '53012',
            'upc' => '030625530125', 'price' => 21.69, 'title' => '11 inch Sempertex Fashion Red Latex Balloons',
        ]);
    }

    public function test_three_distributors_collapse_into_one_upc_cluster(): void
    {
        $this->the100ctTrio();

        $clusters = $this->engine->buildClusters(DistributorProduct::all());

        $this->assertCount(1, $clusters);
        $cluster = $clusters->first();
        $this->assertSame('00030625530125', $cluster['upc']);
        $this->assertSame('53012', $cluster['normalized_sku']);
        $this->assertCount(3, $cluster['members']);

        // The barcode-less havinaparty listing inherited the UPC.
        $inherited = collect($cluster['members'])->firstWhere('distributor_id', $this->havin->id);
        $this->assertTrue($inherited['inherited_upc']);
    }

    public function test_ambiguous_normalized_sku_is_not_force_merged(): void
    {
        $this->the100ctTrio();

        // A 10-ct variant shares the core number 53012 but a different UPC.
        $this->stage($this->laballoons, [
            'external_id' => 'l-2', 'raw_sku' => '53012-B-10', 'normalized_sku' => '53012',
            'upc' => '721214009114', 'title' => '11 inch Fashion Red 10 count',
        ]);

        $clusters = $this->engine->buildClusters(DistributorProduct::all());

        // Two distinct UPC clusters; the bare-53012 havinaparty listing can no
        // longer be safely placed, so it stays out of both.
        $this->assertCount(2, $clusters);

        $hundred = $clusters->firstWhere('upc', '00030625530125');
        $this->assertCount(2, $hundred['members']); // only Bargain + LA, NOT havinaparty
        $this->assertFalse(
            collect($hundred['members'])->contains(fn ($m) => $m['distributor_id'] === $this->havin->id),
        );
    }

    public function test_run_writes_a_pending_proposal_for_a_new_product(): void
    {
        $this->the100ctTrio();

        $stats = $this->engine->run(execute: true);

        $this->assertSame(1, $stats['clusters']);
        $this->assertSame(0, $stats['matched_existing']);
        $this->assertSame(1, $stats['proposals']);

        $proposal = DistributorCatalogProposal::sole();
        $this->assertSame('00030625530125', $proposal->upc);
        $this->assertSame(DistributorCatalogProposal::STATUS_PENDING, $proposal->status);
        $this->assertSame('high', $proposal->confidence); // 3 distributors + parsed count
        $this->assertSame(100, $proposal->proposed_count);
        $this->assertSame('53012', $proposal->normalized_sku);
        $this->assertCount(3, $proposal->evidence);
    }

    public function test_run_skips_clusters_already_in_the_catalog(): void
    {
        $this->the100ctTrio();
        $sku = Sku::factory()->create(['upc' => '030625530125', 'is_active' => true]);

        $stats = $this->engine->run(execute: true);

        $this->assertSame(1, $stats['matched_existing']);
        $this->assertSame(0, $stats['proposals']);
        $this->assertSame(0, DistributorCatalogProposal::count());

        // Distributor URLs should be attached for the Reorder page.
        $this->assertSame(3, $stats['urls_attached']);

        $urls = DistributorSkuUrl::where('sku_id', $sku->id)->get();
        $this->assertCount(3, $urls);

        // Each distributor gets one URL row.
        $distributorIds = $urls->pluck('distributor_id')->unique();
        $this->assertCount(3, $distributorIds);
        $this->assertTrue($distributorIds->contains($this->havin->id));
        $this->assertTrue($distributorIds->contains($this->bargain->id));
        $this->assertTrue($distributorIds->contains($this->laballoons->id));

        // The havinaparty member carries null price (login-gated) and stock=54.
        $havinUrl = $urls->firstWhere('distributor_id', $this->havin->id);
        $this->assertNull($havinUrl->price);
        $this->assertTrue($havinUrl->in_stock);
    }

    public function test_run_does_not_clobber_a_reviewed_proposal(): void
    {
        $this->the100ctTrio();

        // A human already rejected this UPC.
        DistributorCatalogProposal::factory()->rejected()->create([
            'upc' => '00030625530125',
            'proposed_name' => 'old name',
        ]);

        $this->engine->run(execute: true);

        $proposal = DistributorCatalogProposal::where('upc', '00030625530125')->sole();
        $this->assertSame(DistributorCatalogProposal::STATUS_REJECTED, $proposal->status);
        // Evidence still refreshed even though the decision is preserved.
        $this->assertCount(3, $proposal->evidence);
    }

    public function test_unclustered_listings_are_counted(): void
    {
        // A lone barcode-less listing with no sibling → can't cluster.
        $this->stage($this->havin, [
            'external_id' => 'lonely', 'raw_sku' => '99999', 'normalized_sku' => '99999', 'upc' => null,
        ]);

        $stats = $this->engine->run(execute: false);

        $this->assertSame(0, $stats['clusters']);
        $this->assertSame(1, $stats['unclustered']);
    }
}
