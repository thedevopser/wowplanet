<?php

declare(strict_types=1);

namespace App\Infrastructure\Taxonomy;

use App\Models\WowCollectionTaxonomy;

/**
 * Lecture de la taxonomie curée d'une collection, indexée par identifiant Blizzard.
 *
 * La taxonomie la plus volumineuse tient en quelques milliers de lignes de deux libellés :
 * la charger d'un coup coûte moins qu'une requête par entrée pendant l'import.
 */
final readonly class CollectionTaxonomyReader
{
    /**
     * @return array<int, TaxonomyEntry>
     */
    public function for(CollectionEntity $collectionEntity): array
    {
        $taxonomy = [];

        /** @var list<array{entry_id: int, category: string|null, source: string|null, obtainable: bool}> $rows */
        $rows = WowCollectionTaxonomy::query()
            ->where('entity', $collectionEntity)
            ->get(['entry_id', 'category', 'source', 'obtainable'])
            ->map(static fn (WowCollectionTaxonomy $wowCollectionTaxonomy): array => [
                'entry_id' => $wowCollectionTaxonomy->entry_id,
                'category' => $wowCollectionTaxonomy->category,
                'source' => $wowCollectionTaxonomy->source,
                'obtainable' => $wowCollectionTaxonomy->obtainable,
            ])
            ->all();

        foreach ($rows as $row) {
            $taxonomy[$row['entry_id']] = new TaxonomyEntry($row['category'], $row['source'], $row['obtainable']);
        }

        return $taxonomy;
    }
}
