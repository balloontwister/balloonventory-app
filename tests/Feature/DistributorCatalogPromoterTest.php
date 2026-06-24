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
}
