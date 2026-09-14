<?php

namespace Database\Factories;

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TrustedDevice>
 */
class TrustedDeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'Chrome no Android',
            'token_hash' => hash('sha256', Str::random(64)),
            'last_ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'last_used_at' => now(),
        ];
    }
}
