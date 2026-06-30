<?php

namespace Tests\Feature\Distributors;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Color;
use App\Models\Distributor;
use App\Services\Distributors\DistributorAttributeMatcher;
use App\Services\Distributors\DistributorLearnedAliasStore;
use Database\Seeders\PackagingTypeSeeder;
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
            ['attribute_aliases' => ['color' => ['Standard Clear' => 'Clear Transparent']]],
        );

        $this->assertSame($color->id, $result['color']['model']->id);
        $this->assertSame('exact', $result['color']['quality']);
    }

    public function test_label_map_lets_a_distributor_use_different_field_names(): void
    {
        $size = BalloonSize::factory()->create(['brand_id' => $this->kalisan->id, 'name' => '260K']);

        // This distributor's page labels brand "Manufacturer" and size "Style".
        $result = $this->matcher->match(
            ['Manufacturer' => ['Kalisan'], 'Style' => ['260']],
            ['extraction' => ['label_map' => ['brand' => 'Manufacturer', 'size' => 'Style']]],
        );

        $this->assertSame($this->kalisan->id, $result['brand']['model']->id);
        $this->assertSame($size->id, $result['balloon_size']['model']->id);
    }

    public function test_learned_alias_resolves_a_reversed_word_order_color(): void
    {
        $distributor = Distributor::factory()->create();
        $fashionRed = Color::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'Fashion Red']);
        // The decoy our fuzzy "contains" match would otherwise pick for "Red Fashion".
        Color::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'Red']);

        app(DistributorLearnedAliasStore::class)
            ->record($distributor->id, 'color', $this->kalisan->id, 'Red Fashion', $fashionRed->id, null, null);

        $result = $this->matcher->match(
            ['Brand' => ['Kalisan'], 'Color' => ['Red Fashion']],
            [],
            $distributor->id,
        );

        $this->assertSame($fashionRed->id, $result['color']['model']->id);
        $this->assertSame('exact', $result['color']['quality']);
    }

    public function test_learned_alias_beats_an_exact_match_on_a_family_part(): void
    {
        $distributor = Distributor::factory()->create();
        // "Yellow / Gold" splits and exact-matches our generic "Yellow" — which a
        // learned alias must override when the admin says it's really a shade.
        Color::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'Yellow']);
        $pastelIvory = Color::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'Pastel Ivory']);

        app(DistributorLearnedAliasStore::class)
            ->record($distributor->id, 'color', $this->kalisan->id, 'Yellow / Gold', $pastelIvory->id, null, null);

        $result = $this->matcher->match(
            ['Brand' => ['Kalisan'], 'Color' => ['Yellow / Gold']],
            [],
            $distributor->id,
        );

        $this->assertSame($pastelIvory->id, $result['color']['model']->id);
    }

    public function test_learned_alias_is_scoped_to_its_distributor(): void
    {
        $taught = Distributor::factory()->create();
        $other = Distributor::factory()->create();
        $fashionRed = Color::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'Fashion Red']);
        Color::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'Red']);

        app(DistributorLearnedAliasStore::class)
            ->record($taught->id, 'color', $this->kalisan->id, 'Red Fashion', $fashionRed->id, null, null);

        // A different distributor sending the same raw value has no learned alias,
        // so it falls through to the fuzzy "Red".
        $result = $this->matcher->match(
            ['Brand' => ['Kalisan'], 'Color' => ['Red Fashion']],
            [],
            $other->id,
        );

        $this->assertSame('Red', $result['color']['model']->name);
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

    public function test_slash_combined_value_matches_on_a_part(): void
    {
        BalloonSize::factory()->create(['brand_id' => $this->kalisan->id, 'name' => '360K']);
        $silver = Color::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'Silver']);

        $result = $this->matcher->match([
            'Brand' => ['Kalisan'],
            'Size' => ['350 / 360'],          // we carry 360, not 350
            'Color' => ['Gray / Silver'],     // we carry Silver, not Gray
        ]);

        $this->assertSame('360K', $result['balloon_size']['model']->name);
        $this->assertSame($silver->id, $result['color']['model']->id);
    }

    public function test_matches_packaging_via_alias_and_slash_split(): void
    {
        $this->seed(PackagingTypeSeeder::class);
        $config = ['attribute_aliases' => ['packaging' => [
            'Nozzle-Up' => 'Nozzle Up',
            'Loose Bag (Regular)' => 'Loose',
            'Packaged' => 'Retail',
        ]]];

        // "Q-Pak / Nozzle-Up" splits on "/"; the "Nozzle-Up" part aliases to "Nozzle Up".
        $nozzle = $this->matcher->match(['Brand' => ['Kalisan'], 'Package Type' => ['Q-Pak / Nozzle-Up']], $config);
        $this->assertSame('Nozzle Up', $nozzle['packaging']['model']->name);

        $loose = $this->matcher->match(['Brand' => ['Kalisan'], 'Package Type' => ['Loose Bag (Regular)']], $config);
        $this->assertSame('Loose', $loose['packaging']['model']->name);

        $retail = $this->matcher->match(['Brand' => ['Kalisan'], 'Package Type' => ['Packaged']], $config);
        $this->assertSame('Retail', $retail['packaging']['model']->name);
    }

    public function test_shape_prefixed_size_matches_via_structured_shape(): void
    {
        // Sempertex names sizes "{shape-prefix}-{number}". The distributor sends a
        // bare "24 inch" plus a structured shape, which Tier 1 can't bridge.
        $round = BalloonSize::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'R-24']);
        $heart = BalloonSize::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'C-14 (S)']);

        $r24 = $this->matcher->match([
            'Brand' => ['Kalisan'],
            'Size' => ['24 inch'],
            'Balloon Type / Shape' => ['Solid Color', 'Round'],
        ]);
        $this->assertSame($round->id, $r24['balloon_size']['model']->id);
        $this->assertSame('exact', $r24['balloon_size']['quality']);

        $c14 = $this->matcher->match([
            'Brand' => ['Kalisan'],
            'Size' => ['14 inch'],
            'Balloon Type / Shape' => ['Solid Color', 'Heart'],
        ]);
        $this->assertSame($heart->id, $c14['balloon_size']['model']->id);
    }

    public function test_shape_disambiguates_a_shared_size_number(): void
    {
        // Both share the number 12; only the shape tells round from link.
        $round = BalloonSize::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'R-12']);
        $link = BalloonSize::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'LOL-12']);

        $result = $this->matcher->match([
            'Brand' => ['Kalisan'],
            'Size' => ['12 inch'],
            'Balloon Type / Shape' => ['Solid Color', 'Link'],
        ]);

        $this->assertSame($link->id, $result['balloon_size']['model']->id);
        $this->assertNotSame($round->id, $result['balloon_size']['model']->id);
    }

    public function test_shape_prefix_falls_back_to_a_word_in_the_size_value(): void
    {
        // "660 LOL" has no shape field, but the size value itself names the line.
        $lol = BalloonSize::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'LOL-660']);

        $result = $this->matcher->match([
            'Brand' => ['Kalisan'],
            'Size' => ['660 LOL'],
            'Balloon Type / Shape' => ['Entertainer', 'Solid Color'],
        ]);

        $this->assertSame($lol->id, $result['balloon_size']['model']->id);
    }

    public function test_size_number_alias_absorbs_a_brand_marketing_quirk(): void
    {
        // Sempertex sells its code-12 round as "11 inch"; we catalogue it R-12.
        $r12 = BalloonSize::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'R-12']);

        $result = $this->matcher->match(
            [
                'Brand' => ['Kalisan'],
                'Size' => ['11 inch'],
                'Balloon Type / Shape' => ['Solid Color', 'Round'],
            ],
            ['size_number_aliases' => ['Kalisan' => ['11' => '12']]],
        );

        $this->assertSame($r12->id, $result['balloon_size']['model']->id);
        $this->assertSame('exact', $result['balloon_size']['quality']);
    }

    public function test_config_overrides_the_shape_prefix_map(): void
    {
        $size = BalloonSize::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'B-18']);

        $result = $this->matcher->match(
            [
                'Brand' => ['Kalisan'],
                'Size' => ['18 inch'],
                'Balloon Type / Shape' => ['Bubble'],
            ],
            ['size_shape_prefixes' => ['Bubble' => 'B']],
        );

        $this->assertSame($size->id, $result['balloon_size']['model']->id);
    }

    public function test_finish_field_recomposes_the_combined_colour_name(): void
    {
        // Distributor splits colour into base + finish; our catalogue name is combined.
        $fashion = Color::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'Fashion Yellow']);
        Color::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'Neon Yellow']);

        $result = $this->matcher->match([
            'Brand' => ['Kalisan'],
            'Color' => ['Yellow'],
            'Latex Finish' => ['Fashion'],
        ]);

        $this->assertSame($fashion->id, $result['color']['model']->id);
        $this->assertSame('exact', $result['color']['quality']);
    }

    public function test_colour_falls_back_to_base_when_finish_combo_has_no_match(): void
    {
        $yellow = Color::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'Yellow']);

        $result = $this->matcher->match([
            'Brand' => ['Kalisan'],
            'Color' => ['Yellow'],
            'Latex Finish' => ['Fashion'], // no "Fashion Yellow" in catalogue
        ]);

        $this->assertSame($yellow->id, $result['color']['model']->id);
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
