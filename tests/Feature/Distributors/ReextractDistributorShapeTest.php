<?php

namespace Tests\Feature\Distributors;

use App\Models\Distributor;
use App\Models\DistributorProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * catalog:reextract-distributor-shape — the fix-forward tool for a
 * shape_keywords config change: re-derives an already-staged title-extraction
 * distributor's Balloon Type / Shape from its stored title, without a re-crawl.
 */
class ReextractDistributorShapeTest extends TestCase
{
    use RefreshDatabase;

    private function havinAParty(array $shapeKeywords = ['heart' => 'Heart', 'link' => 'Link']): Distributor
    {
        return Distributor::factory()->create([
            'slug' => 'havin-a-party',
            'config' => [
                'extraction' => [
                    'title_attributes' => [
                        'default_shape' => 'Round',
                        'shape_keywords' => $shapeKeywords,
                    ],
                ],
            ],
        ]);
    }

    private function stage(Distributor $d, string $title, string $currentShape): DistributorProduct
    {
        return DistributorProduct::factory()->forDistributor($d)->create([
            'title' => $title,
            'raw_data' => ['attributes' => ['Balloon Type / Shape' => [$currentShape]]],
        ]);
    }

    public function test_execute_corrects_a_wrongly_defaulted_shape(): void
    {
        $d = $this->havinAParty();
        $product = $this->stage($d, '12"K Link Macaron Lilac (50 count)', 'Round');

        $this->artisan('catalog:reextract-distributor-shape havin-a-party --execute')->assertSuccessful();

        $this->assertSame('Link', $product->fresh()->raw_data['attributes']['Balloon Type / Shape'][0]);
    }

    public function test_dry_run_does_not_write(): void
    {
        $d = $this->havinAParty();
        $product = $this->stage($d, '12"K Link Macaron Lilac (50 count)', 'Round');

        $this->artisan('catalog:reextract-distributor-shape havin-a-party')->assertSuccessful();

        $this->assertSame('Round', $product->fresh()->raw_data['attributes']['Balloon Type / Shape'][0]);
    }

    public function test_a_genuinely_round_product_is_left_alone(): void
    {
        $d = $this->havinAParty();
        $product = $this->stage($d, '11"S Fashion Red (100 count)', 'Round');

        $this->artisan('catalog:reextract-distributor-shape havin-a-party --execute')
            ->expectsOutputToContain('Nothing to do')
            ->assertSuccessful();

        $this->assertSame('Round', $product->fresh()->raw_data['attributes']['Balloon Type / Shape'][0]);
    }

    public function test_products_with_no_shape_attribute_are_skipped(): void
    {
        $d = $this->havinAParty();
        // Foil/plastic products carry no shape attribute at all.
        $product = DistributorProduct::factory()->forDistributor($d)->create([
            'title' => 'Script Silver N Air-Fill (5 count)',
            'raw_data' => ['attributes' => ['Balloon Material' => ['Foil']]],
        ]);

        $this->artisan('catalog:reextract-distributor-shape havin-a-party --execute')
            ->expectsOutputToContain('Nothing to do')
            ->assertSuccessful();

        $this->assertArrayNotHasKey('Balloon Type / Shape', $product->fresh()->raw_data['attributes']);
    }

    public function test_unknown_slug_fails_cleanly(): void
    {
        $this->artisan('catalog:reextract-distributor-shape nonexistent-slug')->assertFailed();
    }

    public function test_a_distributor_without_title_extraction_config_fails_cleanly(): void
    {
        $d = Distributor::factory()->create(['slug' => 'larocks', 'config' => []]);

        $this->artisan('catalog:reextract-distributor-shape larocks')->assertFailed();
    }
}
