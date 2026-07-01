<?php

namespace Tests\Feature\Distributors;

use App\Models\Distributor;
use App\Models\DistributorCatalogProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecomputeProposalWarehouseSkusTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Evidence where three distributors agree on item number 51508 and one
     * outlier reports a UPC-derived id — the shape the old "first member wins"
     * logic mis-stamped.
     *
     * @return array<int, array<string, mixed>>
     */
    private function disagreeingEvidence(): array
    {
        return [
            ['distributor_id' => 'larocks', 'normalized_sku' => '3062551508', 'raw_sku' => '3062551508'],
            ['distributor_id' => 'bargain', 'normalized_sku' => '51508', 'raw_sku' => 'BL-51508'],
            ['distributor_id' => 'laballoons', 'normalized_sku' => '51508', 'raw_sku' => '51508-B'],
            ['distributor_id' => 'havin', 'normalized_sku' => '51508', 'raw_sku' => '51508'],
        ];
    }

    public function test_execute_corrects_the_warehouse_sku_to_the_consensus(): void
    {
        $proposal = DistributorCatalogProposal::factory()->create([
            'normalized_sku' => '3062551508',
            'proposed_warehouse_sku' => '3062551508',
            'evidence' => $this->disagreeingEvidence(),
        ]);

        $this->artisan('catalog:recompute-proposal-warehouse-skus --execute')
            ->assertSuccessful();

        $proposal->refresh();
        $this->assertSame('51508', $proposal->normalized_sku);
        $this->assertSame('51508', $proposal->proposed_warehouse_sku);
    }

    public function test_it_fills_an_empty_alphanumeric_warehouse_sku_by_renormalizing(): void
    {
        // Two stores wrap the alphanumeric item code 56360P2 (LA "-B" suffix,
        // Joker "BT-" prefix); the old normalizer discarded it, leaving the SKU
        // null. Re-normalizing with their configs recovers it.
        $la = Distributor::factory()->create(['config' => ['sku_strip_suffixes' => ['-B']]]);
        $joker = Distributor::factory()->create(['config' => ['sku_strip_prefixes' => ['BT-']]]);

        $proposal = DistributorCatalogProposal::factory()->create([
            'upc' => '00030625563604',
            'normalized_sku' => null,
            'proposed_warehouse_sku' => null,
            'evidence' => [
                ['distributor_id' => $la->id, 'raw_sku' => '56360P2-B', 'normalized_sku' => null],
                ['distributor_id' => $joker->id, 'raw_sku' => 'BT-56360P2', 'normalized_sku' => null],
            ],
        ]);

        $this->artisan('catalog:recompute-proposal-warehouse-skus --execute')
            ->assertSuccessful();

        $this->assertSame('56360P2', $proposal->fresh()->proposed_warehouse_sku);
    }

    public function test_it_does_not_overwrite_a_member_with_a_stored_normalized_sku(): void
    {
        // A larocks-style member: raw_sku is the barcode, but its normalized_sku
        // came from a different field (mpn) at ingest — re-normalizing raw_sku
        // would corrupt it, so a stored value must be left alone.
        $proposal = DistributorCatalogProposal::factory()->create([
            'upc' => '08693296864306',
            'normalized_sku' => '31230032',
            'proposed_warehouse_sku' => '31230032',
            'evidence' => [
                ['distributor_id' => 'larocks', 'raw_sku' => '8693296864306', 'normalized_sku' => '31230032'],
            ],
        ]);

        $this->artisan('catalog:recompute-proposal-warehouse-skus --execute')->assertSuccessful();

        $this->assertSame('31230032', $proposal->fresh()->proposed_warehouse_sku);
    }

    public function test_it_does_not_recover_a_barcode_only_raw_sku(): void
    {
        // The only member's normalized_sku is null and its raw_sku is just the
        // barcode — recovery must not stamp the barcode as a warehouse SKU.
        $proposal = DistributorCatalogProposal::factory()->create([
            'upc' => '08693296864306',
            'normalized_sku' => null,
            'proposed_warehouse_sku' => null,
            'evidence' => [
                ['distributor_id' => 'larocks', 'raw_sku' => '8693296864306', 'normalized_sku' => null, 'raw_upc' => '8693296864306'],
            ],
        ]);

        $this->artisan('catalog:recompute-proposal-warehouse-skus --execute')->assertSuccessful();

        $this->assertNull($proposal->fresh()->proposed_warehouse_sku);
    }

    public function test_dry_run_reports_but_does_not_write(): void
    {
        $proposal = DistributorCatalogProposal::factory()->create([
            'normalized_sku' => '3062551508',
            'proposed_warehouse_sku' => '3062551508',
            'evidence' => $this->disagreeingEvidence(),
        ]);

        $this->artisan('catalog:recompute-proposal-warehouse-skus')
            ->assertSuccessful();

        $this->assertSame('3062551508', $proposal->fresh()->normalized_sku);
    }

    public function test_a_manually_edited_warehouse_sku_is_left_untouched(): void
    {
        $proposal = DistributorCatalogProposal::factory()->create([
            'normalized_sku' => '3062551508',
            // The admin already corrected the warehouse SKU to something custom —
            // it differs from the auto-stamped normalized_sku, so it's protected.
            'proposed_warehouse_sku' => '99999',
            'evidence' => $this->disagreeingEvidence(),
        ]);

        $this->artisan('catalog:recompute-proposal-warehouse-skus --execute')
            ->assertSuccessful();

        $this->assertSame('99999', $proposal->fresh()->proposed_warehouse_sku);
    }

    public function test_a_promoted_proposal_is_skipped(): void
    {
        $proposal = DistributorCatalogProposal::factory()->create([
            'status' => DistributorCatalogProposal::STATUS_APPROVED,
            'resulting_sku_id' => (string) Str::uuid7(),
            'normalized_sku' => '3062551508',
            'proposed_warehouse_sku' => '3062551508',
            'evidence' => $this->disagreeingEvidence(),
        ]);

        $this->artisan('catalog:recompute-proposal-warehouse-skus --execute')
            ->assertSuccessful();

        $this->assertSame('3062551508', $proposal->fresh()->normalized_sku);
    }
}
