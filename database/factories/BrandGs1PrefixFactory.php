<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\BrandGs1Prefix;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BrandGs1Prefix>
 */
class BrandGs1PrefixFactory extends Factory
{
    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'prefix' => fake()->unique()->numerify('######'),
        ];
    }
}
