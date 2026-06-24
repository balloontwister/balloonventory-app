<?php

namespace Tests\Feature;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Color;
use App\Models\ColorFamily;
use App\Models\Distributor;
use App\Models\Material;
use App\Models\Shape;
use App\Models\Size;
use App\Models\Sku;
use App\Models\Texture;
use App\Services\DistributorMatcher;
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

class DistributorMatcherTest extends TestCase
{
    use RefreshDatabase;

    private DistributorMatcher $matcher;

    private Distributor $distributor;

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

        $this->matcher = app(DistributorMatcher::class);

        $this->distributor = Distributor::factory()->shopify()->create();
    }

    public function test_empty_products_returns_empty_results(): void
    {
        $result = $this->matcher->match($this->distributor, []);

        $this->assertCount(0, $result['matched']);
        $this->assertCount(0, $result['gaps']);
    }

    public function test_exact_upc_match(): void
    {
        $sku = Sku::factory()->create([
            'upc' => '030625571074',
            'is_active' => true,
        ]);

        $products = [
            [
                'identifier' => '',
                'name' => 'Test Product',
                'url' => 'https://example.com/products/test',
                'barcode' => '030625571074',
                'price' => null,
                'currency' => null,
                'in_stock' => null,
            ],
        ];

        $result = $this->matcher->match($this->distributor, $products);

        $this->assertCount(1, $result['matched']);
        $this->assertCount(0, $result['gaps']);
        $this->assertEquals($sku->id, $result['matched']->first()['sku_id']);
        $this->assertEquals('barcode', $result['matched']->first()['match_reason']);
    }

    public function test_exact_ean_match(): void
    {
        $sku = Sku::factory()->create([
            'ean' => '8936042011048',
            'is_active' => true,
        ]);

        $products = [
            [
                'identifier' => '',
                'name' => 'Test Product',
                'url' => 'https://example.com/products/test',
                'barcode' => '8936042011048',
                'price' => null,
                'currency' => null,
                'in_stock' => null,
            ],
        ];

        $result = $this->matcher->match($this->distributor, $products);

        $this->assertCount(1, $result['matched']);
        $this->assertCount(0, $result['gaps']);
        $this->assertEquals($sku->id, $result['matched']->first()['sku_id']);
    }

    public function test_barcode_matches_across_gtin_formats(): void
    {
        // Stored as a 12-digit UPC-A.
        $sku = Sku::factory()->create([
            'upc' => '012345678905',
            'is_active' => true,
        ]);

        // Distributor lists the same product as a leading-zero EAN-13. Both
        // canonicalize to the same GTIN-14, so the barcode tier must match.
        $products = [
            [
                'identifier' => '',
                'name' => 'Test Product',
                'url' => 'https://example.com/products/test',
                'barcode' => '0012345678905',
                'price' => null,
                'currency' => null,
                'in_stock' => null,
            ],
        ];

        $result = $this->matcher->match($this->distributor, $products);

        $this->assertCount(1, $result['matched']);
        $this->assertCount(0, $result['gaps']);
        $this->assertEquals($sku->id, $result['matched']->first()['sku_id']);
        $this->assertEquals('barcode', $result['matched']->first()['match_reason']);
    }

    public function test_soft_deleted_sku_does_not_leak_into_barcode_index(): void
    {
        // A trashed SKU that has only an EAN — the grouped where() must keep the
        // SoftDeletes scope from being short-circuited by OR precedence.
        $trashed = Sku::factory()->create([
            'ean' => '8936042011048',
            'is_active' => true,
        ]);
        $trashed->delete();

        $products = [
            [
                'identifier' => '',
                'name' => 'Test Product',
                'url' => 'https://example.com/products/test',
                'barcode' => '8936042011048',
                'price' => null,
                'currency' => null,
                'in_stock' => null,
            ],
        ];

        $result = $this->matcher->match($this->distributor, $products);

        // No live SKU owns this barcode, so it's a gap — the trashed one must
        // not be matched.
        $this->assertCount(0, $result['matched']);
        $this->assertCount(1, $result['gaps']);
    }

    public function test_warehouse_sku_match(): void
    {
        $sku = Sku::factory()->create([
            'warehouse_sku' => 'G050110',
            'is_active' => true,
        ]);

        $products = [
            [
                'identifier' => 'G050110',
                'name' => 'Test Product',
                'url' => 'https://example.com/products/test',
                'barcode' => null,
                'price' => null,
                'currency' => null,
                'in_stock' => null,
            ],
        ];

        $result = $this->matcher->match($this->distributor, $products);

        $this->assertCount(1, $result['matched']);
        $this->assertCount(0, $result['gaps']);
        $this->assertEquals($sku->id, $result['matched']->first()['sku_id']);
        $this->assertEquals('warehouse_sku', $result['matched']->first()['match_reason']);
    }

    public function test_barcode_wins_over_warehouse_sku(): void
    {
        // Create two SKUs: one matched by barcode, one by warehouse_sku
        $barcodeSku = Sku::factory()->create([
            'upc' => '012345678905',
            'warehouse_sku' => 'DIFFERENT',
        ]);
        $wsSku = Sku::factory()->create([
            'warehouse_sku' => '012345678905',
        ]);

        // Product has the barcode as digits; also uses those digits as identifier
        $products = [
            [
                'identifier' => '012345678905',
                'name' => 'Test Product',
                'url' => 'https://example.com/products/test',
                'barcode' => '012345678905',
                'price' => null,
                'currency' => null,
                'in_stock' => null,
            ],
        ];

        $result = $this->matcher->match($this->distributor, $products);

        $this->assertCount(1, $result['matched']);
        // Barcode tier should win — matches the UPC SKU
        $this->assertEquals($barcodeSku->id, $result['matched']->first()['sku_id']);
        $this->assertEquals('barcode', $result['matched']->first()['match_reason']);
    }

    public function test_no_match_becomes_gap(): void
    {
        $products = [
            [
                'identifier' => 'UNKNOWN_SKU',
                'name' => 'Unknown Product',
                'url' => 'https://example.com/products/unknown',
                'barcode' => '999999999999',
                'price' => null,
                'currency' => null,
                'in_stock' => null,
            ],
        ];

        $result = $this->matcher->match($this->distributor, $products);

        $this->assertCount(0, $result['matched']);
        $this->assertCount(1, $result['gaps']);
        $this->assertEquals('UNKNOWN_SKU', $result['gaps']->first()['external_identifier']);
    }

    public function test_attribute_based_match(): void
    {
        // Create a brand with balloon sizes and colors
        $brand = Brand::factory()->create(['name' => 'Kalisan', 'abbreviation' => 'KAL']);
        $latex = Material::where('name', 'Latex')->firstOrFail();
        $round = Shape::where('name', 'Round')->firstOrFail();
        $size = Size::where('name', '5-inch')->firstOrFail();
        $balloonSize = BalloonSize::factory()->create([
            'brand_id' => $brand->id,
            'material_id' => $latex->id,
            'size_id' => $size->id,
            'shape_id' => $round->id,
            'name' => '5-inch',
        ]);
        $colorFamily = ColorFamily::firstOrFail();
        $texture = Texture::factory()->create([
            'name' => 'Standard (K)',
            'brand_id' => $brand->id,
        ]);
        $color = Color::factory()->create([
            'name' => 'Plum',
            'brand_id' => $brand->id,
            'color_family_id' => $colorFamily->id,
            'texture_id' => $texture->id,
        ]);
        Sku::factory()->create([
            'brand_id' => $brand->id,
            'balloon_size_id' => $balloonSize->id,
            'color_id' => $color->id,
            'name' => '5-inch Kalisan Latex Standard Plum 50ct',
            'is_active' => true,
        ]);

        $products = [
            [
                'identifier' => '',
                'name' => '5-inches-kalisan-latex-balloons-standard-plum-50-per-bag',
                'url' => 'https://example.com/products/test',
                'barcode' => null,
                'price' => null,
                'currency' => null,
                'in_stock' => null,
            ],
        ];

        $result = $this->matcher->match($this->distributor, $products);

        // Should match via attribute-based matching (brand "kalisan" + size "5-inch" + color "plum")
        $this->assertCount(1, $result['matched']);
        $this->assertEquals('attribute', $result['matched']->first()['match_reason']);
    }

    public function test_pack_count_disambiguates_multiple_variants(): void
    {
        [$brand, $balloonSize, $color] = $this->kalisanFiveInchPlum();

        $fiftyCt = Sku::factory()->create([
            'brand_id' => $brand->id,
            'balloon_size_id' => $balloonSize->id,
            'color_id' => $color->id,
            'default_count_per_bag' => 50,
            'name' => '5-inch Kalisan Plum 50ct',
            'is_active' => true,
        ]);
        $hundredCt = Sku::factory()->create([
            'brand_id' => $brand->id,
            'balloon_size_id' => $balloonSize->id,
            'color_id' => $color->id,
            'default_count_per_bag' => 100,
            'name' => '5-inch Kalisan Plum 100ct',
            'is_active' => true,
        ]);

        $products = [[
            'identifier' => '',
            'name' => '5-inches-kalisan-latex-balloons-standard-plum-100-per-bag',
            'url' => 'https://example.com/products/test',
            'barcode' => null,
            'price' => null,
            'currency' => null,
            'in_stock' => null,
        ]];

        $result = $this->matcher->match($this->distributor, $products);

        $this->assertCount(1, $result['matched']);
        $this->assertEquals($hundredCt->id, $result['matched']->first()['sku_id']);
        $this->assertNotEquals($fiftyCt->id, $result['matched']->first()['sku_id']);
    }

    public function test_ambiguous_variants_without_a_count_become_a_gap(): void
    {
        [$brand, $balloonSize, $color] = $this->kalisanFiveInchPlum();

        // Two variants, identical brand/size/color, and the product name has no
        // pack count to disambiguate — must not guess.
        Sku::factory()->create([
            'brand_id' => $brand->id,
            'balloon_size_id' => $balloonSize->id,
            'color_id' => $color->id,
            'default_count_per_bag' => 50,
            'is_active' => true,
        ]);
        Sku::factory()->create([
            'brand_id' => $brand->id,
            'balloon_size_id' => $balloonSize->id,
            'color_id' => $color->id,
            'default_count_per_bag' => 100,
            'is_active' => true,
        ]);

        $products = [[
            'identifier' => '',
            'name' => '5-inches-kalisan-latex-balloons-standard-plum',
            'url' => 'https://example.com/products/test',
            'barcode' => null,
            'price' => null,
            'currency' => null,
            'in_stock' => null,
        ]];

        $result = $this->matcher->match($this->distributor, $products);

        $this->assertCount(0, $result['matched']);
        $this->assertCount(1, $result['gaps']);
    }

    public function test_size_token_is_not_matched_inside_a_larger_size(): void
    {
        [$brand] = $this->kalisanFiveInchPlum();
        $latex = Material::where('name', 'Latex')->firstOrFail();
        $round = Shape::where('name', 'Round')->firstOrFail();

        // Only a 5-inch balloon size exists for the brand. A 15-inch product
        // must NOT be matched to it (substring of "15-inch" is "5-inch").
        $products = [[
            'identifier' => '',
            'name' => '15-inch-kalisan-latex-balloons-standard-plum-50-per-bag',
            'url' => 'https://example.com/products/test',
            'barcode' => null,
            'price' => null,
            'currency' => null,
            'in_stock' => null,
        ]];

        $result = $this->matcher->match($this->distributor, $products);

        $this->assertCount(0, $result['matched']);
        $this->assertCount(1, $result['gaps']);
    }

    /**
     * Create a Kalisan brand with a 5-inch balloon size and a Plum color.
     *
     * @return array{0: Brand, 1: BalloonSize, 2: Color}
     */
    private function kalisanFiveInchPlum(): array
    {
        $brand = Brand::factory()->create(['name' => 'Kalisan', 'abbreviation' => 'KAL']);
        $latex = Material::where('name', 'Latex')->firstOrFail();
        $round = Shape::where('name', 'Round')->firstOrFail();
        $size = Size::where('name', '5-inch')->firstOrFail();
        $balloonSize = BalloonSize::factory()->create([
            'brand_id' => $brand->id,
            'material_id' => $latex->id,
            'size_id' => $size->id,
            'shape_id' => $round->id,
            'name' => '5-inch',
        ]);
        $colorFamily = ColorFamily::firstOrFail();
        $texture = Texture::factory()->create(['name' => 'Standard (K)', 'brand_id' => $brand->id]);
        $color = Color::factory()->create([
            'name' => 'Plum',
            'brand_id' => $brand->id,
            'color_family_id' => $colorFamily->id,
            'texture_id' => $texture->id,
        ]);

        return [$brand, $balloonSize, $color];
    }

    public function test_brand_and_size_without_color_does_not_match_a_wrong_color_sku(): void
    {
        // Catalog only has a Plum SKU for this brand/size.
        $brand = Brand::factory()->create(['name' => 'Kalisan', 'abbreviation' => 'KAL']);
        $latex = Material::where('name', 'Latex')->firstOrFail();
        $round = Shape::where('name', 'Round')->firstOrFail();
        $size = Size::where('name', '5-inch')->firstOrFail();
        $balloonSize = BalloonSize::factory()->create([
            'brand_id' => $brand->id,
            'material_id' => $latex->id,
            'size_id' => $size->id,
            'shape_id' => $round->id,
            'name' => '5-inch',
        ]);
        $colorFamily = ColorFamily::firstOrFail();
        $texture = Texture::factory()->create(['name' => 'Standard (K)', 'brand_id' => $brand->id]);
        $color = Color::factory()->create([
            'name' => 'Plum',
            'brand_id' => $brand->id,
            'color_family_id' => $colorFamily->id,
            'texture_id' => $texture->id,
        ]);
        Sku::factory()->create([
            'brand_id' => $brand->id,
            'balloon_size_id' => $balloonSize->id,
            'color_id' => $color->id,
            'name' => '5-inch Kalisan Latex Standard Plum 50ct',
            'is_active' => true,
        ]);

        // Distributor product is the SAME brand + size but a colour we don't
        // carry. The old loose tier would have pinned this URL to the Plum SKU
        // (wrong balloon). It must now be recorded as a gap instead.
        $products = [
            [
                'identifier' => '',
                'name' => '5-inches-kalisan-latex-balloons-standard-chartreuse-50-per-bag',
                'url' => 'https://example.com/products/test',
                'barcode' => null,
                'price' => null,
                'currency' => null,
                'in_stock' => null,
            ],
        ];

        $result = $this->matcher->match($this->distributor, $products);

        $this->assertCount(0, $result['matched']);
        $this->assertCount(1, $result['gaps']);
    }
}
