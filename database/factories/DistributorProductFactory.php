<?php

namespace Database\Factories;

use App\Models\Distributor;
use App\Models\DistributorProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DistributorProduct>
 */
class DistributorProductFactory extends Factory
{
    public function definition(): array
    {
        $itemNumber = (string) $this->faker->numberBetween(50000, 59999);

        return [
            'distributor_id' => Distributor::factory(),
            'external_id' => $this->faker->unique()->bothify('var-#######'),
            'raw_sku' => $itemNumber,
            'normalized_sku' => $itemNumber,
            'upc' => $this->faker->numerify('0306255#####'),
            'title' => $this->faker->words(3, true),
            'url' => $this->faker->url(),
            'price' => $this->faker->randomFloat(2, 1, 50),
            'currency' => 'USD',
            'stock' => $this->faker->numberBetween(0, 500),
            'in_stock' => true,
            'raw_data' => null,
            'fetched_at' => now(),
        ];
    }

    public function forDistributor(Distributor $distributor): static
    {
        return $this->state(fn () => ['distributor_id' => $distributor->id]);
    }
}
