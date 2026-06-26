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

/**
 * The auto-create accuracy gate: "high confidence" confirms identity (a shared
 * UPC), but a NEW catalog SKU is only auto-created when the resolved attributes
 * are corroborated by a second attribute source and consistent with the barcode's
 * GS1 prefix. Single-source / disagreeing / mismatched proposals stay in the
 * review queue.
 */
class DistributorAccuracyGateTest extends TestCase
{
    use RefreshDatabase;

    private DistributorCatalogPromoter $promoter;

    private Brand $sempertex;

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
        $this->seedSempertex();
    }

    private function seedSempertex(): void
    {
        $brand = Brand::factory()->create(['name' => 'Sempertex', 'abbreviation' => 'SMP']);
        $latex = Material::where('name', 'Latex')->firstOrFail();
        $round = Shape::where('name', 'Round')->firstOrFail();
        $size = Size::firstOrCreate(['name' => '11-inch']);
        BalloonSize::factory()->create([
            'brand_id' => $brand->id,
            'material_id' => $latex->id,
            'size_id' => $size->id,
            'shape_id' => $round->id,
            'name' => '11-inch',
        ]);
        $texture = Texture::factory()->create(['name' => 'Fashion (SMP)', 'brand_id' => $brand->id]);
        $family = ColorFamily::firstOrFail()->id;
        foreach (['Fashion Red', 'Fashion Blue'] as $name) {
            Color::factory()->create([
                'name' => $name, 'brand_id' => $brand->id,
                'color_family_id' => $family, 'texture_id' => $texture->id,
            ]);
        }

        $this->sempertex = $brand;
    }

    /**
     * @param  array<string, array<int, string>>  $attributes
     */
    private function member(string $distributorId, array $attributes, string $rawUpc = '030625530057'): array
    {
        return [
            'distributor_id' => $distributorId,
            'url' => 'https://example.com/p/'.$distributorId,
            'raw_upc' => $rawUpc,
            'title' => 'Sempertex Fashion 11 inch',
            'attributes' => $attributes,
        ];
    }

    private function fashionRed(): array
    {
        return ['Brand' => ['Sempertex'], 'Size' => ['11 inch'], 'Color' => ['Fashion Red']];
    }

    /**
     * @param  array<int, array<string, mixed>>  $evidence
     */
    private function proposal(array $evidence, array $overrides = []): DistributorCatalogProposal
    {
        return DistributorCatalogProposal::factory()->create(array_merge([
            'upc' => '00030625530057',
            'normalized_sku' => '53005',
            'status' => DistributorCatalogProposal::STATUS_PENDING,
            'confidence' => 'high',
            'proposed_count' => 100,
            'proposed_name' => '11 inch Sempertex Fashion Red 100ct',
            'proposed_warehouse_sku' => '53005',
            'evidence' => $evidence,
        ], $overrides));
    }

    public function test_single_attribute_source_does_not_auto_create(): void
    {
        $bb = Distributor::factory()->shopify()->create();
        $proposal = $this->proposal([$this->member($bb->id, $this->fashionRed())]);

        // The attributes resolve fully, but only one source has them → review only.
        $this->assertFalse($this->promoter->canPromote($proposal));
    }

    public function test_two_agreeing_sources_auto_create(): void
    {
        $bb = Distributor::factory()->shopify()->create();
        $larocks = Distributor::factory()->bigcommerce()->create();

        $proposal = $this->proposal([
            $this->member($bb->id, $this->fashionRed()),
            $this->member($larocks->id, $this->fashionRed()),
        ]);

        $this->assertTrue($this->promoter->canPromote($proposal));
    }

    public function test_two_disagreeing_sources_do_not_auto_create(): void
    {
        $bb = Distributor::factory()->shopify()->create();
        $larocks = Distributor::factory()->bigcommerce()->create();

        // Same brand + size, but the colour disagrees → route to review.
        $proposal = $this->proposal([
            $this->member($bb->id, $this->fashionRed()),
            $this->member($larocks->id, ['Brand' => ['Sempertex'], 'Size' => ['11 inch'], 'Color' => ['Fashion Blue']]),
        ]);

        $this->assertFalse($this->promoter->canPromote($proposal));
    }

    public function test_gs1_prefix_brand_mismatch_blocks_auto_create(): void
    {
        $bb = Distributor::factory()->shopify()->create();
        $larocks = Distributor::factory()->bigcommerce()->create();

        // Both sources agree on Sempertex, but the barcode carries Gemar's GS1
        // prefix (802188…) → the resolution is suspect, so don't auto-create.
        $proposal = $this->proposal([
            $this->member($bb->id, $this->fashionRed(), '802188530057'),
            $this->member($larocks->id, $this->fashionRed(), '802188530057'),
        ], ['upc' => '00802188530057']);

        $this->assertFalse($this->promoter->canPromote($proposal));
    }

    public function test_human_approved_single_source_bypasses_the_gate(): void
    {
        $bb = Distributor::factory()->shopify()->create();

        // A reviewer approved it — a single source is fine, they are the gate.
        $proposal = $this->proposal(
            [$this->member($bb->id, $this->fashionRed())],
            ['status' => DistributorCatalogProposal::STATUS_APPROVED, 'reviewed_by' => 'reviewer-1'],
        );

        $this->assertTrue($this->promoter->canPromote($proposal));
    }

    public function test_command_leaves_single_source_pending_but_creates_multi_source(): void
    {
        $bb = Distributor::factory()->shopify()->create();
        $larocks = Distributor::factory()->bigcommerce()->create();

        // Single-source, high confidence → should stay pending.
        $single = $this->proposal(
            [$this->member($bb->id, $this->fashionRed())],
            ['upc' => '00030625530064', 'normalized_sku' => '53006'],
        );

        // Multi-source agreeing → should auto-create.
        $multi = $this->proposal([
            $this->member($bb->id, $this->fashionRed()),
            $this->member($larocks->id, $this->fashionRed()),
        ]);

        $this->artisan('catalog:promote-distributor-proposals', ['--execute' => true])
            ->assertSuccessful();

        $this->assertSame(DistributorCatalogProposal::STATUS_PENDING, $single->refresh()->status);
        $this->assertNull($single->resulting_sku_id);

        $this->assertSame(DistributorCatalogProposal::STATUS_AUTO_APPROVED, $multi->refresh()->status);
        $this->assertNotNull($multi->resulting_sku_id);
    }
}
