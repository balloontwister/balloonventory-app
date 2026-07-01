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

    private array $bargainConfig = [
        'extraction' => [
            'attribute_list' => ['section_marker' => 'Additional Product Details'],
            'required_labels' => ['Manufacturer Color', 'Latex Finish', 'Package Count'],
            'min_rows' => 4,
        ],
    ];

    /** Mirrors BargainBalloons' real "Additional Product Details" accordion. */
    private function bargainListHtml(): string
    {
        return <<<'HTML'
        <nav><ul><li><span>Home: </span>not a spec</li></ul></nav>
        <details><summary>Additional Product Details</summary>
        <div class="cc-accordion-item__content">
          <ul>
            <li><span>SKU: </span>BL-53005</li>
            <li><span>UPC: </span>030625530057</li>
            <li><span>Size (inches): </span>11.0</li>
            <li><span>Print: </span>Solid Color</li>
            <li><span>Manufacturer Color: </span>Yellow</li>
            <li><span>Packaging Type: </span> Retail Packaged</li>
            <li><span>Package Count: </span>100</li>
            <li><span>Latex Finish: </span>Fashion</li>
          </ul>
        </div></details>
        <footer><ul><li><span>Contact: </span>also not a spec</li></ul></footer>
        HTML;
    }

    public function test_extracts_a_list_style_spec_accordion(): void
    {
        $result = (new ProductAttributeTableExtractor)->extract($this->bargainListHtml(), $this->bargainConfig);

        $this->assertTrue($result['ok']);
        $this->assertSame(['Yellow'], $result['attributes']['Manufacturer Color']);
        $this->assertSame(['Fashion'], $result['attributes']['Latex Finish']);
        $this->assertSame(['100'], $result['attributes']['Package Count']);
        $this->assertSame(['Retail Packaged'], $result['attributes']['Packaging Type']);
        $this->assertSame(['11.0'], $result['attributes']['Size (inches)']);
    }

    public function test_section_marker_excludes_unrelated_list_items(): void
    {
        $result = (new ProductAttributeTableExtractor)->extract($this->bargainListHtml(), $this->bargainConfig);

        // The nav/footer <li><span> entries are outside the marked section.
        $this->assertArrayNotHasKey('Home', $result['attributes']);
        $this->assertArrayNotHasKey('Contact', $result['attributes']);
    }

    private array $jokerConfig = [
        'extraction' => [
            'attribute_rows' => ['section_marker' => 'Product Information'],
            'required_labels' => ['Brand', 'Size', 'Material'],
            'min_rows' => 4,
        ],
    ];

    /** Mirrors Joker Party Supply's body_html "Product Information" table. */
    private function jokerRowsHtml(): string
    {
        return <<<'HTML'
        <table><tbody>
          <tr><td>Ships From</td><td>Warehouse</td></tr>
        </tbody></table>
        <table>
        <thead><tr><th>Product Information</th><th>Details</th></tr></thead>
        <tbody>
          <tr><td>Brand</td><td>Sempertex</td></tr>
          <tr><td>Size</td><td>11 inches</td></tr>
          <tr><td>Material</td><td>Latex</td></tr>
          <tr><td>Color</td><td>Crystal Clear</td></tr>
          <tr><td>UPC</td><td>030625530118</td></tr>
          <tr><td>Vendor ID</td><td>53011</td></tr>
          <tr><td>Quantity</td><td>100 balloons</td></tr>
          <tr><td>Quantity per Box</td><td>10</td></tr>
        </tbody>
        </table>
        <table><tbody>
          <tr><td>Return Policy</td><td>30 days</td></tr>
        </tbody></table>
        HTML;
    }

    public function test_extracts_a_plain_two_column_row_table(): void
    {
        $result = (new ProductAttributeTableExtractor)->extract($this->jokerRowsHtml(), $this->jokerConfig);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['missing_required']);
        $this->assertSame(['Sempertex'], $result['attributes']['Brand']);
        $this->assertSame(['11 inches'], $result['attributes']['Size']);
        $this->assertSame(['Latex'], $result['attributes']['Material']);
        $this->assertSame(['Crystal Clear'], $result['attributes']['Color']);
        $this->assertSame(['030625530118'], $result['attributes']['UPC']);
        $this->assertSame(['100 balloons'], $result['attributes']['Quantity']);
    }

    public function test_row_table_skips_the_thead_header_row(): void
    {
        $result = (new ProductAttributeTableExtractor)->extract($this->jokerRowsHtml(), $this->jokerConfig);

        // The <th>Product Information</th> header is not a <td> pair → never a label.
        $this->assertArrayNotHasKey('Product Information', $result['attributes']);
    }

    public function test_row_table_section_marker_excludes_other_tables(): void
    {
        $result = (new ProductAttributeTableExtractor)->extract($this->jokerRowsHtml(), $this->jokerConfig);

        // Tables before ("Ships From") and after ("Return Policy") the marked one
        // are outside the scanned range.
        $this->assertArrayNotHasKey('Ships From', $result['attributes']);
        $this->assertArrayNotHasKey('Return Policy', $result['attributes']);
    }
}
