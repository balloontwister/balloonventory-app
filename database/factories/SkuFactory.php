<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Sku;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sku>
 */
class SkuFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'brand_id' => Brand::factory(),
            'is_printed' => false,
            'is_active' => true,
            'upc' => null,
        ];
    }
}
