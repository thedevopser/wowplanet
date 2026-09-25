<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WowImportState;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WowImportState>
 */
class WowImportStateFactory extends Factory
{
    protected $model = WowImportState::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity' => fake()->unique()->randomElement(['quests', 'mounts', 'pets', 'decor', 'professions']),
            'build' => sprintf('12.1.0_%d', fake()->numberBetween(60000, 69999)),
            'last_modified' => null,
            'imported_at' => now(),
        ];
    }
}
