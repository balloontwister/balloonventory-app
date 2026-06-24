<?php

namespace Database\Factories;

use App\Models\DistributorCatalogProposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DistributorCatalogProposal>
 */
class DistributorCatalogProposalFactory extends Factory
{
    public function definition(): array
    {
        $itemNumber = (string) $this->faker->unique()->numberBetween(50000, 59999);

        return [
            'upc' => $this->faker->unique()->numerify('000306255####'),
            'normalized_sku' => $itemNumber,
            'status' => DistributorCatalogProposal::STATUS_PENDING,
            'confidence' => 'high',
            'proposed_count' => $this->faker->randomElement([50, 100]),
            'proposed_name' => $this->faker->words(4, true),
            'proposed_warehouse_sku' => $itemNumber,
            'evidence' => [],
        ];
    }

    public function autoApproved(): static
    {
        return $this->state(fn () => [
            'status' => DistributorCatalogProposal::STATUS_AUTO_APPROVED,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => DistributorCatalogProposal::STATUS_REJECTED,
            'reviewed_at' => now(),
        ]);
    }
}
