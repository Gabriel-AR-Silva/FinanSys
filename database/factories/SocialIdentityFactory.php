<?php

namespace Database\Factories;

use App\Enums\SocialProvider;
use App\Models\SocialIdentity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialIdentity>
 */
class SocialIdentityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => SocialProvider::Google,
            'provider_user_id' => fake()->unique()->numerify('#####################'),
            'email' => fake()->unique()->safeEmail(),
            'linked_at' => now(),
        ];
    }
}
