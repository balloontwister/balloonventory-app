<?php

namespace Tests\Feature;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Color;
use App\Models\ColorFamily;
use App\Models\Distributor;
use App\Models\DistributorCatalogProposal;
use App\Models\Material;
use App\Models\Shape;
use App\Models\Size;
use App\Models\Sku;
use App\Models\Texture;
use App\Services\DistributorCatalogPromoter;
use Database\Seeders\ColorFamilySeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\ShapeSeeder;
use Database\Seeders\SizeSeeder;
use Database\Seeders\TextureFamilySeeder;
use Database\Seeders\TextureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistributorCatalogPromoterTest extends TestCase
{
    use RefreshDatabase;

    private DistributorCatalogPromoter $promoter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MaterialSeeder::class);
        $this->seed(ShapeSeeder::class);
        $this->seed(SizeSeeder::class);
        $this->seed(TextureFamilySeeder::class);
        $this->seed(TextureSeeder::class);
        $this->seed(ColorFamilySeeder::class);

        $this->promoter = app(DistributorCatalogPromoter::class);
    }

    /**
     * Sempertex brand with an "11-inch" balloon size and a "Fashion Red" colour.
     */
    private function seedSempertex(): array
    {
        $brand = Brand::factory()->create(['name' => 'Sempertex', 'abbreviation' => 'SMP']);
        $latex = Material::where('name', 'Latex')->firstOrFail();
        $round = Shape::where('name', 'Round')->firstOrFail();
        $size = Size::firstOrCreate(['name' => '11-inch']);
        $balloonSize = BalloonSize::factory()->create([
            'brand_id' => $brand->id,
            'material_id' => $latex->id,
            'size_id' => $size->id,
            'shape_id' => $round->id,
            'name' => '11-inch',
        ]);
        $texture = Texture::factory()->create(['name' => 'Fashion (SMP)', 'brand_id' => $brand->id]);
        $color = Color::factory()->create([
            'name' => 'Fashion Red',
            'brand_id' => $brand->id,
            'color_family_id' => ColorFamily::firstOrFail()->id,
            'texture_id' => $texture->id,
        ]);

        return [$brand, $balloonSize, $color, $latex];
    }

    private function proposal(array $overrides = []): DistributorCatalogProposal
    {
        return DistributorCatalogProposal::factory()->create(array_merge([
            'upc' => '00030625530125',
            'normalized_sku' => '53012',
            'status' => DistributorCatalogProposal::STATUS_PENDING,
            'confidence' => 'high',
            'proposed_count' => 100,
            'proposed_name' => '11-inch Sempertex Fashion Red 100 count',
            'proposed_warehouse_sku' => '53012',
        ], $overrides));
    }

    public function test_promotes_a_fully_resolvable_proposal_into_a_sku_with_urls(): void
    {
        [$brand, $balloonSize, $color, $latex] = $this->seedSempertex();
        $bargain = Distributor::factory()->shopify()->create(['slug' => 'bargain-balloons']);
        $la = Distributor::factory()->shopify()->create(['slug' => 'laballoons']);

        $proposal = $this->proposal([
            'evidence' => [
                ['distributor_id' => $bargain->id, 'url' => 'https://bargainballoons.com/p/bl-53012', 'raw_upc' => '030625530125', 'price' => 13.97, 'stock' => 0],
                ['distributor_id' => $la->id, 'url' => 'https://laballoons.com/p/53012-b', 'raw_upc' => '030625530125', 'price' => 21.69, 'stock' => 348],
            ],
        ]);

        $sku = $this->promoter->promote($proposal);

        $this->assertNotNull($sku);
        $this->assertSame($brand->id, $sku->brand_id);
        $this->assertSame($balloonSize->id, $sku->balloon_size_id);
        $this->assertSame($color->id, $sku->color_id);
        $this->assertSame($latex->id, $sku->material_id);
        $this->assertSame(100, $sku->default_count_per_bag);
        $this->assertSame('53012', $sku->warehouse_sku);
        $this->assertSame('030625530125', $sku->upc); // catalog form, not padded GTIN-14

        // Proposal resolved.
        $proposal->refresh();
        $this->assertSame(DistributorCatalogProposal::STATUS_AUTO_APPROVED, $proposal->status);
        $this->assertSame($sku->id, $proposal->resulting_sku_id);

        // Both distributor URLs attached, with stock translated to availability.
        $this->assertDatabaseHas('distributor_sku_urls', [
            'distributor_id' => $bargain->id, 'sku_id' => $sku->id, 'in_stock' => false,
        ]);
        $this->assertDatabaseHas('distributor_sku_urls', [
            'distributor_id' => $la->id, 'sku_id' => $sku->id, 'in_stock' => true,
        ]);
    }

    public function test_resolves_size_across_real_world_inch_notations(): void
    {
        $this->seedSempertex(); // balloon size name is "11-inch"
        $bargain = Distributor::factory()->shopify()->create(['slug' => 'bargain-balloons']);

        // The distributor titles never write "11-inch": havinaparty uses 11"S,
        // BargainBalloons uses "11 inch". Normalization must still resolve size.
        $proposal = $this->proposal([
            'proposed_name' => '11"S Red Fashion (100 count)',
            'evidence' => [
                ['distributor_id' => $bargain->id, 'url' => 'https://bargainballoons.com/p/bl-53012', 'raw_upc' => '030625530125', 'title' => '11 inch Latex Balloons Sempertex Fashion Red'],
            ],
        ]);

        $sku = $this->promoter->promote($proposal);

        $this->assertNotNull($sku, 'Expected the proposal to auto-create despite inch-notation differences');
        $this->assertNotNull($sku->balloon_size_id);
    }

    public function test_fuzzy_structured_color_defers_to_the_shade_in_the_title(): void
    {
        [$brand, $balloonSize, , $latex] = $this->seedSempertex();
        $family = ColorFamily::firstOrFail()->id;
        $texture = Texture::factory()->create(['name' => 'Pastel (SMP)', 'brand_id' => $brand->id]);

        // The catalog has no colour literally named "Green"; the distributor's
        // coarse "Green" fuzzy-matches "Neon Green", but the title names the shade.
        $neonGreen = Color::factory()->create(['name' => 'Neon Green', 'brand_id' => $brand->id, 'color_family_id' => $family, 'texture_id' => $texture->id]);
        $pastelGreenTea = Color::factory()->create(['name' => 'Pastel Green Tea', 'brand_id' => $brand->id, 'color_family_id' => $family, 'texture_id' => $texture->id]);

        $distributor = Distributor::factory()->shopify()->create();
        $proposal = $this->proposal([
            'upc' => '00030625999996',
            'proposed_name' => '11 Inch Round Pastel Green Tea Sempertex 100ct',
            'evidence' => [[
                'distributor_id' => $distributor->id,
                'url' => 'https://example.com/p/x',
                'raw_upc' => '030625999996',
                'title' => '11 Inch Round Pastel Green Tea Sempertex 100ct',
                'attributes' => [
                    'Brand' => ['Sempertex'],
                    'Size' => ['11 inch'],
                    'Balloon Type / Shape' => ['Solid Color', 'Round'],
                    'Color' => ['Green'],
                ],
            ]],
        ]);

        $sku = $this->promoter->promote($proposal);

        $this->assertNotNull($sku);
        $this->assertSame($pastelGreenTea->id, $sku->color_id, 'Should use the title shade, not the fuzzy family match');
        $this->assertNotSame($neonGreen->id, $sku->color_id);
        $this->assertSame($balloonSize->id, $sku->balloon_size_id);
        $this->assertSame($latex->id, $sku->material_id);
    }

    /**
     * The recompute path (used by the audit command) must ignore any stored
     * proposed_color_id — it might itself be the corrupted value, so it can't be
     * the baseline the audit compares against. It should reach the same title
     * shade a fresh promotion would.
     */
    public function test_recompute_color_from_evidence_ignores_a_manual_override_and_uses_the_title_shade(): void
    {
        [$brand] = $this->seedSempertex();
        $family = ColorFamily::firstOrFail()->id;
        $texture = Texture::factory()->create(['name' => 'Pastel (SMP)', 'brand_id' => $brand->id]);

        $wrongColor = Color::factory()->create(['name' => 'Neon Green', 'brand_id' => $brand->id, 'color_family_id' => $family, 'texture_id' => $texture->id]);
        $correctShade = Color::factory()->create(['name' => 'Pastel Green Tea', 'brand_id' => $brand->id, 'color_family_id' => $family, 'texture_id' => $texture->id]);

        $distributor = Distributor::factory()->shopify()->create();
        $proposal = $this->proposal([
            'upc' => '00030625999997',
            'proposed_name' => '11 Inch Round Pastel Green Tea Sempertex 100ct',
            // A stale/incidental override — the exact shape of the production bug.
            'proposed_color_id' => $wrongColor->id,
            'evidence' => [[
                'distributor_id' => $distributor->id,
                'title' => '11 Inch Round Pastel Green Tea Sempertex 100ct',
                'attributes' => [
                    'Brand' => ['Sempertex'],
                    'Color' => ['Green'],
                ],
            ]],
        ]);

        $result = $this->promoter->recomputeColorFromEvidence($proposal);

        $this->assertSame($correctShade->id, $result?->id);
    }

    public function test_leaves_unresolvable_proposal_pending(): void
    {
        $this->seedSempertex();

        // Title has no recognizable brand → cannot auto-create.
        $proposal = $this->proposal([
            'proposed_name' => 'mystery wholesale item 100 count',
            'evidence' => [],
        ]);

        $this->assertFalse($this->promoter->canPromote($proposal));
        $this->assertNull($this->promoter->promote($proposal));

        $proposal->refresh();
        $this->assertSame(DistributorCatalogProposal::STATUS_PENDING, $proposal->status);
        $this->assertNull($proposal->resulting_sku_id);
        $this->assertSame(0, Sku::count());
    }

    public function test_does_not_create_when_upc_already_in_catalog(): void
    {
        $this->seedSempertex();
        Sku::factory()->create(['upc' => '030625530125', 'is_active' => true]);
        $skusBefore = Sku::count();

        $proposal = $this->proposal();

        $this->assertFalse($this->promoter->canPromote($proposal));
        $this->assertNull($this->promoter->promote($proposal));
        $this->assertSame($skusBefore, Sku::count());
    }

    public function test_is_idempotent_once_promoted(): void
    {
        $this->seedSempertex();
        $proposal = $this->proposal(['evidence' => []]);

        $first = $this->promoter->promote($proposal);
        $this->assertNotNull($first);

        $countAfterFirst = Sku::count();

        $second = $this->promoter->promote($proposal->fresh());
        $this->assertSame($first->id, $second->id);
        $this->assertSame($countAfterFirst, Sku::count());
    }

    public function test_brand_flag_filters_proposals_in_command(): void
    {
        // Seed Sempertex (brand appears in evidence titles) and Kalisan (does not).
        $this->seedSempertex();
        $kalisanBrand = Brand::factory()->create(['name' => 'Kalisan', 'abbreviation' => 'KAL']);
        $latex = Material::where('name', 'Latex')->firstOrFail();
        $round = Shape::where('name', 'Round')->firstOrFail();
        $size = Size::firstOrCreate(['name' => '11-inch']);
        $balloonSizeK = BalloonSize::factory()->create([
            'brand_id' => $kalisanBrand->id,
            'material_id' => $latex->id,
            'size_id' => $size->id,
            'shape_id' => $round->id,
            'name' => '12-inch (K)',
        ]);
        $textureK = Texture::factory()->create(['name' => 'Macaron (K)', 'brand_id' => $kalisanBrand->id]);
        $colorK = Color::factory()->create([
            'name' => 'Macaron Lilac',
            'brand_id' => $kalisanBrand->id,
            'color_family_id' => ColorFamily::firstOrFail()->id,
            'texture_id' => $textureK->id,
        ]);

        // Sempertex proposal — should be promoted with --brand=sempertex. Two
        // distributors expose agreeing attribute tables so it clears the
        // multi-source accuracy gate and auto-creates.
        $sempertexAttributes = [
            'Brand' => ['Sempertex'],
            'Size' => ['11 inch'],
            'Color' => ['Fashion Red'],
        ];
        $this->proposal([
            'upc' => '00030625530125',
            'proposed_name' => '11-inch Sempertex Fashion Red 100 count',
            'evidence' => [
                ['distributor_id' => Distributor::factory()->shopify()->create()->id,
                    'url' => 'https://example.com/p/1', 'raw_upc' => '030625530125', 'title' => 'Sempertex Fashion Red 11 inch', 'attributes' => $sempertexAttributes],
                ['distributor_id' => Distributor::factory()->bigcommerce()->create()->id,
                    'url' => 'https://other.com/p/1', 'raw_upc' => '030625530125', 'title' => 'Sempertex Fashion Red 11 inch', 'attributes' => $sempertexAttributes],
            ],
        ]);

        // Kalisan proposal — should be skipped when filtering for Sempertex.
        $this->proposal([
            'upc' => '00869329686430',
            'proposed_name' => '12-inch Kalisan Macaron Lilac 50ct',
            'evidence' => [
                ['distributor_id' => Distributor::factory()->shopify()->create()->id,
                    'url' => 'https://example.com/p/2', 'raw_upc' => '869329686430', 'title' => 'Kalisan Macaron Lilac 12 inch'],
            ],
        ]);

        // Run with --brand=sempertex — only the Sempertex proposal should promote.
        $this->artisan('catalog:promote-distributor-proposals', [
            '--execute' => true,
            '--brand' => 'sempertex',
        ])->assertSuccessful();

        $this->assertSame(1, Sku::count());

        $sempertexProposal = DistributorCatalogProposal::where('upc', '00030625530125')->first();
        $this->assertSame(DistributorCatalogProposal::STATUS_AUTO_APPROVED, $sempertexProposal->status);

        $kalisanProposal = DistributorCatalogProposal::where('upc', '00869329686430')->first();
        $this->assertSame(DistributorCatalogProposal::STATUS_PENDING, $kalisanProposal->status);
    }
}
