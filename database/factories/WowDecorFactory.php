<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WowDecor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WowDecor>
 */
class WowDecorFactory extends Factory
{
    protected $model = WowDecor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => fake()->unique()->numberBetween(1, 99999),
            'name_fr' => fake()->words(2, true),
            'is_active' => true,
        ];
    }
}
