<?php

namespace Database\Factories;

use App\Models\Shape;
use App\Models\ShapeTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShapeTranslation>
 */
class ShapeTranslationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shape_id' => Shape::factory(),
            'locale' => 'en',
            'name' => fake()->word(),
        ];
    }
}
