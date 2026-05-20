<?php

namespace Database\Factories;

use App\Models\Shape;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shape>
 */
class ShapeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'material_id' => null,
        ];
    }
}
