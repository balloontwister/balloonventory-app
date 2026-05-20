<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Color;
use App\Models\ColorFamily;
use App\Models\Texture;
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
            'brand_id' => Brand::factory(),
            'material_id' => null,
            'texture_id' => Texture::factory(),
        ];
    }
}
