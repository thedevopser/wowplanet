<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WowQuest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WowQuest>
 */
class WowQuestFactory extends Factory
{
    protected $model = WowQuest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => fake()->unique()->numberBetween(1, 99999),
            'name_fr' => fake()->sentence(3),
            'expansion_id' => fake()->numberBetween(0, 11),
            'zone_name' => fake()->randomElement([
                'Forêt d\'Elwynn',
                'Durotar',
                'Les Carmines',
                'Nagrand',
                'Vallée des Quatre vents',
            ]),
            'faction' => null,
            'is_active' => true,
        ];
    }
}
