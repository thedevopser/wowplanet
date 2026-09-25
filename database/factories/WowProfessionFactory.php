<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WowProfession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WowProfession>
 */
class WowProfessionFactory extends Factory
{
    protected $model = WowProfession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => fake()->unique()->numberBetween(1, 999),
            'name_fr' => fake()->randomElement([
                'Forge',
                'Alchimie',
                'Enchantement',
                'Couture',
                'Ingénierie',
                'Joaillerie',
                'Calligraphie',
                'Travail du cuir',
            ]),
            'type' => 'primary',
            'is_active' => true,
        ];
    }

    public function secondary(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'secondary',
            'name_fr' => fake()->randomElement(['Cuisine', 'Pêche', 'Archéologie']),
        ]);
    }
}
