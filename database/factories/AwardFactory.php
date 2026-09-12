<?php

namespace Database\Factories;

use App\Models\Award;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Award>
 */
class AwardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'Орден "За мужність" III ступеня',
                'Орден Богдана Хмельницького III ступеня',
                'Відзнака Президента України "Хрест бойових заслуг"',
            ]).' '.fake()->unique()->numerify('###'),
        ];
    }
}
