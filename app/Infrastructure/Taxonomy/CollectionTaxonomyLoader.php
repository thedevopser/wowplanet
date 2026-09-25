<?php

declare(strict_types=1);

namespace App\Infrastructure\Taxonomy;

use App\Infrastructure\Taxonomy\Exceptions\TaxonomySourceUnavailableException;
use App\Models\WowCollectionTaxonomy;

/**
 * Amorçage et enrichissement de la taxonomie depuis l'instantané versionné du dépôt.
 *
 * Le chargement est strictement additif : `insertOrIgnore` laisse en place toute ligne déjà
 * connue, si bien que la première exécution amorce et que les suivantes ne font qu'ajouter les
 * entrées d'un nouveau patch. C'est ce qui permet à un arbitrage manuel de survivre.
 *
 * `load()` prend ses entrées de l'instantané, `loadEntries()` de ce qu'on lui tend : c'est par
 * là qu'un tirage amont fait entrer la curation d'un nouveau patch avant de réexporter
 * l'instantané.
 */
final readonly class CollectionTaxonomyLoader
{
    private const int CHUNK_SIZE = 500;

    public function __construct(private CollectionTaxonomySnapshot $collectionTaxonomySnapshot) {}

    /**
     * @return array{read: int, inserted: int, skipped: int}
     */
    public function load(CollectionEntity $collectionEntity): array
    {
        $entries = $this->collectionTaxonomySnapshot->entriesFor($collectionEntity);

        if ($entries === []) {
            throw TaxonomySourceUnavailableException::forEntity(
                $collectionEntity->value,
                $this->collectionTaxonomySnapshot->path(),
            );
        }

        return $this->loadEntries($collectionEntity, $entries);
    }

    /**
     * @param  array<int, TaxonomyEntry>  $entries
     * @return array{read: int, inserted: int, skipped: int}
     */
    public function loadEntries(CollectionEntity $collectionEntity, array $entries): array
    {
        $rows = $this->rows($collectionEntity, $entries);
        $inserted = 0;

        foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
            $inserted += WowCollectionTaxonomy::query()->insertOrIgnore($chunk);
        }

        return [
            'read' => count($rows),
            'inserted' => $inserted,
            'skipped' => count($rows) - $inserted,
        ];
    }

    /**
     * @param  array<int, TaxonomyEntry>  $entries
     * @return list<array{entity: string, entry_id: int, category: string|null, source: string|null, obtainable: bool}>
     */
    private function rows(CollectionEntity $collectionEntity, array $entries): array
    {
        $rows = [];

        foreach ($entries as $entryId => $taxonomyEntry) {
            $rows[] = [
                'entity' => $collectionEntity->value,
                'entry_id' => $entryId,
                'category' => $taxonomyEntry->category,
                'source' => $taxonomyEntry->source,
                'obtainable' => $taxonomyEntry->obtainable,
            ];
        }

        return $rows;
    }
}
