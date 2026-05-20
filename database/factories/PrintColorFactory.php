<?php

namespace Database\Factories;

use App\Models\PrintColor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrintColor>
 */
class PrintColorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
        ];
    }
}
