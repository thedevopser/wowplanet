<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Models\WowCollectionTaxonomy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WowCollectionTaxonomy>
 */
class WowCollectionTaxonomyFactory extends Factory
{
    protected $model = WowCollectionTaxonomy::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity' => fake()->randomElement(CollectionEntity::cases()),
            'entry_id' => fake()->unique()->numberBetween(1, 100000),
            'category' => fake()->randomElement(['Classic', 'Legion', 'The War Within', 'PVP', 'World Events']),
            'source' => fake()->randomElement(['Vendor', 'Drop', 'Quest', 'Achievement', 'Reputation']),
        ];
    }
}
