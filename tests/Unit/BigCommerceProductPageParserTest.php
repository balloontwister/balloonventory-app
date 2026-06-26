<?php

namespace Tests\Unit;

use App\Services\DistributorPlatforms\BigCommerceProductPageParser;
use App\Services\DistributorSkuNormalizer;
use PHPUnit\Framework\TestCase;

class BigCommerceProductPageParserTest extends TestCase
{
    private BigCommerceProductPageParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new BigCommerceProductPageParser(new DistributorSkuNormalizer);
    }

    /** havinaparty profile: gated price (absent), numeric stock, no barcode. */
    private function havinapartyHtml(): string
    {
        return <<<'HTML'
        <script type="application/ld+json">{"@type":"BreadcrumbList","itemListElement":[{"name":"Home"},{"name":"Latex Balloons"},{"name":"Shop by Brand"},{"name":"Sempertex Latex"},{"name":"Red Fashion"},{"name":"11\"S Red Fashion (100 count)"}]}</script>
        <script type="application/ld+json">{"@context":"https://schema.org","@type":"Product","name":"11\"S Red Fashion (100 count)","sku":"53012","brand":"Sempertex","offers":{"@type":"Offer","price":"","availability":"https://schema.org/OutOfStock"}}</script>
        <script type="text/javascript">
        var BCData = {"product_attributes":{"sku":"53012","upc":null,"mpn":null,"gtin":null,"price":{"price_range":[],"retail_price_range":[]},"stock":53,"instock":true}};
        </script>
        HTML;
    }

    /** Larocks profile: public price, boolean-only stock, mpn, sku-is-EAN. */
    private function larocksHtml(): string
    {
        return <<<'HTML'
        <script type="application/ld+json">{"@type":"BreadcrumbList"}</script>
        <script type="application/ld+json">{"@type":"Product","name":"12 Inch K-Link Macaron Lilac Balloon Kalisan 50ct","sku":"8693296864306","mpn":"31230032","brand":{"@type":"Brand","name":"Kalisan"},"offers":{"@type":"Offer","price":"7.6","availability":"https://schema.org/InStock"}}</script>
        <script>var BCData = {"product_attributes":{"sku":"8693296864306","upc":null,"mpn":"31230032","gtin":null,"price":{"without_tax":{"value":7.6,"currency":"USD"}},"stock":null,"instock":true}};</script>
        HTML;
    }

    public function test_parses_havinaparty_profile(): void
    {
        $p = $this->parser->parse($this->havinapartyHtml());

        $this->assertSame('53012', $p['raw_sku']);
        $this->assertSame('53012', $p['normalized_sku']);
        $this->assertNull($p['upc']);             // 53012 is not a valid GTIN
        $this->assertSame('11"S Red Fashion (100 count)', $p['title']);
        $this->assertSame('Sempertex', $p['brand']);
        $this->assertNull($p['price']);            // gated → empty
        $this->assertSame(53, $p['stock']);        // numeric stock present
        $this->assertTrue($p['in_stock']);         // from BCData, NOT the wrong JSON-LD availability
        // Breadcrumb categories (Home + product leaf stripped).
        $this->assertSame(['Latex Balloons', 'Shop by Brand', 'Sempertex Latex', 'Red Fashion'], $p['categories']);
    }

    public function test_parses_larocks_profile(): void
    {
        $p = $this->parser->parse($this->larocksHtml());

        $this->assertSame('8693296864306', $p['raw_sku']);
        $this->assertSame('31230032', $p['normalized_sku']);  // prefers mpn
        $this->assertSame('8693296864306', $p['upc']);          // sku is a valid EAN-13
        $this->assertSame('Kalisan', $p['brand']);              // structured brand.name
        $this->assertSame(7.6, $p['price']);                    // public price
        $this->assertNull($p['stock']);                         // no numeric count
        $this->assertTrue($p['in_stock']);
    }

    public function test_does_not_trust_json_ld_out_of_stock_when_bcdata_says_in_stock(): void
    {
        // havinaparty JSON-LD hardcodes OutOfStock; BCData instock:true wins.
        $p = $this->parser->parse($this->havinapartyHtml());
        $this->assertTrue($p['in_stock']);
    }

    public function test_returns_null_when_no_product_data(): void
    {
        $this->assertNull($this->parser->parse('<html><body>not a product</body></html>'));
    }

    public function test_handles_braces_inside_string_values(): void
    {
        // A title containing { } must not break the balanced-brace scan.
        $html = '<script>var BCData = {"product_attributes":{"sku":"99999999","stock":4,"instock":true,"note":"a {curly} title"}};</script>';
        $p = $this->parser->parse($html);

        $this->assertSame('99999999', $p['raw_sku']);
        $this->assertSame(4, $p['stock']);
    }
}
