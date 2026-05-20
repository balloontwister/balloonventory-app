<?php

namespace Database\Factories;

use App\Models\Color;
use App\Models\ColorTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ColorTranslation>
 */
class ColorTranslationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'color_id' => Color::factory(),
            'locale' => 'en',
            'name' => fake()->word(),
        ];
    }
}
