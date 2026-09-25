<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WowProfession;
use App\Models\WowRecipe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WowRecipe>
 */
class WowRecipeFactory extends Factory
{
    protected $model = WowRecipe::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => fake()->unique()->numberBetween(1, 99999),
            'name_fr' => fake()->sentence(3),
            'profession_id' => WowProfession::factory(),
            'expansion_id' => fake()->numberBetween(0, 11),
            'category_name' => fake()->randomElement([
                'Armure',
                'Armes',
                'Potions',
                'Enchantements',
                'Sacs',
            ]),
            'is_active' => true,
        ];
    }
}
