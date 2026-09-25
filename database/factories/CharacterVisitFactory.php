<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CharacterVisit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterVisit>
 */
class CharacterVisitFactory extends Factory
{
    protected $model = CharacterVisit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'realm_slug' => fake()->randomElement(['hyjal', 'dalaran', 'archimonde']),
            'character_name' => strtolower(fake()->firstName()),
            'display_name' => fake()->firstName(),
            'display_realm' => fake()->randomElement(['Hyjal', 'Dalaran', 'Archimonde']),
            'class_name' => fake()->randomElement(['Guerrier', 'Mage', 'Prêtre', 'Voleur', 'Chaman']),
            'level' => 80,
            'last_visited_at' => fake()->dateTimeBetween('-30 days'),
        ];
    }
}
