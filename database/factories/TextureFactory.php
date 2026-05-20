<?php

namespace Database\Factories;

use App\Models\Texture;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Texture>
 */
class TextureFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'material_id' => null,
            'brand_id' => null,
            'texture_family_id' => null,
        ];
    }
}
