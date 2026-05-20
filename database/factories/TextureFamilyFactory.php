<?php

namespace Database\Factories;

use App\Models\TextureFamily;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TextureFamily>
 */
class TextureFamilyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
        ];
    }
}
