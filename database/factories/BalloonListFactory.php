<?php

namespace Database\Factories;

use App\Models\BalloonList;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BalloonList>
 */
class BalloonListFactory extends Factory
{
    protected $model = BalloonList::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => fake()->unique()->words(2, true),
            'is_business_favorites' => false,
            'notes' => fake()->optional()->sentence(),
            'created_by_user_id' => User::factory(),
        ];
    }

    public function favorites(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Favorites',
            'is_business_favorites' => true,
            'notes' => null,
        ]);
    }
}
