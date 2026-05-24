<?php

namespace Database\Factories;

use App\Models\Bin;
use App\Models\Business;
use App\Models\Sku;
use App\Models\StockLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockLevel>
 */
class StockLevelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'sku_id' => Sku::factory(),
            'bin_id' => Bin::factory(),
            'full_bags' => fake()->numberBetween(0, 20),
            'open_bags' => fake()->numberBetween(0, 2),
            'last_movement_at' => fake()->optional()->dateTimeBetween('-6 months', 'now'),
        ];
    }

    public function empty(): static
    {
        return $this->state(fn (array $attributes) => [
            'full_bags' => 0,
            'open_bags' => 0,
        ]);
    }
}
