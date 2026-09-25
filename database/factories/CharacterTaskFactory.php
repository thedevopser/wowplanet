<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CharacterTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterTask>
 */
class CharacterTaskFactory extends Factory
{
    protected $model = CharacterTask::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bnet_user_id' => (string) fake()->numberBetween(10000, 99999),
            'realm_slug' => fake()->randomElement(['hyjal', 'dalaran', 'archimonde']),
            'character_name' => strtolower(fake()->firstName()),
            'name' => fake()->randomElement(['Donjon mythique', 'World boss', 'Quête journalière', 'Raid hebdo']),
            'reset_type' => fake()->randomElement(['daily', 'weekly', 'monthly']),
            'is_completed' => false,
            'completed_at' => null,
            'sort_order' => 0,
        ];
    }
}
