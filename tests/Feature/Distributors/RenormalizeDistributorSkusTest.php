<?php

namespace Tests\Feature\Distributors;

use App\Models\Distributor;
use App\Models\DistributorProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * catalog:renormalize-distributor-skus — the fix-forward tool for a config
 * change: unlike renormalize-staged-skus (fills nulls only), this OVERWRITES a
 * distributor's stale normalized_sku values, since for a distributor where
 * normalized_sku is always derived from raw_sku, re-running the normalizer with
 * corrected config is unconditionally safe.
 */
class RenormalizeDistributorSkusTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_corrects_a_wrong_value_after_a_config_change(): void
    {
        // LA Balloons' actual bug: the old config only stripped the outer "-B",
        // leaving "53023TB" wrongly stored. After adding "TB" to the config, the
        // stale staged value needs correcting, not just filling.
        $distributor = Distributor::factory()->create([
            'slug' => 'la-balloons',
            'config' => ['sku_strip_suffixes' => ['-KL', '-B', '-M', 'TB']],
        ]);
        $product = DistributorProduct::factory()->forDistributor($distributor)->create([
            'raw_sku' => '53023TB-B',
            'normalized_sku' => '53023TB', // stale, from before "TB" was added
        ]);

        $this->artisan('catalog:renormalize-distributor-skus la-balloons --execute')->assertSuccessful();

        $this->assertSame('53023', $product->fresh()->normalized_sku);
    }

    public function test_dry_run_does_not_write(): void
    {
        $distributor = Distributor::factory()->create([
            'slug' => 'la-balloons',
            'config' => ['sku_strip_suffixes' => ['TB']],
        ]);
        $product = DistributorProduct::factory()->forDistributor($distributor)->create([
            'raw_sku' => '53023TB',
            'normalized_sku' => '53023TB',
        ]);

        $this->artisan('catalog:renormalize-distributor-skus la-balloons')->assertSuccessful();

        $this->assertSame('53023TB', $product->fresh()->normalized_sku);
    }

    public function test_only_the_named_distributor_is_touched(): void
    {
        $laBalloons = Distributor::factory()->create(['slug' => 'la-balloons', 'config' => ['sku_strip_suffixes' => ['TB']]]);
        $other = Distributor::factory()->create(['slug' => 'other-store', 'config' => []]);

        DistributorProduct::factory()->forDistributor($laBalloons)->create(['raw_sku' => '53023TB', 'normalized_sku' => '53023TB']);
        $otherProduct = DistributorProduct::factory()->forDistributor($other)->create(['raw_sku' => '53023TB', 'normalized_sku' => '53023TB']);

        $this->artisan('catalog:renormalize-distributor-skus la-balloons --execute')->assertSuccessful();

        // Different distributor's config has no "TB" rule, so its own value is
        // correct as-is and must not be touched by a run scoped to la-balloons.
        $this->assertSame('53023TB', $otherProduct->fresh()->normalized_sku);
    }

    public function test_unknown_slug_fails_cleanly(): void
    {
        $this->artisan('catalog:renormalize-distributor-skus nonexistent-slug')
            ->assertFailed();
    }

    public function test_reports_nothing_to_do_when_everything_already_matches(): void
    {
        $distributor = Distributor::factory()->create(['slug' => 'la-balloons', 'config' => ['sku_strip_suffixes' => ['TB']]]);
        DistributorProduct::factory()->forDistributor($distributor)->create(['raw_sku' => '53023TB', 'normalized_sku' => '53023']);

        $this->artisan('catalog:renormalize-distributor-skus la-balloons --execute')
            ->expectsOutputToContain('Nothing to do')
            ->assertSuccessful();
    }
}
