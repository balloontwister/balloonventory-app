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
                    'foil_keywords' => ['air-fill', 'air fill', 'foil', 'mylar', 'orbz', 'sphere'],
                    'latex_brands' => ['Sempertex', 'Kalisan', 'Tuftex', 'Qualatex'],
                    'printed_keywords' => ['happy birthday', 'christmas', 'halloween'],
                    'required_labels' => ['Balloon Material'],
                    'min_rows' => 1,
                ],
            ],
        ];
    }

    private function classify(array $parsed): string
    {
        return $this->classifier->classify($this->extractor->extract($parsed, $this->config()));
    }

    public function test_no_recipe_returns_empty(): void
    {
        $result = $this->extractor->extract(['title' => '11"S Red Fashion (100 count)', 'brand' => 'Sempertex'], []);

        $this->assertFalse($result['has_recipe']);
    }

    public function test_latex_brand_with_count_classifies_solid_latex(): void
    {
        $parsed = ['title' => '11"S Red Fashion (100 count)', 'brand' => 'Sempertex'];
        $result = $this->extractor->extract($parsed, $this->config());

        $this->assertSame(['Sempertex'], $result['attributes']['Brand']);
        $this->assertSame(['Latex'], $result['attributes']['Balloon Material']);
        $this->assertSame(['100'], $result['attributes']['Quantity']);
        $this->assertTrue($result['ok']);
        $this->assertSame(DistributorProductClassifier::SOLID_LATEX, $this->classify($parsed));
    }

    public function test_air_fill_overrides_latex_brand_to_foil(): void
    {
        // A Sempertex-branded foil letter must NOT be mistaken for latex.
        $parsed = ['title' => '14"S Silver Z Air-Fill Pkg (5 count)', 'brand' => 'Sempertex'];
        $result = $this->extractor->extract($parsed, $this->config());

        $this->assertSame(['Foil'], $result['attributes']['Balloon Material']);
        $this->assertSame(DistributorProductClassifier::FOIL, $this->classify($parsed));
    }

    public function test_foil_brand_keyword_classifies_foil(): void
    {
        $parsed = ['title' => 'Script Silver N Air-Fill Pkg (5 count)', 'brand' => 'Betallic'];

        $this->assertSame(DistributorProductClassifier::FOIL, $this->classify($parsed));
    }

    public function test_themed_latex_classifies_printed(): void
    {
        $parsed = ['title' => '18K Happy Birthday Holographic (10 count)', 'brand' => 'Kalisan'];
        $result = $this->extractor->extract($parsed, $this->config());

        $this->assertSame(['Latex'], $result['attributes']['Balloon Material']);
        $this->assertSame(['happy birthday'], $result['attributes']['Occasion / Theme']);
        $this->assertSame(DistributorProductClassifier::PRINTED, $this->classify($parsed));
    }

    public function test_unknown_brand_without_foil_signal_has_no_material(): void
    {
        $parsed = ['title' => 'Mystery Item (3 count)', 'brand' => 'WhoKnows'];
        $result = $this->extractor->extract($parsed, $this->config());

        $this->assertArrayNotHasKey('Balloon Material', $result['attributes']);
        $this->assertFalse($result['ok']); // required Balloon Material missing
    }

    public function test_count_parsed_from_various_forms(): void
    {
        $this->assertSame(['10'], $this->extractor->extract(['title' => '36"S Crystal Clear (10 COUNT)', 'brand' => 'Sempertex'], $this->config())['attributes']['Quantity']);
        $this->assertSame(['50'], $this->extractor->extract(['title' => '160K Mirror Silver Nozzle Up (50 count)', 'brand' => 'Kalisan'], $this->config())['attributes']['Quantity']);
    }
}
