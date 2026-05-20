<?php

namespace Database\Factories;

use App\Models\Color;
use App\Models\ColorFamily;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Color>
 */
class ColorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'color_family_id' => ColorFamily::factory(),
            'brand_id' => null,
            'material_id' => null,
            'texture_id' => null,
        ];
    }
}
