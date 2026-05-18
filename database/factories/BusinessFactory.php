<?php

namespace Database\Factories;

use App\Enums\BusinessPlan;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 9999),
            'plan' => BusinessPlan::Solo,
        ];
    }

    public function store(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan' => BusinessPlan::Store,
        ]);
    }

    public function enterprise(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan' => BusinessPlan::Enterprise,
        ]);
    }
}
