<?php

namespace Database\Factories;

use App\Models\Texture;
use App\Models\TextureTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TextureTranslation>
 */
class TextureTranslationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'texture_id' => Texture::factory(),
            'locale' => 'en',
            'name' => fake()->word(),
        ];
    }
}
