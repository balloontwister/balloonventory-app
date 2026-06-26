<?php

namespace Tests\Unit\Distributors;

use App\Services\Distributors\JsonLdAvailabilityParser;
use PHPUnit\Framework\TestCase;

class JsonLdAvailabilityParserTest extends TestCase
{
    private JsonLdAvailabilityParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new JsonLdAvailabilityParser;
    }

    private function page(string $availability): string
    {
        $json = json_encode([
            '@context' => 'http://schema.org',
            '@type' => 'Product',
            'name' => 'Test',
            'offers' => ['@type' => 'Offer', 'availability' => $availability],
        ]);

        return "<html><head><script type=\"application/ld+json\">{$json}</script></head><body></body></html>";
    }

    public function test_in_stock_returns_true(): void
    {
        $this->assertTrue($this->parser->parse($this->page('http://schema.org/InStock')));
    }

    public function test_out_of_stock_returns_false(): void
    {
        $this->assertFalse($this->parser->parse($this->page('http://schema.org/OutOfStock')));
    }

    public function test_sold_out_returns_false(): void
    {
        $this->assertFalse($this->parser->parse($this->page('https://schema.org/SoldOut')));
    }

    public function test_no_json_ld_returns_null(): void
    {
        $this->assertNull($this->parser->parse('<html><body>no structured data</body></html>'));
    }

    public function test_product_without_offers_returns_null(): void
    {
        $json = json_encode(['@type' => 'Product', 'name' => 'Test']);
        $html = "<script type=\"application/ld+json\">{$json}</script>";

        $this->assertNull($this->parser->parse($html));
    }

    public function test_finds_product_inside_graph(): void
    {
        $json = json_encode([
            '@context' => 'http://schema.org',
            '@graph' => [
                ['@type' => 'BreadcrumbList'],
                ['@type' => 'Product', 'offers' => ['availability' => 'http://schema.org/InStock']],
            ],
        ]);
        $html = "<script type=\"application/ld+json\">{$json}</script>";

        $this->assertTrue($this->parser->parse($html));
    }

    public function test_offers_as_a_list_returns_true_when_any_in_stock(): void
    {
        $json = json_encode([
            '@type' => 'Product',
            'offers' => [
                ['@type' => 'Offer', 'availability' => 'http://schema.org/OutOfStock'],
                ['@type' => 'Offer', 'availability' => 'http://schema.org/InStock'],
            ],
        ]);
        $html = "<script type=\"application/ld+json\">{$json}</script>";

        $this->assertTrue($this->parser->parse($html));
    }

    public function test_offers_as_a_list_returns_false_when_all_unavailable(): void
    {
        $json = json_encode([
            '@type' => 'Product',
            'offers' => [
                ['@type' => 'Offer', 'availability' => 'http://schema.org/OutOfStock'],
                ['@type' => 'Offer', 'availability' => 'http://schema.org/SoldOut'],
            ],
        ]);
        $html = "<script type=\"application/ld+json\">{$json}</script>";

        $this->assertFalse($this->parser->parse($html));
    }
}
