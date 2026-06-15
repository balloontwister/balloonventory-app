<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\Shape;
use App\Models\ShapeTranslation;
use App\Models\Size;
use App\Models\Theme;
use Database\Seeders\FoilCatalogSeeder;
use Database\Seeders\MaterialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoilCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MaterialSeeder::class);
    }

    public function test_seeds_foil_shapes_scoped_to_the_foil_material(): void
    {
        $this->seed(FoilCatalogSeeder::class);

        $foil = Material::where('name', 'Foil')->firstOrFail();

        foreach (['Round', 'Square', 'Star', 'Shaped'] as $name) {
            $this->assertDatabaseHas('shapes', [
                'name' => $name,
                'material_id' => $foil->id,
            ]);
        }

        // All six die-cut foil designs share the single generic "Shaped".
        $this->assertSame(
            4,
            Shape::where('material_id', $foil->id)->count(),
            'Exactly the four foil shapes should exist (no per-die-cut shapes).',
        );
    }

    public function test_seeds_the_missing_foil_sizes(): void
    {
        $this->seed(FoilCatalogSeeder::class);

        foreach (['22-inch', '25-inch', '26-inch', '28-inch', '30-inch', '34-inch'] as $name) {
            $this->assertDatabaseHas('sizes', ['name' => $name]);
        }

        $this->assertSame(56, Size::where('name', '22-inch')->value('diameter_cm'));
    }

    public function test_seeds_the_new_occasion_themes(): void
    {
        $this->seed(FoilCatalogSeeder::class);

        $expected = [
            'Birthday', 'Wedding', "Valentine's Day", 'Graduation', 'Communion',
            'Thank You', 'Patriotic', 'Western', 'Sports', 'Aquatic', 'Everyday',
            'Baby Shower', 'Anniversary', 'Congratulations', 'Retirement',
            'Get Well', 'Baptism', 'New Year',
        ];

        foreach ($expected as $name) {
            $this->assertDatabaseHas('themes', ['name' => $name]);
        }
    }

    public function test_seeds_spanish_translations_for_new_rows(): void
    {
        $this->seed(FoilCatalogSeeder::class);

        $square = Shape::where('name', 'Square')->firstOrFail();
        $this->assertDatabaseHas('shape_translations', [
            'shape_id' => $square->id,
            'locale' => 'es',
            'name' => 'Cuadrado',
        ]);

        $wedding = Theme::where('name', 'Wedding')->firstOrFail();
        $this->assertDatabaseHas('theme_translations', [
            'theme_id' => $wedding->id,
            'locale' => 'es',
            'name' => 'Boda',
        ]);
    }

    public function test_is_idempotent_on_repeated_runs(): void
    {
        $this->seed(FoilCatalogSeeder::class);
        $this->seed(FoilCatalogSeeder::class);

        $foil = Material::where('name', 'Foil')->firstOrFail();

        $this->assertSame(4, Shape::where('material_id', $foil->id)->count());
        $this->assertSame(1, Theme::where('name', 'Wedding')->count());
        $this->assertSame(1, Size::where('name', '30-inch')->count());
        $this->assertSame(
            1,
            ShapeTranslation::where('shape_id', Shape::where('name', 'Square')->value('id'))
                ->where('locale', 'es')->count(),
        );
    }

    public function test_does_not_overwrite_a_hand_edited_existing_row(): void
    {
        // A theme curated by hand in production must survive a seeder re-run.
        $birthday = Theme::create(['name' => 'Birthday', 'sort_order' => 999]);

        $this->seed(FoilCatalogSeeder::class);

        $this->assertSame(1, Theme::where('name', 'Birthday')->count());
        $this->assertSame(999, $birthday->fresh()->sort_order, 'firstOrCreate must not overwrite the hand-set sort_order.');
    }
}
