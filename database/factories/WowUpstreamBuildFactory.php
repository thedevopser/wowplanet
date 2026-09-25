<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WowUpstreamBuild;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WowUpstreamBuild>
 */
class WowUpstreamBuildFactory extends Factory
{
    protected $model = WowUpstreamBuild::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => fake()->unique()->randomElement(['blizzard', 'wago']),
            'build' => sprintf('12.1.0_%d', fake()->numberBetween(60000, 69999)),
            'checked_at' => now(),
            'outcome' => 'ok',
        ];
    }
}
