<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\PriceCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceCode>
 */
class PriceCodeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'code' => fake()->unique()->lexify('??'),
        ];
    }
}
