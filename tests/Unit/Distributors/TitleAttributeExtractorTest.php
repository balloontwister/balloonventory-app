<?php

namespace Tests\Unit\Distributors;

use App\Services\Distributors\DistributorProductClassifier;
use App\Services\Distributors\TitleAttributeExtractor;
use PHPUnit\Framework\TestCase;

class TitleAttributeExtractorTest extends TestCase
{
    private TitleAttributeExtractor $extractor;

    private DistributorProductClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new TitleAttributeExtractor;
        $this->classifier = new DistributorProductClassifier;
    }

    private function config(): array
    {
        return [
            'extraction' => [
                'title_attributes' => [
                    'category_material_map' => [
                        'Latex Balloons' => 'Latex',
                        'Foil Balloons' => 'Foil',
                        'Mylar' => 'Foil',
                        'Bubble' => 'Plastic',
                    ],
                    'printed_categories' => ['Printed', 'Special Occassion', 'Shop by Prints'],
                    'foil_keywords' => ['air-fill', 'foil', 'mylar', 'orbz', 'sphere'],
                    'latex_brands' => ['Sempertex', 'Kalisan', 'Tuftex', 'Qualatex'],
                    'printed_keywords' => ['happy birthday', 'christmas'],
                    'required_labels' => ['Balloon Material'],
                    'min_rows' => 1,
                ],
            ],
        ];
    }

    private function extract(array $parsed): array
    {
        return $this->extractor->extract($parsed, $this->config());
    }

    private function classify(array $parsed): string
    {
        return $this->classifier->classify($this->extract($parsed));
    }

    public function test_no_recipe_returns_empty(): void
    {
        $this->assertFalse($this->extractor->extract(['title' => '11"S Red Fashion (100 count)'], [])['has_recipe']);
    }

    public function test_breadcrumb_drives_material_colour_and_size_for_solid_latex(): void
    {
        $parsed = [
            'title' => '11"S Red Fashion (100 count)',
            'brand' => 'Sempertex',
            'categories' => ['Latex Balloons', 'Shop by Brand', 'Sempertex Latex', 'Red Fashion'],
        ];
        $result = $this->extract($parsed);

        $this->assertSame(['Sempertex'], $result['attributes']['Brand']);
        $this->assertSame(['Latex'], $result['attributes']['Balloon Material']);
        $this->assertSame(['Red Fashion'], $result['attributes']['Color']);
        $this->assertSame(['11'], $result['attributes']['Size']);
        $this->assertSame(['100'], $result['attributes']['Quantity']);
        $this->assertArrayNotHasKey('Occasion / Theme', $result['attributes']);
        $this->assertSame(DistributorProductClassifier::SOLID_LATEX, $this->classify($parsed));
    }

    public function test_breadcrumb_foil_category_classifies_foil(): void
    {
        $parsed = [
            'title' => 'Script Silver N Air-Fill Pkg (5 count)',
            'brand' => 'Betallic',
            'categories' => ['Foil Balloons'],
        ];
        $result = $this->extract($parsed);

        $this->assertSame(['Foil'], $result['attributes']['Balloon Material']);
        // No colour emitted for foil (leaf is not a solid-latex colour node).
        $this->assertArrayNotHasKey('Color', $result['attributes']);
        $this->assertSame(DistributorProductClassifier::FOIL, $this->classify($parsed));
    }

    public function test_breadcrumb_overrides_a_latex_brand_when_product_is_foil(): void
    {
        // A Kaleidoscope/Kalisan-ish printed FOIL: breadcrumb says Foil, so it must
        // not be mistaken for latex even though the title lacks a foil keyword.
        $parsed = [
            'title' => '18"C Happy Birthday Bruches Holographic (10 count)',
            'brand' => 'Kaleidoscope',
            'categories' => ['Foil Balloons', 'Special Occassion Foil', 'Happy Birthday'],
        ];

        $this->assertSame(DistributorProductClassifier::FOIL, $this->classify($parsed));
    }

    public function test_printed_latex_via_breadcrumb_prints_category(): void
    {
        $parsed = [
            'title' => '36 Qualatex Printed Round Latex Birthday (25 count)',
            'brand' => 'Qualatex',
            'categories' => ['Latex Balloons', 'Shop by Prints', 'Birthday'],
        ];
        $result = $this->extract($parsed);

        $this->assertSame(['Latex'], $result['attributes']['Balloon Material']);
        $this->assertArrayHasKey('Occasion / Theme', $result['attributes']);
        $this->assertArrayNotHasKey('Color', $result['attributes']); // themed → not a solid colour
        $this->assertSame(DistributorProductClassifier::PRINTED, $this->classify($parsed));
    }

    public function test_falls_back_to_title_keywords_without_a_breadcrumb(): void
    {
        // No categories → title air-fill keyword still flags foil.
        $foil = ['title' => '14"S Silver Z Air-Fill Pkg (5 count)', 'brand' => 'Sempertex'];
        $this->assertSame(DistributorProductClassifier::FOIL, $this->classify($foil));

        // Latex brand, no foil signal → latex.
        $latex = ['title' => '24K Fuchsia Standard (2 count)', 'brand' => 'Kalisan'];
        $this->assertSame(DistributorProductClassifier::SOLID_LATEX, $this->classify($latex));
    }

    public function test_unknown_brand_without_signal_has_no_material(): void
    {
        $result = $this->extract(['title' => 'Mystery Item (3 count)', 'brand' => 'WhoKnows']);

        $this->assertArrayNotHasKey('Balloon Material', $result['attributes']);
        $this->assertFalse($result['ok']);
    }
}
