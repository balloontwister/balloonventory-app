<?php

namespace Database\Factories;

use App\Models\BusinessInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BusinessInvitation>
 */
class BusinessInvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invited_email' => fake()->safeEmail(),
            'role' => fake()->randomElement(['owner', 'staff', 'guest']),
            'token' => Str::random(64),
            'status' => BusinessInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(14),
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BusinessInvitation::STATUS_ACCEPTED,
            'responded_at' => now(),
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BusinessInvitation::STATUS_DECLINED,
            'responded_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BusinessInvitation::STATUS_PENDING,
            'expires_at' => now()->subDay(),
        ]);
    }
}
