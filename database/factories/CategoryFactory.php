<?php

namespace Database\Factories;

use App\Enums\CategoryType;
use App\Enums\RecordStatus;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
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
            'name' => fake()->unique()->word(),
            'type' => fake()->randomElement(CategoryType::cases()),
            'status' => RecordStatus::Active,
        ];
    }
}
