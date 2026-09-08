<?php

namespace Database\Factories;

use App\Models\Decree;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Decree>
 */
class DecreeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numerify('###/2026'),
            'date' => fake()->date('Y-m-d'),
            'url' => fake()->url(),
        ];
    }
}
