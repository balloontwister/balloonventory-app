<?php

namespace Tests\Feature\Distributors;

use App\Models\Distributor;
use App\Models\DistributorProduct;
use App\Services\DistributorProductIngestor;
use App\Services\Distributors\DistributorHealthEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DistributorHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluator_grades_extraction_rate(): void
    {
        $e = new DistributorHealthEvaluator;

        $this->assertNull($e->evaluate(9, 9));                              // too small a sample
        $this->assertSame('healthy', $e->evaluate(10, 10)['status']);      // 100%
        $this->assertSame('healthy', $e->evaluate(8, 10)['status']);       // 80%
        $this->assertSame('degraded', $e->evaluate(5, 10)['status']);      // 50%
        $this->assertSame('broken', $e->evaluate(1, 10)['status']);        // 10%
    }

    public function test_failed_extraction_does_not_clobber_existing_good_data(): void
    {
        $distributor = Distributor::factory()->bigcommerce()->create([
            'config' => [
                'extraction' => [
                    'attribute_table' => ['header_class' => 'productView-table-header', 'value_class' => 'productView-table-data'],
                    'required_labels' => ['Brand'],
                    'min_rows' => 3,
                ],
            ],
        ]);

        // Already-good staged row (from a healthy earlier crawl).
        $existing = DistributorProduct::factory()->forDistributor($distributor)->create([
            'external_id' => 'p1',
            'raw_data' => ['attributes' => ['Brand' => ['Kalisan'], 'Size' => ['260']]],
        ]);

        // The site still exposes JSON-LD (so the product parses) but dropped the
        // attribute table (template change) → extraction fails.
        Http::fake(['*' => Http::response(
            '<script type="application/ld+json">{"@type":"Product","name":"X","sku":"123"}</script>'
        )]);

        app(DistributorProductIngestor::class)->crawlBigCommercePage(
            $distributor, 'https://x/p1', 'p1', $distributor->config, execute: true,
        );

        // Good attributes preserved, not overwritten with an empty extraction.
        $this->assertSame(['Kalisan'], $existing->refresh()->raw_data['attributes']['Brand']);
    }
}
