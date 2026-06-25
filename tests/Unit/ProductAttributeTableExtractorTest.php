<?php

namespace Tests\Unit;

use App\Services\Distributors\ProductAttributeTableExtractor;
use Tests\TestCase;

class ProductAttributeTableExtractorTest extends TestCase
{
    private array $larocksConfig = [
        'extraction' => [
            'attribute_table' => [
                'header_class' => 'productView-table-header',
                'value_class' => 'productView-table-data',
            ],
            'required_labels' => ['Brand', 'Industry'],
            'min_rows' => 4,
        ],
    ];

    private function larocksTableHtml(): string
    {
        return <<<'HTML'
        <h1 class="productView-title">260K Standard Clear Balloons Kalisan 100ct</h1>
        <div class="productView-table">
            <div class="productView-table-row">
                <div class="productView-table-header">Brand:</div>
                <div class="productView-table-data">Kalisan</div>
            </div>
            <div class="productView-table-row">
                <div class="productView-table-header">Industry:</div>
                <div class="productView-table-data">Balloons</div>
            </div>
            <div class="productView-table-row">
                <div class="productView-table-header">Balloon Material:</div>
                <div class="productView-table-data">Latex</div>
            </div>
            <div class="productView-table-row">
                <div class="productView-table-header">Size:</div>
                <div class="productView-table-data">260</div>
            </div>
            <div class="productView-table-row">
                <div class="productView-table-header">Balloon Type / Shape:</div>
                <div class="productView-table-data">Solid Color</div>
            </div>
            <div class="productView-table-row">
                <div class="productView-table-header">Quantity:</div>
                <div class="productView-table-data">100 ct</div>
            </div>
            <div class="productView-table-row">
                <div class="productView-table-header">Color:</div>
                <div class="productView-table-data">Clear</div>
            </div>
            <div class="productView-table-row">
                <div class="productView-table-header">Balloon Type / Shape:</div>
                <div class="productView-table-data">Entertainer</div>
            </div>
        </div>
        HTML;
    }

    public function test_extracts_label_value_pairs(): void
    {
        $result = (new ProductAttributeTableExtractor)->extract($this->larocksTableHtml(), $this->larocksConfig);

        $this->assertTrue($result['has_recipe']);
        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['missing_required']);
        $this->assertSame(['Kalisan'], $result['attributes']['Brand']);
        $this->assertSame(['Latex'], $result['attributes']['Balloon Material']);
        $this->assertSame(['260'], $result['attributes']['Size']);
        $this->assertSame(['Clear'], $result['attributes']['Color']);
        $this->assertSame(['100 ct'], $result['attributes']['Quantity']);
    }

    public function test_repeated_labels_are_collected_not_overwritten(): void
    {
        $result = (new ProductAttributeTableExtractor)->extract($this->larocksTableHtml(), $this->larocksConfig);

        $this->assertSame(['Solid Color', 'Entertainer'], $result['attributes']['Balloon Type / Shape']);
        $this->assertSame(8, $result['row_count']);
    }

    public function test_missing_required_labels_mark_the_page_untrusted(): void
    {
        $html = '<div class="productView-table-row"><div class="productView-table-header">Color:</div><div class="productView-table-data">Clear</div></div>';

        $result = (new ProductAttributeTableExtractor)->extract($html, $this->larocksConfig);

        $this->assertFalse($result['ok']);
        $this->assertEqualsCanonicalizing(['Brand', 'Industry'], $result['missing_required']);
    }

    public function test_no_recipe_yields_no_extraction(): void
    {
        $result = (new ProductAttributeTableExtractor)->extract($this->larocksTableHtml(), []);

        $this->assertFalse($result['has_recipe']);
        $this->assertSame([], $result['attributes']);
        $this->assertSame(0, $result['row_count']);
    }

    public function test_page_without_the_table_extracts_nothing(): void
    {
        $result = (new ProductAttributeTableExtractor)->extract('<h1>Creepy Ants</h1><p>A novelty.</p>', $this->larocksConfig);

        $this->assertSame(0, $result['row_count']);
        $this->assertFalse($result['ok']);
    }
}
