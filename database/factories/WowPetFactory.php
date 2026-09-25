<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WowPet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WowPet>
 */
class WowPetFactory extends Factory
{
    protected $model = WowPet::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => fake()->unique()->numberBetween(1, 99999),
            'name_fr' => fake()->words(2, true),
            'source' => fake()->randomElement(['Drop', 'Vendeur', 'Quête', 'Haut-fait', null]),
            'is_active' => true,
        ];
    }
}
