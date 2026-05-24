<?php

namespace Database\Factories;

use App\Enums\StockDirection;
use App\Models\Business;
use App\Models\Sku;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'sku_id' => Sku::factory(),
            'bin_id' => null,
            'user_id' => User::factory(),
            'direction' => fake()->randomElement([StockDirection::In, StockDirection::Out]),
            'full_bags_change' => fake()->numberBetween(1, 5),
            'open_bags_change' => 0,
            'upc_scanned' => null,
            'job_id' => null,
            'notes' => null,
            'created_at' => fake()->dateTimeBetween('-6 months', 'now'),
        ];
    }

    public function checkIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => StockDirection::In,
        ]);
    }

    public function checkOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => StockDirection::Out,
        ]);
    }

    public function removal(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => StockDirection::Removed,
            'full_bags_change' => 0,
            'open_bags_change' => 0,
        ]);
    }
}
