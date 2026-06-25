<?php

namespace Tests\Feature\Distributors;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Color;
use App\Services\Distributors\DistributorAttributeMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistributorAttributeMatcherTest extends TestCase
{
    use RefreshDatabase;

    private DistributorAttributeMatcher $matcher;

    private Brand $kalisan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->matcher = app(DistributorAttributeMatcher::class);
        $this->kalisan = Brand::factory()->create(['name' => 'Kalisan']);
    }

    public function test_matches_brand_size_color_count_from_structured_attributes(): void
    {
        $size = BalloonSize::factory()->create(['brand_id' => $this->kalisan->id, 'name' => '260K']);
        $color = Color::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'Clear Transparent']);

        $result = $this->matcher->match([
            'Brand' => ['Kalisan'],
            'Size' => ['260'],           // bare number → our "260K"
            'Color' => ['Clear'],        // fuzzy → "Clear Transparent"
            'Quantity' => ['100 ct'],
        ]);

        $this->assertSame($this->kalisan->id, $result['brand']['model']->id);
        $this->assertSame('exact', $result['brand']['quality']);

        $this->assertSame($size->id, $result['balloon_size']['model']->id);
        $this->assertSame('exact', $result['balloon_size']['quality']);

        $this->assertSame($color->id, $result['color']['model']->id);
        $this->assertSame('fuzzy', $result['color']['quality']);

        $this->assertSame(100, $result['count']);
    }

    public function test_alias_resolves_a_non_obvious_color(): void
    {
        $color = Color::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'Clear Transparent']);

        $result = $this->matcher->match(
            ['Brand' => ['Kalisan'], 'Color' => ['Standard Clear']],
            ['color' => ['Standard Clear' => 'Clear Transparent']],
        );

        $this->assertSame($color->id, $result['color']['model']->id);
        $this->assertSame('exact', $result['color']['quality']);
    }

    public function test_ambiguous_size_returns_candidates_and_fuzzy_quality(): void
    {
        // Two sizes whose names share the same core key (the parenthetical brand
        // suffix is stripped during matching), so "11 inch" is genuinely ambiguous.
        $round = BalloonSize::factory()->create(['brand_id' => $this->kalisan->id, 'name' => '11-inch (Q)']);
        $heart = BalloonSize::factory()->create(['brand_id' => $this->kalisan->id, 'name' => '11-inch (H)']);

        $result = $this->matcher->match(['Brand' => ['Kalisan'], 'Size' => ['11 inch']]);

        $this->assertSame('fuzzy', $result['balloon_size']['quality']);
        $candidateIds = collect($result['balloon_size']['candidates'])->pluck('id');
        $this->assertTrue($candidateIds->contains($round->id));
        $this->assertTrue($candidateIds->contains($heart->id));
    }

    public function test_unmatched_brand_yields_no_scoped_matches(): void
    {
        $result = $this->matcher->match(['Brand' => ['Nonexistent'], 'Size' => ['260'], 'Color' => ['Clear']]);

        $this->assertNull($result['brand']['model']);
        $this->assertSame('none', $result['brand']['quality']);
        $this->assertNull($result['balloon_size']['model']);
        $this->assertNull($result['color']['model']);
    }
}
