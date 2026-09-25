<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Importers;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\CollectionSearchSweep;
use App\Infrastructure\Blizzard\Concerns\ImportsFromBlizzardApi;
use App\Infrastructure\Blizzard\MediaSearchSweep;
use App\Infrastructure\Blizzard\MediaSearchTag;
use App\Infrastructure\Blizzard\SearchIdWindow;
use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Infrastructure\Taxonomy\CollectionTaxonomyReader;
use App\Infrastructure\Taxonomy\TaxonomyEntry;
use App\Models\WowDecor;

/**
 * Catalogue des décorations : l'API tranche l'existence, la taxonomie tranche le rangement.
 *
 * Voir MountImporter pour le détail du partage d'autorité. L'index tranche l'existence et le
 * nom, le balayage de recherche apporte l'item lié, et l'icône vient du balayage des media
 * d'items — une décoration n'a pas d'icône propre, c'est celle de l'objet qui la pose.
 *
 * `is_active` vient de la curation, jamais de l'API. L'API atteste qu'une décoration existe,
 * jamais qu'un joueur peut encore l'obtenir : un événement de pré-lancement clos ou une
 * promotion retirée laissent une entrée hors d'atteinte, qui compterait au dénominateur et
 * rendrait le 100 % inatteignable. Une décoration que personne n'a curée entre donc active,
 * rien n'attestant le contraire.
 *
 * L'index est obligatoire, le reste ne l'est pas : une fenêtre manquante laisse la ligne avec
 * l'item et l'icône qu'elle avait.
 */
