<?php

namespace Database\Factories;

use App\Models\Distributor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Distributor>
 */
class DistributorFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'platform_type' => fake()->randomElement(['shopify', 'bigcommerce']),
            'base_url' => 'https://'.Str::slug($name).'.com',
            'is_active' => true,
        ];
    }

    public function shopify(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform_type' => 'shopify',
        ]);
    }

    public function bigcommerce(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform_type' => 'bigcommerce',
        ]);
    }

    public function magento(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform_type' => 'magento',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
