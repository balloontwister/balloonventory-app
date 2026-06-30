<?php

namespace Tests\Feature;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Color;
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
        // havinaparty: no barcode, no price (login-gated). Carries a brand, so it
        // can be confirmed as the same product when it inherits the UPC.
        $this->stage($this->havin, [
            'external_id' => 'h-1', 'raw_sku' => '53012', 'normalized_sku' => '53012',
            'upc' => null, 'price' => null, 'title' => '11"S Red Fashion (100 count)', 'stock' => 54,
            'product_type' => 'solid_latex', 'raw_data' => ['attributes' => ['Brand' => ['Sempertex']]],
        ]);
        // BargainBalloons + LA Balloons: same product, same UPC, decorated SKUs.
        $this->stage($this->bargain, [
            'external_id' => 'b-1', 'raw_sku' => 'BL-53012', 'normalized_sku' => '53012',
            'upc' => '030625530125', 'price' => 13.97, 'title' => '11 inch Latex Balloons 100 Per Bag Fashion Red Betallatex',
            'product_type' => 'solid_latex', 'raw_data' => ['attributes' => ['Brand' => ['Sempertex']]],
        ]);
        $this->stage($this->laballoons, [
            'external_id' => 'l-1', 'raw_sku' => '53012-B', 'normalized_sku' => '53012',
            'upc' => '030625530125', 'price' => 21.69, 'title' => '11 inch Sempertex Fashion Red Latex Balloons',
            'product_type' => 'solid_latex', 'raw_data' => ['attributes' => ['Brand' => ['Sempertex']]],
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

    public function test_barcodeless_member_without_a_brand_does_not_inherit(): void
    {
        // The real bug: an Elitex latex barcode + a BargainBalloons foil row that
        // shares the bare item number 36683 but carries NO attributes. It must NOT
        // inherit the Elitex UPC (different product), so the cluster stays clean.
        $this->stage($this->bargain, [
            'external_id' => 'foil-1', 'raw_sku' => '36683-01', 'normalized_sku' => '36683',
            'upc' => null, 'title' => '23" Star Wars SuperShape Foil Balloon',
            'raw_data' => ['attributes' => []], // no brand
        ]);
        $this->stage($this->laballoons, [
            'external_id' => 'elitex-1', 'raw_sku' => '8853406036683', 'normalized_sku' => '36683',
            'upc' => '8853406036683', 'title' => '12 Inch Round Pastel Ivory Elitex',
            'product_type' => 'solid_latex', 'raw_data' => ['attributes' => ['Brand' => ['Elitex']]],
        ]);

        $clusters = $this->engine->buildClusters(DistributorProduct::all());

        $this->assertCount(1, $clusters);
        // Only the Elitex member — the no-brand foil row was not pulled in.
        $this->assertCount(1, $clusters->first()['members']);
        $this->assertSame('8853406036683', $clusters->first()['members'][0]['raw_sku']);
    }

    public function test_barcodeless_member_with_a_mismatched_brand_does_not_inherit(): void
    {
        // Same bare number, but the barcode-less row IS branded — wrongly. A foil
        // "Anagram" item 36683 must not inherit the Elitex UPC.
        $this->stage($this->havin, [
            'external_id' => 'ana-1', 'raw_sku' => '36683', 'normalized_sku' => '36683',
            'upc' => null, 'title' => '23" Star Wars Foil',
            'raw_data' => ['attributes' => ['Brand' => ['Anagram']]],
        ]);
        $this->stage($this->laballoons, [
            'external_id' => 'elitex-2', 'raw_sku' => '8853406036683', 'normalized_sku' => '36683',
            'upc' => '8853406036683', 'title' => '12 Inch Pastel Ivory Elitex',
            'raw_data' => ['attributes' => ['Brand' => ['Elitex']]],
        ]);

        $clusters = $this->engine->buildClusters(DistributorProduct::all());

        $this->assertCount(1, $clusters->first()['members']); // Elitex only
    }

    public function test_a_bare_member_does_not_lift_confidence_to_high(): void
    {
        // One real attribute source (LA) + a barcoded but attribute-less second
        // distributor sharing the UPC → must stay low confidence, not high.
        $this->stage($this->laballoons, [
            'external_id' => 'la-c', 'raw_sku' => '99', 'normalized_sku' => '99',
            'upc' => '030625530125', 'title' => '11 inch Sempertex Fashion Red',
            'product_type' => 'solid_latex', 'raw_data' => ['attributes' => ['Brand' => ['Sempertex'], 'Quantity' => ['100']]],
        ]);
        $this->stage($this->bargain, [
            'external_id' => 'bb-bare', 'raw_sku' => 'X', 'normalized_sku' => 'X',
            'upc' => '030625530125', 'title' => 'bare row', 'product_type' => 'solid_latex',
            'raw_data' => ['attributes' => []], // no brand → no corroboration
        ]);

        $this->engine->run(execute: true);

        $this->assertSame('low', DistributorCatalogProposal::first()->confidence);
    }

    public function test_confident_type_wins_over_a_weakly_classified_member(): void
    {
        // Same UPC, two distributors. The one read weakly (non_balloon — e.g. a
        // table-less page) is staged FIRST, so it leads the member list; the
        // cluster must still take the confident solid_latex classification.
        $this->stage($this->bargain, [
            'external_id' => 'weak-1', 'raw_sku' => 'BL-53012', 'normalized_sku' => '53012',
            'upc' => '030625530125', 'title' => 'unreadable page', 'product_type' => 'non_balloon',
        ]);
        $this->stage($this->laballoons, [
            'external_id' => 'good-1', 'raw_sku' => '53012-B', 'normalized_sku' => '53012',
            'upc' => '030625530125', 'title' => '11 inch Sempertex Fashion Red Latex Balloons',
            'product_type' => 'solid_latex',
        ]);

        $clusters = $this->engine->buildClusters(DistributorProduct::all());

        $this->assertCount(1, $clusters);
        $this->assertSame('solid_latex', $clusters->first()['product_type']);
    }

    public function test_cluster_with_only_weak_members_keeps_the_weak_type(): void
    {
        $this->stage($this->bargain, [
            'external_id' => 'weak-only', 'raw_sku' => 'X', 'normalized_sku' => 'X',
            'upc' => '030625530125', 'title' => 'novelty', 'product_type' => 'non_balloon',
        ]);

        $clusters = $this->engine->buildClusters(DistributorProduct::all());

        $this->assertSame('non_balloon', $clusters->first()['product_type']);
    }

    private function stageHavinSku(string $sku, string $brand): DistributorProduct
    {
        return $this->stage($this->havin, [
            'external_id' => 'h-'.$sku, 'raw_sku' => $sku, 'normalized_sku' => $sku,
            'upc' => null, 'url' => 'https://havinaparty.com/'.$sku.'/',
            'title' => $sku.' Mirror Silver (50 count)', 'stock' => 34, 'product_type' => 'solid_latex',
            'raw_data' => ['attributes' => ['Brand' => [$brand]]],
        ]);
    }

    public function test_barcodeless_listing_matches_catalog_by_warehouse_sku_and_attaches_a_url(): void
    {
        $brand = Brand::factory()->create(['name' => 'Kalisan']);
        $sku = Sku::factory()->create(['brand_id' => $brand->id, 'warehouse_sku' => '10150025', 'upc' => null]);
        $this->havin->update(['config' => ['match_by_warehouse_sku' => true]]);

        $this->stageHavinSku('10150025', 'Kalisan');

        $stats = $this->engine->run(execute: true);

        $this->assertSame(1, $stats['matched_by_warehouse_sku']);
        $this->assertSame(0, $stats['proposals']); // attaches to an existing SKU, never creates
        $this->assertSame(1, DistributorSkuUrl::where('sku_id', $sku->id)
            ->where('distributor_id', $this->havin->id)->count());
    }

    public function test_warehouse_sku_match_requires_the_config_flag(): void
    {
        $brand = Brand::factory()->create(['name' => 'Kalisan']);
        Sku::factory()->create(['brand_id' => $brand->id, 'warehouse_sku' => '10150025']);
        $this->havin->update(['config' => []]); // flag NOT set

        $this->stageHavinSku('10150025', 'Kalisan');

        $this->assertSame(0, $this->engine->run(execute: true)['matched_by_warehouse_sku']);
    }

    public function test_warehouse_sku_match_is_rejected_on_a_brand_mismatch(): void
    {
        $kalisan = Brand::factory()->create(['name' => 'Kalisan']);
        Brand::factory()->create(['name' => 'Sempertex']);
        Sku::factory()->create(['brand_id' => $kalisan->id, 'warehouse_sku' => '10150025']);
        $this->havin->update(['config' => ['match_by_warehouse_sku' => true]]);

        // Same bare number, but the listing's brand is Sempertex → must not match
        // the Kalisan SKU.
        $this->stageHavinSku('10150025', 'Sempertex');

        $this->assertSame(0, $this->engine->run(execute: true)['matched_by_warehouse_sku']);
    }

    public function test_ambiguous_warehouse_sku_does_not_match(): void
    {
        $brand = Brand::factory()->create(['name' => 'Kalisan']);
        Sku::factory()->create(['brand_id' => $brand->id, 'warehouse_sku' => '10150025']);
        Sku::factory()->create(['brand_id' => $brand->id, 'warehouse_sku' => '10150025']); // two SKUs, same brand+code
        $this->havin->update(['config' => ['match_by_warehouse_sku' => true]]);

        $this->stageHavinSku('10150025', 'Kalisan');

        $this->assertSame(0, $this->engine->run(execute: true)['matched_by_warehouse_sku']);
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

    public function test_warehouse_sku_is_the_consensus_across_distributors(): void
    {
        $larocks = Distributor::factory()->bigcommerce()->create(['slug' => 'larocks']);
        $shared = [
            'upc' => '030625515085',
            'product_type' => 'solid_latex',
            'raw_data' => ['attributes' => ['Brand' => ['Sempertex']]],
        ];

        // Three stores agree the manufacturer item number is 51508.
        $this->stage($this->bargain, ['external_id' => 'b', 'raw_sku' => 'BL-51508', 'normalized_sku' => '51508'] + $shared);
        $this->stage($this->laballoons, ['external_id' => 'l', 'raw_sku' => '51508-B', 'normalized_sku' => '51508'] + $shared);
        $this->stage($this->havin, ['external_id' => 'h', 'raw_sku' => '51508', 'normalized_sku' => '51508'] + $shared);
        // A fourth store reports a UPC-derived internal id — the outlier that used
        // to win just by being first.
        $this->stage($larocks, ['external_id' => 'la', 'raw_sku' => '3062551508', 'normalized_sku' => '3062551508'] + $shared);

        $this->engine->run(execute: true);

        $proposal = DistributorCatalogProposal::sole();
        $this->assertSame('51508', $proposal->normalized_sku);
        $this->assertSame('51508', $proposal->proposed_warehouse_sku);
    }

    public function test_warehouse_sku_breaks_a_tie_toward_the_shorter_item_number(): void
    {
        $larocks = Distributor::factory()->bigcommerce()->create(['slug' => 'larocks']);
        $shared = [
            'upc' => '030625515085',
            'product_type' => 'solid_latex',
            'raw_data' => ['attributes' => ['Brand' => ['Sempertex']]],
        ];

        // One vote each — the shorter bare item number wins over the long internal id.
        $this->stage($this->bargain, ['external_id' => 'b', 'raw_sku' => 'BL-51508', 'normalized_sku' => '51508'] + $shared);
        $this->stage($larocks, ['external_id' => 'la', 'raw_sku' => '3062551508', 'normalized_sku' => '3062551508'] + $shared);

        $this->engine->run(execute: true);

        $this->assertSame('51508', DistributorCatalogProposal::sole()->proposed_warehouse_sku);
    }

    public function test_run_stamps_resolution_for_grouping(): void
    {
        $brand = Brand::factory()->create(['name' => 'Sempertex']);
        BalloonSize::factory()->create(['brand_id' => $brand->id, 'name' => 'R-12']);
        Color::factory()->create(['brand_id' => $brand->id, 'name' => 'Red']);

        $this->stage($this->bargain, [
            'external_id' => 's-1', 'raw_sku' => '53012', 'normalized_sku' => '53012',
            'upc' => '030625530125', 'title' => '12 inch Round Red Sempertex 100ct',
            'product_type' => 'solid_latex',
            'raw_data' => ['attributes' => [
                'Brand' => ['Sempertex'],
                'Size' => ['12 inch'],
                'Balloon Type / Shape' => ['Solid Color', 'Round'],
                'Color' => ['Red'],
            ]],
        ]);

        $this->engine->run(execute: true);

        $proposal = DistributorCatalogProposal::sole();
        $this->assertSame($brand->id, $proposal->resolved_brand_id);
        $this->assertSame('Sempertex', $proposal->resolved_brand_name);
        $this->assertSame(DistributorCatalogProposal::RESOLUTION_FULL, $proposal->resolution_state);
        $this->assertSame('Red', $proposal->resolution['color']['name']);
    }

    public function test_proposed_count_prefers_structured_quantity_over_the_title(): void
    {
        $this->stage($this->bargain, [
            'external_id' => 'q-1', 'raw_sku' => '53012', 'normalized_sku' => '53012',
            'upc' => '030625530125', 'title' => '11 inch Red Fashion 100ct', // title says 100
            'product_type' => 'solid_latex',
            'raw_data' => ['attributes' => ['Brand' => ['Sempertex'], 'Quantity' => ['50 ct']]], // structured says 50
        ]);

        $this->engine->run(execute: true);

        $this->assertSame(50, DistributorCatalogProposal::sole()->proposed_count);
    }

    public function test_only_solid_latex_is_proposed_other_types_are_parked(): void
    {
        // A solid latex product and a foil product, each its own UPC cluster.
        $this->stage($this->bargain, [
            'external_id' => 'latex-1', 'raw_sku' => 'L1', 'normalized_sku' => 'L1',
            'upc' => '030625530125', 'title' => '11 inch Red Latex', 'product_type' => 'solid_latex',
        ]);
        $this->stage($this->bargain, [
            'external_id' => 'foil-1', 'raw_sku' => 'F1', 'normalized_sku' => 'F1',
            'upc' => '026635403511', 'title' => '18 inch Birthday Foil', 'product_type' => 'foil',
        ]);

        $stats = $this->engine->run(execute: true);

        $this->assertSame(2, $stats['clusters']);
        $this->assertSame(1, $stats['proposals']);
        $this->assertSame(1, $stats['deferred']);
        $this->assertSame(['foil' => 1], $stats['deferred_by_type']);

        // Only the latex product became a proposal; the foil is parked in staging.
        $proposal = DistributorCatalogProposal::sole();
        $this->assertSame('00030625530125', $proposal->upc);
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
