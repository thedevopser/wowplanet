<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CharacterFavorite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterFavorite>
 */
class CharacterFavoriteFactory extends Factory
{
    protected $model = CharacterFavorite::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bnet_user_id' => (string) fake()->numberBetween(10000, 99999),
            'realm_slug' => fake()->randomElement(['hyjal', 'dalaran', 'archimonde']),
            'character_name' => strtolower(fake()->firstName()),
            'sort_order' => 0,
        ];
    }
}
