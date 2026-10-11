<?php

namespace Database\Factories;

use App\Enums\UserType;
use App\Models\GovEntity;
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
            'type' => UserType::Citizen,
            'national_id' => fake()->unique()->numerify('2##########'),
            'name' => fake()->name(),
            'name_ar' => 'مواطن '.fake()->unique()->numberBetween(1, 99999),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
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

    /**
     * A ministry employee (reviewer) without a national ID login.
     */
    public function employee(?GovEntity $govEntity = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => UserType::Employee,
            'national_id' => null,
            'gov_entity_id' => $govEntity ?? GovEntity::factory(),
        ]);
    }
}
