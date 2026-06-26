<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Shape;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\ShapeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShapeSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_all_shapes_with_correct_materials(): void
    {
        $this->seed(MaterialSeeder::class);
        $this->seed(ShapeSeeder::class);

        $shapes = Shape::with('material')->get()->keyBy('name');

        $this->assertCount(14, $shapes);

        foreach (['Round', 'Link', 'Non-round', 'Heart', '321-Bee Body', 'Geo', 'Multi-shape', 'Other'] as $name) {
            $this->assertEquals('Latex', $shapes[$name]->material->name, "{$name} should be Latex");
        }

        foreach (['Round Foil', 'Square Foil', 'Circle Foil', 'Star Foil', 'Shaped', 'SuperShape (foil)'] as $name) {
            $this->assertEquals('Foil', $shapes[$name]->material->name, "{$name} should be Foil");
        }
    }

    public function test_skips_if_shapes_already_exist(): void
    {
        $this->seed(MaterialSeeder::class);
        $this->seed(ShapeSeeder::class);
        $this->seed(ShapeSeeder::class);

        $this->assertCount(14, Shape::all());
    }

    public function test_descriptions_are_seeded_for_specialty_shapes(): void
    {
        $this->seed(MaterialSeeder::class);
        $this->seed(ShapeSeeder::class);

        $this->assertNotNull(Shape::where('name', '321-Bee Body')->value('description'));
        $this->assertNotNull(Shape::where('name', 'Geo')->value('description'));
    }
}
