<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Importers;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Concerns\ImportsFromBlizzardApi;
use App\Infrastructure\Blizzard\Responses\PetDocument;
use App\Infrastructure\Taxonomy\ApiSourceTypeVocabulary;
use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Infrastructure\Taxonomy\CollectionTaxonomyReader;
use App\Infrastructure\Taxonomy\TaxonomyEntry;
use App\Models\WowPet;

/**
 * Catalogue des mascottes : l'API tranche l'existence, la taxonomie tranche le rangement.
 *
 * Voir MountImporter pour le détail du partage d'autorité. La différence tient au chemin :
 * `data/wow/search/pet` n'existe pas — il répond 404 —, donc l'identité vient du détail, un
 * appel par mascotte. C'est sans regret, le détail des mascottes étant le seul des trois à
 * porter l'icône en clair : les 2 179 de l'index en ont une, et une créature avec.
 *
 * L'index est obligatoire, les détails ne le sont pas : un détail manquant laisse la ligne
 * avec l'icône, la créature et la source qu'elle avait.
 */
final readonly class PetImporter
{
    use ImportsFromBlizzardApi;

    private const INDEX_ENDPOINT = 'data/wow/pet/index';

    private const DETAIL_ENDPOINT = 'data/wow/pet/';

    /** Les détails sont petits, mais deux mille corps décodés gardés ensemble ne le sont pas. */
    private const DETAIL_BATCH = 1000;

    private const SAVE_CHUNK = 500;

    public function __construct(
        BlizzardApiClient $blizzardApiClient,
        private CollectionTaxonomyReader $collectionTaxonomyReader,
    ) {
        $this->blizzardApiClient = $blizzardApiClient;
    }

    public function import(): void
    {
        $names = $this->fetchNames();
        if ($names === []) {
            return;
        }

        $rows = $this->buildRows(
            $names,
            $this->fetchDetails(array_keys($names)),
            $this->collectionTaxonomyReader->for(CollectionEntity::Pet),
            $this->existingRows(),
        );

        $this->saveRows($rows, array_keys($names));
    }

    /**
     * Noms français depuis l'index de l'API, qui tranche l'existence (id = species id).
     *
     * @return array<int, string>
     */
    private function fetchNames(): array
    {
        $this->info('Fetching pet index from Blizzard API...');

        $index = $this->fetchWithRetry(self::INDEX_ENDPOINT);
        if (! $index instanceof \App\Infrastructure\Blizzard\Responses\ResponsePayload) {
            $this->info('  ERROR: pet index unavailable, aborting import (catalog left untouched).');

            return [];
        }

        $names = [];

        foreach ($index->objectList('pets') as $responsePayload) {
            $id = $responsePayload->optionalInt('id') ?? 0;
            $name = trim($responsePayload->optionalString('name') ?? '');
            if ($id > 0 && $name !== '') {
                $names[$id] = $name;
            }
        }

        if ($names === []) {
            $this->info('  ERROR: pet index holds no usable name, aborting import (catalog left untouched).');

            return [];
        }

        $this->info(sprintf('  Found %d live pets in the API index.', count($names)));

        return $names;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, PetDocument>
     */
    private function fetchDetails(array $ids): array
    {
        $this->info(sprintf('Fetching the detail of %d pets...', count($ids)));

        $documents = [];
        $missing = 0;

        foreach (array_chunk($ids, self::DETAIL_BATCH) as $chunk) {
            $endpoints = [];
            foreach ($chunk as $id) {
                $endpoints[$id] = self::DETAIL_ENDPOINT.$id;
            }

            foreach ($this->fetchBatchAsync($endpoints) as $id => $decoded) {
                if ($decoded === null) {
                    $missing++;

                    continue;
                }

                $documents[(int) $id] = PetDocument::fromPayload($decoded);
            }
        }

        if ($missing > 0) {
            $this->info(sprintf('  %d details unavailable: those rows keep the icon, creature and source they had.', $missing));
        }

        return $documents;
    }

    /**
     * @return array<int, array{name_fr: string, category: string|null, source: string|null, creature_id: int|null, icon_url: string|null, is_active: bool}>
     */
    private function existingRows(): array
    {
        $rows = [];

        foreach (WowPet::query()->get(['id', 'name_fr', 'category', 'source', 'creature_id', 'icon_url', 'is_active']) as $pet) {
            $rows[$pet->id] = [
                'name_fr' => $pet->name_fr,
                'category' => $pet->category,
                'source' => $pet->source,
                'creature_id' => $pet->creature_id,
                'icon_url' => $pet->icon_url,
                'is_active' => $pet->is_active,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, string>  $names
     * @param  array<int, PetDocument>  $details
     * @param  array<int, TaxonomyEntry>  $taxonomy
     * @param  array<int, array{name_fr: string, category: string|null, source: string|null, creature_id: int|null, icon_url: string|null, is_active: bool}>  $existing
     * @return list<array{id: int, name_fr: string, category: string|null, source: string|null, creature_id: int|null, icon_url: string|null, is_active: bool}>
     */
    private function buildRows(array $names, array $details, array $taxonomy, array $existing): array
    {
        $rows = [];
        $awaitingArbitration = 0;
        $unchanged = 0;
        $withIcons = 0;

        foreach ($names as $id => $nameFr) {
            $entry = $taxonomy[$id] ?? null;
            if (! $entry instanceof TaxonomyEntry) {
                $awaitingArbitration++;
            }

            $previous = $existing[$id] ?? null;
            $petDocument = $details[$id] ?? null;

            $row = [
                'name_fr' => $nameFr,
                'category' => $entry?->category,
                'source' => $entry instanceof TaxonomyEntry
                    ? $entry->source
                    : ApiSourceTypeVocabulary::toPendingSource($petDocument?->sourceType),
                'creature_id' => $petDocument->creatureId ?? $previous['creature_id'] ?? null,
                'icon_url' => $petDocument->iconUrl ?? $previous['icon_url'] ?? null,
                'is_active' => true,
            ];

            if ($row['icon_url'] !== null) {
                $withIcons++;
            }

            if ($row === $previous) {
                $unchanged++;

                continue;
            }

            $rows[] = ['id' => $id] + $row;
        }

        $this->info(sprintf('  %d pets in catalog, %d with icon URL, %d already up to date.', count($names), $withIcons, $unchanged));
        $this->info(sprintf('  %d awaiting arbitration (absent from the taxonomy).', $awaitingArbitration));

        return $rows;
    }

    /**
     * @param  list<array{id: int, name_fr: string, category: string|null, source: string|null, creature_id: int|null, icon_url: string|null, is_active: bool}>  $rows
     * @param  list<int>  $catalogIds
     */
    private function saveRows(array $rows, array $catalogIds): void
    {
        $this->info(sprintf('Saving %d pets...', count($rows)));

        foreach (array_chunk($rows, self::SAVE_CHUNK) as $chunk) {
            WowPet::query()->upsert(
                $chunk,
                uniqueBy: ['id'],
                update: ['name_fr', 'category', 'source', 'creature_id', 'icon_url', 'is_active'],
            );
        }

        $this->deleteRowsOutsideCatalog(WowPet::class, $catalogIds, 'pets');

        $this->info(sprintf('Pet import complete: %d items.', count($catalogIds)));
    }
}
