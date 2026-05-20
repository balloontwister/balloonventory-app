<?php

namespace Database\Factories;

use App\Models\PackagingType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackagingType>
 */
class PackagingTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
        ];
    }
}
