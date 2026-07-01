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
}
