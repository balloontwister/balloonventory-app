<?php

namespace Database\Factories;

use App\Enums\AdminLevel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            // Factory users have accepted the current terms by default, so they
            // don't trip the acceptance interstitial. Use termsNotAccepted() to
            // model a user who still needs to accept.
            'terms_accepted_at' => now(),
            'terms_version' => config('legal.terms_version'),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'admin_level' => null,
        ];
    }

    /**
     * The user has never accepted the terms (e.g. an invited/magic-link user).
     */
    public function termsNotAccepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'terms_accepted_at' => null,
            'terms_version' => null,
        ]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'admin_level' => AdminLevel::SuperAdmin,
        ]);
    }

    public function siteAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'admin_level' => AdminLevel::SiteAdmin,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
