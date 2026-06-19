<?php

namespace Database\Factories;

use App\Enums\FeedbackStatus;
use App\Models\Business;
use App\Models\Sku;
use App\Models\SkuFeedback;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SkuFeedback>
 */
class SkuFeedbackFactory extends Factory
{
    public function definition(): array
    {
        $sku = Sku::factory();

        return [
            'business_id' => Business::factory(),
            'user_id' => User::factory(),
            'sku_id' => $sku,
            'sku_name' => fn (array $attrs) => Sku::find($attrs['sku_id'])?->name ?? fake()->words(3, true),
            'field' => fake()->randomElement(['name', 'color', 'size', 'barcode', 'other']),
            'current_value' => fake()->words(2, true),
            'suggested_value' => fake()->words(2, true),
            'note' => fake()->optional()->sentence(),
            'status' => FeedbackStatus::Open,
            'resolved_by_user_id' => null,
            'resolved_at' => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FeedbackStatus::Resolved,
            'resolved_by_user_id' => User::factory(),
            'resolved_at' => now(),
        ]);
    }

    public function dismissed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FeedbackStatus::Dismissed,
            'resolved_by_user_id' => User::factory(),
            'resolved_at' => now(),
        ]);
    }
}
