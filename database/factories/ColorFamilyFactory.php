<?php

namespace Database\Factories;

use App\Models\ColorFamily;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ColorFamily>
 */
class ColorFamilyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'material_id' => null,
        ];
    }
}
