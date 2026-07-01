<?php

namespace Tests\Feature\Distributors;

use App\Models\Distributor;
use App\Models\DistributorProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenormalizeStagedSkusTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_backfills_a_now_recoverable_alphanumeric_sku(): void
    {
        $distributor = Distributor::factory()->create(['config' => ['sku_strip_suffixes' => ['-B']]]);

        // Staged with the old normalizer, which discarded the alphanumeric core.
        $product = DistributorProduct::factory()->forDistributor($distributor)->create([
            'raw_sku' => '56360P2-B',
            'normalized_sku' => null,
        ]);

        $this->artisan('catalog:renormalize-staged-skus --execute')->assertSuccessful();

        $this->assertSame('56360P2', $product->fresh()->normalized_sku);
    }

    public function test_dry_run_does_not_write(): void
    {
        $distributor = Distributor::factory()->create(['config' => ['sku_strip_suffixes' => ['-B']]]);
        $product = DistributorProduct::factory()->forDistributor($distributor)->create([
            'raw_sku' => '56360P2-B',
            'normalized_sku' => null,
        ]);

        $this->artisan('catalog:renormalize-staged-skus')->assertSuccessful();

        $this->assertNull($product->fresh()->normalized_sku);
    }

    public function test_already_correct_products_are_left_alone(): void
    {
        $distributor = Distributor::factory()->create();
        DistributorProduct::factory()->forDistributor($distributor)->create([
            'raw_sku' => '53012',
            'normalized_sku' => '53012',
        ]);

        $this->artisan('catalog:renormalize-staged-skus --execute')
            ->expectsOutputToContain('Nothing to do')
            ->assertSuccessful();
    }

    public function test_an_existing_normalized_sku_is_never_overwritten(): void
    {
        $distributor = Distributor::factory()->create();
        // raw_sku is the barcode, but normalized_sku came from mpn at ingest — the
        // command must not re-derive (and corrupt) it from raw_sku.
        $product = DistributorProduct::factory()->forDistributor($distributor)->create([
            'raw_sku' => '8693296864306',
            'normalized_sku' => '31230032',
        ]);

        $this->artisan('catalog:renormalize-staged-skus --execute')->assertSuccessful();

        $this->assertSame('31230032', $product->fresh()->normalized_sku);
    }

    public function test_a_pure_numeric_null_is_left_alone(): void
    {
        $distributor = Distributor::factory()->create();
        // A pure-numeric raw_sku was never dropped by the old normalizer, so its
        // null normalized_sku means the item number came from another field —
        // recovering it from raw_sku would be a guess. Leave it null.
        $product = DistributorProduct::factory()->forDistributor($distributor)->create([
            'raw_sku' => '1978436278',
            'normalized_sku' => null,
        ]);

        $this->artisan('catalog:renormalize-staged-skus --execute')
            ->expectsOutputToContain('Nothing to do')
            ->assertSuccessful();

        $this->assertNull($product->fresh()->normalized_sku);
    }

    public function test_a_barcode_in_raw_sku_is_not_turned_into_an_item_number(): void
    {
        $distributor = Distributor::factory()->create();
        $product = DistributorProduct::factory()->forDistributor($distributor)->create([
            'raw_sku' => '8693296864306', // 13-digit EAN, no real item number to recover
            'upc' => '8693296864306',
            'normalized_sku' => null,
        ]);

        $this->artisan('catalog:renormalize-staged-skus --execute')->assertSuccessful();

        $this->assertNull($product->fresh()->normalized_sku);
    }
}
