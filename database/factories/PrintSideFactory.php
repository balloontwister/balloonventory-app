<?php

namespace Database\Factories;

use App\Models\PrintSide;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrintSide>
 */
class PrintSideFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
        ];
    }
}
