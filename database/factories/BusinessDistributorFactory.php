<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\BusinessDistributor;
use App\Models\Distributor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessDistributor>
 */
class BusinessDistributorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'distributor_id' => Distributor::factory(),
            'sort_order' => 0,
            'is_enabled' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => false,
        ]);
    }
}
