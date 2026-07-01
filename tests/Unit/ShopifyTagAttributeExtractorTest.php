<?php

namespace Tests\Unit;

use App\Services\Distributors\ShopifyTagAttributeExtractor;
use Tests\TestCase;

class ShopifyTagAttributeExtractorTest extends TestCase
{
    private array $laConfig = [
        'extraction' => [
            'tag_attributes' => [
                'tag_map' => [
                    'Color_' => 'Color',
                    'Size_' => 'Size',
                    'Packaging_' => 'Package Type',
                    'Theme_' => 'Occasion / Theme',
                ],
                'product_type_map' => ['latex' => 'Latex', 'foil' => 'Foil', 'mylar' => 'Foil'],
                'strip_words' => ['Latex', 'Foil', 'Mylar', 'Bubble'],
                'required_labels' => ['Color', 'Size'],
                'min_rows' => 2,
            ],
        ],
    ];

    /** Mirrors a real LA Balloons latex product from products.json. */
    private function laProduct(): array
    {
        return [
            'vendor' => 'Kalisan',
            'product_type' => 'Latex Balloons',
            'title' => '24 inch KALISAN STANDARD MAGENTA PINK',
            'tags' => ['Color_Pink', 'Packaging_Packaged', 'Size_24" Latex', 'cf-vendor-kalisan'],
        ];
    }

    public function test_extracts_canonical_attributes_from_namespaced_tags(): void
    {
        $result = (new ShopifyTagAttributeExtractor)->extract($this->laProduct(), $this->laConfig);

        $this->assertTrue($result['has_recipe']);
        $this->assertTrue($result['ok']);
        $this->assertSame(['Pink'], $result['attributes']['Color']);
        $this->assertSame(['Packaged'], $result['attributes']['Package Type']);
        // The material word is stripped from the size so the matcher can resolve it.
        $this->assertSame(['24"'], $result['attributes']['Size']);
    }

    public function test_product_type_becomes_balloon_material(): void
    {
        $result = (new ShopifyTagAttributeExtractor)->extract($this->laProduct(), $this->laConfig);

        $this->assertSame(['Latex'], $result['attributes']['Balloon Material']);
    }

    public function test_foil_product_type_maps_to_foil_material(): void
    {
        $product = ['vendor' => 'Betallic', 'product_type' => 'Foil Balloon', 'tags' => ['Color_Teal', 'Size_40" Foil']];

        $result = (new ShopifyTagAttributeExtractor)->extract($product, $this->laConfig);

        $this->assertSame(['Foil'], $result['attributes']['Balloon Material']);
        $this->assertSame(['40"'], $result['attributes']['Size']);
    }

    public function test_theme_tags_collect_into_occasion_theme(): void
    {
        $product = [
            'vendor' => 'Betallic', 'product_type' => 'Latex Balloons',
            'tags' => ['Color_Red', 'Size_11" Latex', 'Theme_Birthday', 'Theme_Animals'],
        ];

        $result = (new ShopifyTagAttributeExtractor)->extract($product, $this->laConfig);

        $this->assertSame(['Birthday', 'Animals'], $result['attributes']['Occasion / Theme']);
    }

    public function test_unmapped_tags_are_ignored(): void
    {
        $result = (new ShopifyTagAttributeExtractor)->extract($this->laProduct(), $this->laConfig);

        // cf-vendor-kalisan and other noise tags don't create attributes.
        $this->assertSame(['Color', 'Package Type', 'Size', 'Balloon Material'], array_keys($result['attributes']));
    }

    public function test_no_recipe_yields_no_extraction(): void
    {
        $result = (new ShopifyTagAttributeExtractor)->extract($this->laProduct(), []);

        $this->assertFalse($result['has_recipe']);
        $this->assertSame([], $result['attributes']);
        $this->assertFalse($result['ok']);
    }

    public function test_missing_required_labels_mark_untrusted(): void
    {
        $product = ['vendor' => 'Kalisan', 'product_type' => 'Latex Balloons', 'tags' => ['Packaging_Packaged']];

        $result = (new ShopifyTagAttributeExtractor)->extract($product, $this->laConfig);

        $this->assertFalse($result['ok']);
        $this->assertEqualsCanonicalizing(['Color', 'Size'], $result['missing_required']);
    }

    public function test_handles_comma_string_tags(): void
    {
        $product = ['vendor' => 'Kalisan', 'product_type' => 'Latex Balloons', 'tags' => 'Color_Pink, Size_24" Latex'];

        $result = (new ShopifyTagAttributeExtractor)->extract($product, $this->laConfig);

        $this->assertSame(['Pink'], $result['attributes']['Color']);
        $this->assertSame(['24"'], $result['attributes']['Size']);
    }

    /**
     * Config mirroring All American Balloons: a "Twisting Balloons" product_type is
     * latex, and a printed product_type is flagged so the classifier parks it.
     */
    private array $aabConfig = [
        'extraction' => [
            'tag_attributes' => [
                'tag_map' => ['Color_' => 'Color', 'Size_' => 'Size', 'Theme_' => 'Occasion / Theme'],
                'product_type_map' => ['latex' => 'Latex', 'twisting' => 'Latex', 'foil' => 'Foil', 'mylar' => 'Foil'],
                'printed_type_keywords' => ['printed'],
                'strip_words' => ['Latex', 'Foil', 'Mylar', 'Bubble'],
                'required_labels' => ['Color', 'Size'],
                'min_rows' => 2,
            ],
        ],
    ];

    public function test_twisting_product_type_maps_to_latex_material(): void
    {
        $product = [
            'vendor' => 'Betallic', 'product_type' => 'Twisting Balloons',
            'tags' => ['Color_Honey Yellow', 'Size_360'],
        ];

        $result = (new ShopifyTagAttributeExtractor)->extract($product, $this->aabConfig);

        $this->assertSame(['Latex'], $result['attributes']['Balloon Material']);
        $this->assertArrayNotHasKey('Print', $result['attributes']);
    }

    public function test_printed_product_type_adds_a_print_row(): void
    {
        $product = [
            'vendor' => 'Betallic', 'product_type' => 'Printed Latex Balloons',
            'tags' => ['Color_Black', 'Size_24 Inch'],
        ];

        $result = (new ShopifyTagAttributeExtractor)->extract($product, $this->aabConfig);

        // Still latex material, but the Print row tells the classifier it's printed.
        $this->assertSame(['Latex'], $result['attributes']['Balloon Material']);
        $this->assertSame(['Printed'], $result['attributes']['Print']);
    }

    public function test_solid_product_type_gets_no_print_row(): void
    {
        $product = [
            'vendor' => 'Betallic', 'product_type' => 'Solid Latex Balloons',
            'tags' => ['Color_Black', 'Size_24 Inch'],
        ];

        $result = (new ShopifyTagAttributeExtractor)->extract($product, $this->aabConfig);

        $this->assertArrayNotHasKey('Print', $result['attributes']);
    }
}
