<?php

namespace Database\Factories;

use App\Models\Award;
use App\Models\Awardee;
use App\Models\Decree;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Awardee>
 */
class AwardeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'decree_id' => Decree::factory(),
            'award_id' => Award::factory(),
            'full_name' => fake()->name(),
            'rank' => fake()->randomElement([
                'солдат',
                'старший солдат',
                'молодший сержант',
                'сержант',
                'старший сержант',
                'лейтенант',
                'капітан',
            ]),
        ];
    }
}
