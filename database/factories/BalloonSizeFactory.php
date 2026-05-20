<?php

namespace Database\Factories;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Material;
use App\Models\Shape;
use App\Models\Size;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BalloonSize>
 */
class BalloonSizeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'material_id' => Material::factory(),
            'size_id' => Size::factory(),
            'shape_id' => Shape::factory(),
            'name' => fake()->unique()->word(),
        ];
    }
}
