<?php

namespace Database\Factories;

use App\Models\Bin;
use App\Models\Business;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Bin>
 */
class BinFactory extends Factory
{
    public function definition(): array
    {
        $number = fake()->numberBetween(1, 99);

        return [
            'business_id' => Business::factory(),
            'location_id' => Location::factory(),
            'number' => $number,
            'name' => "Bin #{$number}",
            'description' => fake()->optional()->sentence(),
            'scan_code' => 'BIN-'.strtoupper(Str::random(8)),
            'is_default' => false,
            'sort_order' => 0,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Default',
            'number' => null,
            'is_default' => true,
        ]);
    }
}
