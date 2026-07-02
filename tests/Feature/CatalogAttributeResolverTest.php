<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Color;
use App\Services\CatalogAttributeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogAttributeResolverTest extends TestCase
{
    use RefreshDatabase;

    private CatalogAttributeResolver $resolver;

    private Brand $kalisan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = app(CatalogAttributeResolver::class);
        $this->kalisan = Brand::factory()->create(['name' => 'Kalisan']);
    }

    /**
     * The reported bug: a distributor's structured Color field is a coarse but
     * REAL catalog colour ("Green" genuinely exists), so it resolves 'exact' —
     * which used to short-circuit before the title was ever consulted, even
     * though the title names a distinct, more specific colour ("Mirror Green
     * Gold") that is itself a refinement of the coarse one.
     */
    public function test_a_coarse_exact_match_defers_to_a_more_specific_title_shade(): void
    {
        $green = Color::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'Green']);
        $mirrorGreenGold = Color::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'Mirror Green Gold']);

        $result = $this->resolver->refineColorFromTitle(
            $green,
            'exact',
            '36 inch KALISAN MIRROR GREEN GOLD',
            $this->kalisan,
        );

        $this->assertSame($mirrorGreenGold->id, $result?->id);
    }

    public function test_white_defers_to_pearl_white(): void
    {
        $white = Color::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'White']);
        $pearlWhite = Color::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'Pearl White']);

        $result = $this->resolver->refineColorFromTitle($white, 'exact', 'Kalisan Pearl White 50ct', $this->kalisan);

        $this->assertSame($pearlWhite->id, $result?->id);
    }

    /**
     * The safety half of the fix: an exact match must NOT be overridden by an
     * unrelated title mention — only by a title colour that is a refinement
     * (its name contains the exact match's name).
     */
    public function test_an_exact_match_is_not_overridden_by_an_unrelated_title_colour(): void
    {
        $red = Color::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'Fashion Red']);
        // Present in the title, but NOT a refinement of "Fashion Red" — its name
        // doesn't contain "Fashion Red".
        Color::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'Blue']);

        $result = $this->resolver->refineColorFromTitle(
            $red,
            'exact',
            'Kalisan Fashion Red Balloons, great with our Blue assortment',
            $this->kalisan,
        );

        $this->assertSame($red->id, $result?->id);
    }

    public function test_a_non_exact_match_still_always_defers_to_the_title_as_before(): void
    {
        // A fuzzy structured guess (not exact) — should defer to title regardless
        // of containment, exactly as before this fix.
        $fuzzyGuess = Color::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'Neon Green']);
        $pastelGreenTea = Color::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'Pastel Green Tea']);

        $result = $this->resolver->refineColorFromTitle(
            $fuzzyGuess,
            'fuzzy',
            '11 Inch Round Pastel Green Tea Sempertex 100ct',
            $this->kalisan,
        );

        $this->assertSame($pastelGreenTea->id, $result?->id);
    }

    public function test_returns_the_structured_match_when_the_title_names_nothing(): void
    {
        $green = Color::factory()->create(['brand_id' => $this->kalisan->id, 'name' => 'Green']);

        $result = $this->resolver->refineColorFromTitle($green, 'exact', 'Kalisan Balloons 50ct', $this->kalisan);

        $this->assertSame($green->id, $result?->id);
    }
}
