<?php

namespace Database\Factories;

use App\Models\Theme;
use App\Models\ThemeTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ThemeTranslation>
 */
class ThemeTranslationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'theme_id' => Theme::factory(),
            'locale' => 'en',
            'name' => fake()->word(),
        ];
    }
}
