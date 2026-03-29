<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Author>
 */
class AuthorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'         => fake()->unique()->numberBetween(1, 10000),
            'name'            => fake()->name(),
            'email'           => fake()->unique()->safeEmail(),
            'user_created_at' => fake()->dateTimeBetween('-2 years', 'now'),
        ];
    }
}
