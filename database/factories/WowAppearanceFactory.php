<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WowAppearance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WowAppearance>
 */
class WowAppearanceFactory extends Factory
{
    protected $model = WowAppearance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => fake()->unique()->numberBetween(1, 99999),
            'name_fr' => fake()->words(2, true),
            'slot' => fake()->randomElement(['HEAD', 'SHOULDER', 'CHEST', 'LEGS', 'WEAPON', 'CLOAK']),
            'category' => fake()->randomElement(['Armure', 'Arme']),
            'quality' => fake()->numberBetween(0, 5),
            'item_id' => fake()->numberBetween(1, 200000),
            'is_active' => true,
        ];
    }
}
