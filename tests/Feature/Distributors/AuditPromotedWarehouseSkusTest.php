<?php

namespace Tests\Feature\Distributors;

use App\Models\DistributorCatalogProposal;
use App\Models\Sku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditPromotedWarehouseSkusTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Evidence where the barcode-embedded item number (51508) is the minority
     * report — three stores agree, one outlier carries a UPC-derived id.
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

    private function promotedSku(string $warehouseSku, string $proposedWarehouseSku): Sku
    {
        $sku = Sku::factory()->create(['warehouse_sku' => $warehouseSku]);

        DistributorCatalogProposal::factory()->create([
            'status' => DistributorCatalogProposal::STATUS_AUTO_APPROVED,
            'upc' => '00030625515085',
            'resulting_sku_id' => $sku->id,
            'normalized_sku' => $proposedWarehouseSku,
            'proposed_warehouse_sku' => $proposedWarehouseSku,
            'evidence' => $this->disagreeingEvidence(),
        ]);

        return $sku;
    }

    public function test_execute_corrects_an_affected_promoted_sku(): void
    {
        $sku = $this->promotedSku('3062551508', '3062551508');

        $this->artisan('catalog:audit-promoted-warehouse-skus --execute')->assertSuccessful();

        $this->assertSame('51508', $sku->fresh()->warehouse_sku);
    }

    public function test_audit_is_read_only_without_execute(): void
    {
        $sku = $this->promotedSku('3062551508', '3062551508');

        $this->artisan('catalog:audit-promoted-warehouse-skus')->assertSuccessful();

        $this->assertSame('3062551508', $sku->fresh()->warehouse_sku);
    }

    public function test_a_manually_edited_sku_is_left_untouched(): void
    {
        // The SKU's warehouse_sku no longer matches what promotion stamped, so an
        // admin edited it — the audit must not overwrite that.
        $sku = $this->promotedSku('CUSTOM-123', '3062551508');

        $this->artisan('catalog:audit-promoted-warehouse-skus --execute')->assertSuccessful();

        $this->assertSame('CUSTOM-123', $sku->fresh()->warehouse_sku);
    }

    public function test_a_correct_sku_is_not_reported(): void
    {
        $sku = $this->promotedSku('51508', '51508');

        $this->artisan('catalog:audit-promoted-warehouse-skus --execute')
            ->expectsOutputToContain('Nothing to do')
            ->assertSuccessful();

        $this->assertSame('51508', $sku->fresh()->warehouse_sku);
    }
}
