<?php

namespace Database\Factories;

use App\Models\Distributor;
use App\Models\DistributorCatalogGap;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DistributorCatalogGap>
 */
class DistributorCatalogGapFactory extends Factory
{
    public function definition(): array
    {
        return [
            'distributor_id' => Distributor::factory(),
            'external_identifier' => fake()->unique()->numerify('SKU-#####'),
            'product_name' => fake()->words(4, true),
            'product_url' => fake()->url(),
        ];
    }
}
