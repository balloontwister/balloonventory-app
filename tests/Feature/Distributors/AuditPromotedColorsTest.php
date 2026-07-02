<?php

namespace Tests\Feature\Distributors;

use App\Models\Brand;
use App\Models\Color;
use App\Models\ColorFamily;
use App\Models\Distributor;
use App\Models\DistributorCatalogProposal;
use App\Models\Sku;
use App\Models\Texture;
use Database\Seeders\ColorFamilySeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\ShapeSeeder;
use Database\Seeders\SizeSeeder;
use Database\Seeders\TextureFamilySeeder;
use Database\Seeders\TextureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * catalog:audit-promoted-colors — the live-catalog repair tool for the "edit form
 * silently learns the wrong colour" bug: a promoted SKU whose colour disagrees
 * with a fresh, alias-free re-resolution of its own evidence.
 */
class AuditPromotedColorsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MaterialSeeder::class);
        $this->seed(ShapeSeeder::class);
        $this->seed(SizeSeeder::class);
        $this->seed(TextureFamilySeeder::class);
        $this->seed(TextureSeeder::class);
        $this->seed(ColorFamilySeeder::class);
    }

    /**
     * @return array{0: Brand, 1: Color, 2: Color, 3: Distributor}
     */
    private function mismatchedSetup(): array
    {
        $brand = Brand::factory()->create(['name' => 'Sempertex']);
        $family = ColorFamily::firstOrFail()->id;
        $texture = Texture::factory()->create(['name' => 'Pastel (SMP)', 'brand_id' => $brand->id]);

        $wrongColor = Color::factory()->create(['name' => 'Pearl Blue', 'brand_id' => $brand->id, 'color_family_id' => $family, 'texture_id' => $texture->id]);
        $correctShade = Color::factory()->create(['name' => 'Pastel Matte Yellow', 'brand_id' => $brand->id, 'color_family_id' => $family, 'texture_id' => $texture->id]);
        $distributor = Distributor::factory()->shopify()->create();

        return [$brand, $wrongColor, $correctShade, $distributor];
    }

    private function promotedProposal(Distributor $distributor, Color $currentSkuColor): Sku
    {
        $sku = Sku::factory()->create(['color_id' => $currentSkuColor->id]);

        DistributorCatalogProposal::factory()->create([
            'status' => DistributorCatalogProposal::STATUS_AUTO_APPROVED,
            'resulting_sku_id' => $sku->id,
            'proposed_name' => '5-inch Deluxe Mustard Sempertex',
            'evidence' => [[
                'distributor_id' => $distributor->id,
                'title' => '5-inch Pastel Matte Yellow Sempertex',
                'attributes' => ['Brand' => ['Sempertex'], 'Color' => ['Mustard']],
            ]],
        ]);

        return $sku;
    }

    public function test_execute_corrects_a_confidently_mismatched_sku(): void
    {
        [, $wrongColor, $correctShade, $distributor] = $this->mismatchedSetup();
        $sku = $this->promotedProposal($distributor, $wrongColor);

        $this->artisan('catalog:audit-promoted-colors --execute')->assertSuccessful();

        $this->assertSame($correctShade->id, $sku->fresh()->color_id);
    }

    public function test_dry_run_reports_but_does_not_write(): void
    {
        [, $wrongColor, , $distributor] = $this->mismatchedSetup();
        $sku = $this->promotedProposal($distributor, $wrongColor);

        $this->artisan('catalog:audit-promoted-colors')->assertSuccessful();

        $this->assertSame($wrongColor->id, $sku->fresh()->color_id);
    }

    public function test_a_matching_sku_is_not_reported(): void
    {
        [, , $correctShade, $distributor] = $this->mismatchedSetup();
        $sku = $this->promotedProposal($distributor, $correctShade);

        $this->artisan('catalog:audit-promoted-colors --execute')
            ->expectsOutputToContain('Nothing to do')
            ->assertSuccessful();

        $this->assertSame($correctShade->id, $sku->fresh()->color_id);
    }

    /**
     * A low-confidence disagreement (no clear title shade, just a fuzzy structured
     * guess that happens to differ) must never be auto-applied.
     */
    public function test_a_low_confidence_disagreement_is_reported_but_not_applied(): void
    {
        $brand = Brand::factory()->create(['name' => 'Sempertex']);
        $family = ColorFamily::firstOrFail()->id;
        $texture = Texture::factory()->create(['name' => 'Pastel (SMP)', 'brand_id' => $brand->id]);

        $currentColor = Color::factory()->create(['name' => 'Fashion Navy', 'brand_id' => $brand->id, 'color_family_id' => $family, 'texture_id' => $texture->id]);
        // A decoy the fuzzy "contains" match would pick for bare "Blue" — no title
        // names anything more specific, so this is a low-confidence guess.
        $decoyBlue = Color::factory()->create(['name' => 'Blue Bell', 'brand_id' => $brand->id, 'color_family_id' => $family, 'texture_id' => $texture->id]);
        $distributor = Distributor::factory()->shopify()->create();

        $sku = Sku::factory()->create(['color_id' => $currentColor->id]);
        DistributorCatalogProposal::factory()->create([
            'status' => DistributorCatalogProposal::STATUS_AUTO_APPROVED,
            'resulting_sku_id' => $sku->id,
            'proposed_name' => 'Balloon 12345',
            'evidence' => [[
                'distributor_id' => $distributor->id,
                'title' => 'Balloon 12345',
                'attributes' => ['Brand' => ['Sempertex'], 'Color' => ['Blue']],
            ]],
        ]);

        $this->artisan('catalog:audit-promoted-colors --execute')->assertSuccessful();

        // Structured "Blue" fuzzy-matches "Blue Bell" (differs from current Fashion
        // Navy) but no title shade confirms it — must be left untouched.
        $this->assertSame($currentColor->id, $sku->fresh()->color_id);
    }
}
