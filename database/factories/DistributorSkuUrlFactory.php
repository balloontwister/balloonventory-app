<?php

namespace Database\Factories;

use App\Models\Distributor;
use App\Models\DistributorSkuUrl;
use App\Models\Sku;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DistributorSkuUrl>
 */
class DistributorSkuUrlFactory extends Factory
{
    public function definition(): array
    {
        return [
            'distributor_id' => Distributor::factory(),
            'sku_id' => Sku::factory(),
            'url' => fake()->url(),
            'last_checked_at' => now(),
        ];
    }

    public function withPrice(float $price, string $currency = 'USD'): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => $price,
            'currency' => $currency,
        ]);
    }

    public function inStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'in_stock' => true,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'in_stock' => false,
        ]);
    }
}