final readonly class DecorImporter
{
    use ImportsFromBlizzardApi;

    private const INDEX_ENDPOINT = 'data/wow/decor/index';

    /** Les fenêtres de media pèsent environ 1,2 Mo : elles se traitent par petits lots. */
    private const MEDIA_WINDOW_BATCH = 10;

    private const SAVE_CHUNK = 500;

    public function __construct(
        BlizzardApiClient $blizzardApiClient,
        private CollectionTaxonomyReader $collectionTaxonomyReader,
        private CollectionSearchSweep $collectionSearchSweep,
        private MediaSearchSweep $mediaSearchSweep,
    ) {
        $this->blizzardApiClient = $blizzardApiClient;
    }

    public function import(): void
    {
        $names = $this->fetchNames();
        if ($names === []) {
            return;
        }

        $itemIds = $this->fetchItemIds(array_keys($names));

        $rows = $this->buildRows(
            $names,
            $itemIds,
            $this->fetchItemIcons($itemIds),
            $this->collectionTaxonomyReader->for(CollectionEntity::Decor),
            $this->existingRows(),
        );

        $this->saveRows($rows, array_keys($names));
    }

    /**
     * Noms français depuis l'index Housing Decor de l'API, qui tranche l'existence.
     *
     * @return array<int, string>
     */
    private function fetchNames(): array
    {
        $this->info('Fetching decor index from Blizzard API...');

        $index = $this->fetchWithRetry(self::INDEX_ENDPOINT);
        if (! $index instanceof \App\Infrastructure\Blizzard\Responses\ResponsePayload) {
            $this->info('  ERROR: decor index unavailable, aborting import (catalog left untouched).');

            return [];
        }

        $names = [];

        foreach ($index->objectList('decor_items') as $responsePayload) {
            $id = $responsePayload->optionalInt('id') ?? 0;
            $name = trim($responsePayload->optionalString('name') ?? '');
            if ($id > 0 && $name !== '') {
                $names[$id] = $name;
            }
        }

        if ($names === []) {
            $this->info('  ERROR: decor index holds no usable name, aborting import (catalog left untouched).');

            return [];
        }

        $this->info(sprintf('  Found %d live decors in the API index.', count($names)));

        return $names;
    }

    /**
     * Items liés par balayage de recherche, seule porte vers l'icône d'une décoration.
     *
     * @param  list<int>  $ids
     * @return array<int, int> [decor_id => item_id]
     */
    private function fetchItemIds(array $ids): array
    {
        $windows = [];
        foreach ($ids as $id) {
            $windows[SearchIdWindow::holding($id)] = true;
        }

        $windows = array_keys($windows);
        sort($windows);

        $this->info(sprintf('Sweeping %d decor search windows...', count($windows)));

        $itemIds = [];
        foreach ($this->collectionSearchSweep->sweepDecors($windows) as $id => $decorSearchDocument) {
            if ($decorSearchDocument->itemId !== null) {
                $itemIds[$id] = $decorSearchDocument->itemId;
            }
        }

        return $itemIds;
    }

    /**
     * @param  array<int, int>  $itemIds
     * @return array<int, string> [item_id => icon_url]
     */
    private function fetchItemIcons(array $itemIds): array
    {
        $windows = [];
        foreach ($itemIds as $itemId) {
            $windows[SearchIdWindow::holding($itemId)] = true;
        }

        $windows = array_keys($windows);
        sort($windows);

        $this->info(sprintf('Sweeping %d item media windows for decor icons...', count($windows)));

        $icons = [];
        foreach (array_chunk($windows, self::MEDIA_WINDOW_BATCH) as $chunk) {
            foreach ($this->mediaSearchSweep->sweep($chunk, MediaSearchTag::Item) as $id => $mediaSearchDocument) {
                if ($mediaSearchDocument->iconUrl !== null) {
                    $icons[$id] = $mediaSearchDocument->iconUrl;
                }
            }
        }

        return $icons;
    }

    /**
     * @return array<int, array{name_fr: string, category: string|null, source: string|null, item_id: int|null, icon_url: string|null, is_active: bool}>
     */
    private function existingRows(): array
    {
        $rows = [];

        foreach (WowDecor::query()->get(['id', 'name_fr', 'category', 'source', 'item_id', 'icon_url', 'is_active']) as $decor) {
            $rows[$decor->id] = [
                'name_fr' => $decor->name_fr,
                'category' => $decor->category,
                'source' => $decor->source,
                'item_id' => $decor->item_id,
                'icon_url' => $decor->icon_url,
                'is_active' => $decor->is_active,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, string>  $names
     * @param  array<int, int>  $itemIds
     * @param  array<int, string>  $icons
     * @param  array<int, TaxonomyEntry>  $taxonomy
     * @param  array<int, array{name_fr: string, category: string|null, source: string|null, item_id: int|null, icon_url: string|null, is_active: bool}>  $existing
     * @return list<array{id: int, name_fr: string, category: string|null, source: string|null, item_id: int|null, icon_url: string|null, is_active: bool}>
     */
    private function buildRows(array $names, array $itemIds, array $icons, array $taxonomy, array $existing): array
    {
        $rows = [];
        $awaitingArbitration = 0;
        $inactive = 0;
        $unchanged = 0;

        foreach ($names as $id => $nameFr) {
            $entry = $taxonomy[$id] ?? null;
            if (! $entry instanceof TaxonomyEntry) {
                $awaitingArbitration++;
            }

            $previous = $existing[$id] ?? null;
            $itemId = $itemIds[$id] ?? $previous['item_id'] ?? null;

            $row = [
                'name_fr' => $nameFr,
                'category' => $entry?->category,
                'source' => $entry?->source,
                'item_id' => $itemId,
                'icon_url' => ($itemId === null ? null : $icons[$itemId] ?? null) ?? $previous['icon_url'] ?? null,
                'is_active' => $entry->obtainable ?? true,
            ];

            if (! $row['is_active']) {
                $inactive++;
            }

            if ($row === $previous) {
                $unchanged++;

                continue;
            }

            $rows[] = ['id' => $id] + $row;
        }

        $this->info(sprintf('  %d decors in catalog, %d not obtainable, %d already up to date.', count($names), $inactive, $unchanged));
        $this->info(sprintf('  %d awaiting arbitration (absent from the taxonomy).', $awaitingArbitration));

        return $rows;
    }

    /**
     * @param  list<array{id: int, name_fr: string, category: string|null, source: string|null, item_id: int|null, icon_url: string|null, is_active: bool}>  $rows
     * @param  list<int>  $catalogIds
     */
    private function saveRows(array $rows, array $catalogIds): void
    {
        $this->info(sprintf('Saving %d decors...', count($rows)));

        foreach (array_chunk($rows, self::SAVE_CHUNK) as $chunk) {
            WowDecor::query()->upsert(
                $chunk,
                uniqueBy: ['id'],
                update: ['name_fr', 'category', 'source', 'item_id', 'icon_url', 'is_active'],
            );
        }

        $this->deleteRowsOutsideCatalog(WowDecor::class, $catalogIds, 'decors');

        $this->info(sprintf('Decor import complete: %d items.', count($catalogIds)));
    }
}
