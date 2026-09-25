<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WowAchievement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WowAchievement>
 */
class WowAchievementFactory extends Factory
{
    protected $model = WowAchievement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => fake()->unique()->numberBetween(1, 99999),
            'name_fr' => fake()->sentence(3),
            'expansion_id' => fake()->numberBetween(0, 11),
            'category_name' => fake()->randomElement([
                'Général',
                'Quêtes',
                'Exploration',
                'Joueur contre joueur',
                'Donjons et raids',
            ]),
            'is_active' => true,
        ];
    }
}
