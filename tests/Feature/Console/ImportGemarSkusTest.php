<?php

namespace Tests\Feature\Console;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Color;
use App\Models\ColorFamily;
use App\Models\Material;
use App\Models\Shape;
use App\Models\Size;
use App\Models\Sku;
use App\Models\Texture;
use Database\Seeders\BrandSeeder;
use Database\Seeders\ColorFamilySeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\PackagingTypeSeeder;
use Database\Seeders\ShapeSeeder;
use Database\Seeders\SizeSeeder;
use Database\Seeders\TextureFamilySeeder;
use Database\Seeders\TextureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportGemarSkusTest extends TestCase
{
    use RefreshDatabase;

    private string $jsonPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BrandSeeder::class);
        $this->seed(MaterialSeeder::class);
        $this->seed(ShapeSeeder::class);
        $this->seed(SizeSeeder::class);
        $this->seed(PackagingTypeSeeder::class);
        $this->seed(TextureFamilySeeder::class);
        $this->seed(TextureSeeder::class);
        $this->seed(ColorFamilySeeder::class);

        // Gemar brand is not in BrandSeeder — create it for the test
        Brand::firstOrCreate(
            ['name' => 'Gemar'],
            ['abbreviation' => 'GEM', 'is_active' => true],
        );

        $this->seedGemarReferenceData();

        $this->jsonPath = storage_path('app/test_gemar_normalized.json');
        file_put_contents($this->jsonPath, json_encode($this->fixtureRows(), JSON_PRETTY_PRINT));
    }

    protected function tearDown(): void
    {
        if (file_exists($this->jsonPath)) {
            unlink($this->jsonPath);
        }

        parent::tearDown();
    }

    /**
     * Seed the minimal Gemar reference data needed for FK resolution.
     */
    private function seedGemarReferenceData(): void
    {
        $gemar = Brand::where('name', 'Gemar')->firstOrFail();
        $latex = Material::where('name', 'Latex')->firstOrFail();
        $round = Shape::where('name', 'Round')->firstOrFail();
        $nonRound = Shape::where('name', 'Non-round')->firstOrFail();
        $link = Shape::where('name', 'Link')->firstOrFail();
        $heart = Shape::where('name', 'Heart')->firstOrFail();
        $families = ColorFamily::pluck('id', 'name');

        // Textures
        $standardTex = Texture::firstOrCreate(
            ['name' => 'Standard (G)', 'brand_id' => $gemar->id],
            ['material_id' => $latex->id, 'sort_order' => 1],
        );
        $crystalTex = Texture::firstOrCreate(
            ['name' => 'Crystal (G)', 'brand_id' => $gemar->id],
            ['material_id' => $latex->id, 'sort_order' => 2],
        );
        $neonTex = Texture::firstOrCreate(
            ['name' => 'Neon (G)', 'brand_id' => $gemar->id],
            ['material_id' => $latex->id, 'sort_order' => 3],
        );
        $metallicTex = Texture::firstOrCreate(
            ['name' => 'Metallic (G)', 'brand_id' => $gemar->id],
            ['material_id' => $latex->id, 'sort_order' => 4],
        );
        $shinyTex = Texture::firstOrCreate(
            ['name' => 'Shiny (G)', 'brand_id' => $gemar->id],
            ['material_id' => $latex->id, 'sort_order' => 5],
        );

        // Balloon Sizes
        $sizes = [
            ['5-inch', $round, Size::where('name', '5-inch')->firstOrFail()],
            ['12-inch', $round, Size::where('name', '11-inch')->firstOrFail()],
            ['19-inch', $round, Size::where('name', '18-inch')->firstOrFail()],
            ['31-inch', $round, Size::where('name', '36-inch')->firstOrFail()],
            ['160-G (D2)', $nonRound, Size::where('name', '160')->firstOrFail()],
            ['260-G (D4)', $nonRound, Size::where('name', '260')->firstOrFail()],
            ['350-G (D6)', $nonRound, Size::where('name', '360')->firstOrFail()],
            ['13-inch Link (GL13)', $link, Size::where('name', '11-inch')->firstOrFail()],
            ['12-inch Heart (CR12)', $heart, Size::where('name', '11-inch')->firstOrFail()],
        ];

        foreach ($sizes as [$name, $shape, $sizeFamily]) {
            BalloonSize::firstOrCreate(
                ['name' => $name, 'brand_id' => $gemar->id],
                [
                    'material_id' => $latex->id,
                    'size_id' => $sizeFamily->id,
                    'shape_id' => $shape->id,
                    'sort_order' => 0,
                ],
            );
        }

        // Colors — only the ones referenced by the fixture
        $colors = [
            ['White #001', $standardTex, 'Whites'],
            ['Raspberry Red / Red #005', $standardTex, 'Reds'],
            ['Crystal #000', $crystalTex, 'Clears'],
            ['Yellow #015', $crystalTex, 'Yellows'],
            ['Sky Blue #044', $crystalTex, 'Blues'],
            ['Gold #039', $metallicTex, 'Golds'],
            ['Shiny Gold #088', $shinyTex, 'Golds'],
            ['Green #027', $neonTex, 'Greens'],
        ];

        foreach ($colors as [$name, $texture, $family]) {
            Color::firstOrCreate(
                ['name' => $name, 'brand_id' => $gemar->id],
                [
                    'color_family_id' => $families[$family] ?? null,
                    'material_id' => $latex->id,
                    'texture_id' => $texture->id,
                    'sort_order' => 0,
                ],
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fixtureRows(): array
    {
        return [
            // Standard 5" White, 100ct — EAN-13
            [
                'warehouse_sku' => 'G050110',
                'raw_name' => '5" Gemar Latex Balloons (Bag of 100) Standard White',
                'handle' => '5-inch-gemar-latex-balloons-bag-of-100-standard-white-g050110',
                'source_url' => 'https://bargainballoons.com/products/test',
                'barcode' => '8021886050110',
                'size_resolved' => '5-inch',
                'texture_resolved' => 'Standard (G)',
                'color_resolved' => 'White #001',
                'packaging' => 'Retail',
                'count_per_bag' => 100,
                'is_assortment' => false,
            ],
            // Standard 12" Red, 50ct — same physical balloon as 100ct variant
            [
                'warehouse_sku' => 'G110504',
                'raw_name' => '12" Gemar Latex Balloons (Bag of 50) Standard Red',
                'handle' => '12-inch-gemar-latex-balloons-bag-of-50-standard-red',
                'source_url' => 'https://bargainballoons.com/products/test',
                'barcode' => '8021886110500',
                'size_resolved' => '12-inch',
                'texture_resolved' => 'Standard (G)',
                'color_resolved' => 'Raspberry Red / Red #005',
                'packaging' => 'Retail',
                'count_per_bag' => 50,
                'is_assortment' => false,
            ],
            // Same physical balloon as above, different count → identical_skus
            [
                'warehouse_sku' => 'G110505',
                'raw_name' => '12" Gemar Latex Balloons (Bag of 100) Standard Red',
                'handle' => '12-inch-gemar-latex-balloons-bag-of-100-standard-red',
                'source_url' => 'https://bargainballoons.com/products/test',
                'barcode' => '8021886110517',
                'size_resolved' => '12-inch',
                'texture_resolved' => 'Standard (G)',
                'color_resolved' => 'Raspberry Red / Red #005',
                'packaging' => 'Retail',
                'count_per_bag' => 100,
                'is_assortment' => false,
            ],
            // Crystal clear, twisting 260
            [
                'warehouse_sku' => 'G550000',
                'raw_name' => '260G Gemar Latex Balloons (Bag of 50) Crystal Clear',
                'handle' => '260g-gemar-crystal-clear',
                'source_url' => 'https://bargainballoons.com/products/test',
                'barcode' => '8021886550009',
                'size_resolved' => '260-G (D4)',
                'texture_resolved' => 'Crystal (G)',
                'color_resolved' => 'Crystal #000',
                'packaging' => 'Retail',
                'count_per_bag' => 50,
                'is_assortment' => false,
            ],
            // Crystal Rainbow Yellow, 13-inch Link
            [
                'warehouse_sku' => 'G121506',
                'raw_name' => '13" Gemar Latex Balloons (Bag of 50) Crystal Yellow Link',
                'handle' => '13-inch-gemar-crystal-yellow-link',
                'source_url' => 'https://bargainballoons.com/products/test',
                'barcode' => '8021886121506',
                'size_resolved' => '13-inch Link (GL13)',
                'texture_resolved' => 'Crystal (G)',
                'color_resolved' => 'Yellow #015',
                'packaging' => 'Retail',
                'count_per_bag' => 50,
                'is_assortment' => false,
            ],
            // Metallic Gold, 31"
            [
                'warehouse_sku' => 'G323900',
                'raw_name' => '31" Gemar Latex Balloons (Pack of 1) Metallic Gold',
                'handle' => '31-inch-gemar-metallic-gold',
                'source_url' => 'https://bargainballoons.com/products/test',
                'barcode' => '8021886323900',
                'size_resolved' => '31-inch',
                'texture_resolved' => 'Metallic (G)',
                'color_resolved' => 'Gold #039',
                'packaging' => 'Retail',
                'count_per_bag' => 1,
                'is_assortment' => false,
            ],
        ];
    }

    public function test_dry_run_reports_inserts_without_writing(): void
    {
        $brand = Brand::where('name', 'Gemar')->firstOrFail();
        $this->assertSame(0, Sku::where('brand_id', $brand->id)->count());

        $this->artisan('catalog:import-gemar', ['--path' => $this->jsonPath])
            ->expectsOutputToContain('Inserts: 6')
            ->assertSuccessful();

        $this->assertSame(0, Sku::where('brand_id', $brand->id)->count());
    }

    public function test_execute_writes_all_rows(): void
    {
        $brand = Brand::where('name', 'Gemar')->firstOrFail();

        $this->artisan('catalog:import-gemar', ['--path' => $this->jsonPath, '--execute' => true])
            ->assertSuccessful();

        $this->assertSame(6, Sku::where('brand_id', $brand->id)->count());
    }

    public function test_counts_are_preserved(): void
    {
        $this->artisan('catalog:import-gemar', ['--path' => $this->jsonPath, '--execute' => true])
            ->assertSuccessful();

        $this->assertSame(100, (int) Sku::where('warehouse_sku', 'G050110')->firstOrFail()->default_count_per_bag);
        $this->assertSame(50, (int) Sku::where('warehouse_sku', 'G110504')->firstOrFail()->default_count_per_bag);
        $this->assertSame(1, (int) Sku::where('warehouse_sku', 'G323900')->firstOrFail()->default_count_per_bag);
    }

    public function test_ean13_barcode_is_stored(): void
    {
        $this->artisan('catalog:import-gemar', ['--path' => $this->jsonPath, '--execute' => true])
            ->assertSuccessful();

        $sku = Sku::where('warehouse_sku', 'G050110')->firstOrFail();
        $this->assertSame('8021886050110', $sku->ean);
        $this->assertNull($sku->upc, '13-digit barcode routes to ean, not upc');
    }

    public function test_fk_resolution_round_heart_link_twisting(): void
    {
        $this->artisan('catalog:import-gemar', ['--path' => $this->jsonPath, '--execute' => true])
            ->assertSuccessful();

        // Round
        $round = Sku::where('warehouse_sku', 'G050110')->firstOrFail();
        $this->assertSame('5-inch', $round->balloonSize->name);
        $this->assertSame('Round', $round->balloonSize->shape->name);

        // Twisting (Non-round)
        $twist = Sku::where('warehouse_sku', 'G550000')->firstOrFail();
        $this->assertSame('260-G (D4)', $twist->balloonSize->name);
        $this->assertSame('Non-round', $twist->balloonSize->shape->name);

        // Link
        $link = Sku::where('warehouse_sku', 'G121506')->firstOrFail();
        $this->assertSame('13-inch Link (GL13)', $link->balloonSize->name);
        $this->assertSame('Link', $link->balloonSize->shape->name);
    }

    public function test_identical_skus_pivot_links_packaging_variants(): void
    {
        $this->artisan('catalog:import-gemar', ['--path' => $this->jsonPath, '--execute' => true])
            ->assertSuccessful();

        // G110504 (50ct) and G110505 (100ct) are same color+size → linked
        $a = Sku::where('warehouse_sku', 'G110504')->firstOrFail();
        $b = Sku::where('warehouse_sku', 'G110505')->firstOrFail();

        $this->assertTrue($a->identicalSkus()->where('skus.id', $b->id)->exists(), 'A → B link');
        $this->assertTrue($b->identicalSkus()->where('skus.id', $a->id)->exists(), 'B → A reciprocal link');
    }

    public function test_execute_is_idempotent(): void
    {
        $brand = Brand::where('name', 'Gemar')->firstOrFail();

        $this->artisan('catalog:import-gemar', ['--path' => $this->jsonPath, '--execute' => true])->assertSuccessful();
        $this->artisan('catalog:import-gemar', ['--path' => $this->jsonPath, '--execute' => true])->assertSuccessful();

        $this->assertSame(6, Sku::where('brand_id', $brand->id)->count());

        $a = Sku::where('warehouse_sku', 'G110504')->firstOrFail();
        $this->assertSame(1, $a->identicalSkus()->count(), 'No duplicate pivot rows after second run');
    }

    public function test_unresolved_color_is_warned_and_skipped(): void
    {
        $rows = $this->fixtureRows();
        $rows[] = [
            'warehouse_sku' => 'G999999',
            'raw_name' => '5" Gemar Unicorn Color - 100CT',
            'handle' => '5-inch-gemar-unicorn',
            'source_url' => 'https://bargainballoons.com/products/test',
            'barcode' => '8021886999999',
            'size_resolved' => '5-inch',
            'texture_resolved' => 'Standard (G)',
            'color_resolved' => 'Unicorn Glitter',
            'packaging' => 'Retail',
            'count_per_bag' => 100,
            'is_assortment' => false,
        ];
        file_put_contents($this->jsonPath, json_encode($rows));

        $brand = Brand::where('name', 'Gemar')->firstOrFail();

        $this->artisan('catalog:import-gemar', ['--path' => $this->jsonPath, '--execute' => true])
            ->expectsOutputToContain("Color not found 'Unicorn Glitter'")
            ->expectsConfirmation('There are 1 warnings. Continue anyway?', 'yes')
            ->assertSuccessful();

        $this->assertNull(Sku::where('warehouse_sku', 'G999999')->first());
        $this->assertSame(6, Sku::where('brand_id', $brand->id)->count());
    }

    public function test_fails_cleanly_on_invalid_json(): void
    {
        file_put_contents($this->jsonPath, 'not valid json {');

        $this->artisan('catalog:import-gemar', ['--path' => $this->jsonPath, '--execute' => true])
            ->expectsOutputToContain('empty or invalid JSON')
            ->assertFailed();

        $brand = Brand::where('name', 'Gemar')->firstOrFail();
        $this->assertSame(0, Sku::where('brand_id', $brand->id)->count());
    }

    public function test_invalid_barcode_check_digit_is_rejected(): void
    {
        $rows = $this->fixtureRows();
        // Corrupt the barcode check digit for the first SKU
        $rows[0]['barcode'] = '8021886050111';  // original was 8021886050110
        file_put_contents($this->jsonPath, json_encode($rows));

        $brand = Brand::where('name', 'Gemar')->firstOrFail();

        $this->artisan('catalog:import-gemar', ['--path' => $this->jsonPath, '--execute' => true])
            ->expectsConfirmation('There are 1 warnings. Continue anyway?', 'yes')
            ->assertSuccessful();

        // SKU was inserted but without a barcode
        $sku = Sku::where('warehouse_sku', 'G050110')->firstOrFail();
        $this->assertNull($sku->ean);
        $this->assertNull($sku->upc);
        $this->assertSame(6, Sku::where('brand_id', $brand->id)->count());
    }
}
