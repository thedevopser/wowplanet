<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Importers;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\CollectionSearchSweep;
use App\Infrastructure\Blizzard\Concerns\ImportsFromBlizzardApi;
use App\Infrastructure\Blizzard\RenderedIconProbe;
use App\Infrastructure\Blizzard\SearchIdWindow;
use App\Infrastructure\Reference\ReferenceMaps;
use App\Infrastructure\Taxonomy\ApiSourceTypeVocabulary;
use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Infrastructure\Taxonomy\CollectionTaxonomyReader;
use App\Infrastructure\Taxonomy\TaxonomyEntry;
use App\Models\WowMount;

/**
 * Catalogue des montures : l'API tranche l'existence, la taxonomie tranche le rangement.
 *
 * Trois sources, chacune pour ce qu'elle seule porte. L'index tranche l'existence et le nom :
 * il n'expose que ce qui est live sur retail, donc une monture qu'il ignore n'est pas encore
 * obtenable et reste hors catalogue. Le balayage de recherche apporte le type de source. Et
 * le socle de référence apporte le sort source et, par lui, l'icône.
 *
 * Le socle est ici indispensable et non un confort : l'API n'expose l'icône d'une monture
 * nulle part — `data/wow/media/mount/{id}` est en 404 et l'espace de media des sorts est
 * creux —, ni le sort qui porte son lien Wowhead. `Mount.SourceSpellID` puis
 * `SpellMisc.SpellIconFileDataID` sont le seul chemin.
 *
 * L'index est obligatoire, le reste ne l'est pas. Un index manquant ferait disparaître du lot
 * des montures que le balayage des lignes périmées supprimerait ensuite : l'import s'interrompt.
 * Une fenêtre de recherche ou un socle vide, en revanche, laisse la ligne avec ce qu'elle avait.
 *
 * Une monture que la taxonomie ne range pas entre quand même, avec le type de source de l'API
 * en valeur d'attente, et figure au rapport d'entrées à arbitrer : l'invisibilité silencieuse
 * était le défaut à corriger.
 */
final readonly class MountImporter
{
    use ImportsFromBlizzardApi;

    private const INDEX_ENDPOINT = 'data/wow/mount/index';

    private const SAVE_CHUNK = 500;

    public function __construct(
        BlizzardApiClient $blizzardApiClient,
        private CollectionTaxonomyReader $collectionTaxonomyReader,
        private CollectionSearchSweep $collectionSearchSweep,
        private ReferenceMaps $referenceMaps,
        private RenderedIconProbe $renderedIconProbe,
    ) {
        $this->blizzardApiClient = $blizzardApiClient;
    }

    public function import(): void
    {
        $names = $this->fetchNames();
        if ($names === []) {
            return;
        }

        $existing = $this->existingRows();

        $rows = $this->buildRows(
            $names,
            $this->fetchSourceTypes(array_keys($names)),
            $this->collectionTaxonomyReader->for(CollectionEntity::Mount),
            $this->referenceIdentity($existing),
            $existing,
        );

        $this->saveRows($rows, array_keys($names));
    }

    /**
     * Noms français depuis l'index de l'API, qui tranche l'existence.
     *
     * @return array<int, string>
     */
    private function fetchNames(): array
    {
        $this->info('Fetching mount index from Blizzard API...');

        $index = $this->fetchWithRetry(self::INDEX_ENDPOINT);
        if (! $index instanceof \App\Infrastructure\Blizzard\Responses\ResponsePayload) {
            $this->info('  ERROR: mount index unavailable, aborting import (catalog left untouched).');

            return [];
        }

        $names = [];

        foreach ($index->objectList('mounts') as $responsePayload) {
            $id = $responsePayload->optionalInt('id') ?? 0;
            $name = trim($responsePayload->optionalString('name') ?? '');
            if ($id > 0 && $name !== '') {
                $names[$id] = $name;
            }
        }

        if ($names === []) {
            $this->info('  ERROR: mount index holds no usable name, aborting import (catalog left untouched).');

            return [];
        }

        $this->info(sprintf('  Found %d live mounts in the API index.', count($names)));

        return $names;
    }

    /**
     * Types de source par balayage de recherche : quatre fenêtres couvrent tout le catalogue,
     * là où le détail unitaire demanderait un appel par monture.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function fetchSourceTypes(array $ids): array
    {
        $windows = [];
        foreach ($ids as $id) {
            $windows[SearchIdWindow::holding($id)] = true;
        }

        $windows = array_keys($windows);
        sort($windows);

        $this->info(sprintf('Sweeping %d mount search windows...', count($windows)));

        $sourceTypes = [];
        foreach ($this->collectionSearchSweep->sweepMounts($windows) as $id => $mountSearchDocument) {
            if ($mountSearchDocument->sourceType !== null) {
                $sourceTypes[$id] = $mountSearchDocument->sourceType;
            }
        }

        return $sourceTypes;
    }

    /**
     * Sort source et icône, que seul le socle porte.
     *
     * @param  array<int, array{name_fr: string, source: string|null, category: string|null, source_spell_id: int|null, icon_url: string|null, is_active: bool}>  $existing
     * @return array<int, array{spell_id: int|null, icon_url: string|null}>
     */
    private function referenceIdentity(array $existing): array
    {
        $spells = $this->referenceMaps->mountSpells();
        $icons = $this->probeIcons($this->referenceMaps->mountIcons(), $existing);

        $identity = [];
        foreach (array_keys($spells + $icons) as $id) {
            $identity[$id] = ['spell_id' => $spells[$id] ?? null, 'icon_url' => $icons[$id] ?? null];
        }

        return $identity;
    }

    /**
     * Écarte les icônes composées que le CDN de rendu ne sert pas.
     *
     * Une URL déjà en base a déjà passé ce contrôle le jour où elle a été écrite : seules
     * les nouvelles sont soumises, ce qui laisse une passe suivante sans aucune requête.
     *
     * @param  array<int, string>  $icons
     * @param  array<int, array{name_fr: string, source: string|null, category: string|null, source_spell_id: int|null, icon_url: string|null, is_active: bool}>  $existing
     * @return array<int, string>
     */
    private function probeIcons(array $icons, array $existing): array
    {
        $known = [];
        foreach ($existing as $row) {
            if ($row['icon_url'] !== null) {
                $known[$row['icon_url']] = true;
            }
        }

        $candidates = array_values(array_unique(array_filter(
            $icons,
            static fn (string $url): bool => ! isset($known[$url]),
        )));

        if ($candidates !== []) {
            $this->info(sprintf('Checking %d newly composed icons against the render CDN...', count($candidates)));
        }

        foreach ($this->renderedIconProbe->servedUrls($candidates) as $url) {
            $known[$url] = true;
        }

        $served = array_filter($icons, static fn (string $url): bool => isset($known[$url]));

        $refused = count($icons) - count($served);
        if ($refused > 0) {
            $this->info(sprintf('  %d icons the CDN does not serve: those mounts stay without one, rather than with a broken image.', $refused));
        }

        return $served;
    }

    /**
     * @return array<int, array{name_fr: string, source: string|null, category: string|null, source_spell_id: int|null, icon_url: string|null, is_active: bool}>
     */
    private function existingRows(): array
    {
        $rows = [];

        foreach (WowMount::query()->get(['id', 'name_fr', 'source', 'category', 'source_spell_id', 'icon_url', 'is_active']) as $mount) {
            $rows[$mount->id] = [
                'name_fr' => $mount->name_fr,
                'source' => $mount->source,
                'category' => $mount->category,
                'source_spell_id' => $mount->source_spell_id,
                'icon_url' => $mount->icon_url,
                'is_active' => $mount->is_active,
            ];
        }

        return $rows;
    }

    /**
     * Une ligne identique à celle déjà en base n'est pas réécrite : sans cela, chaque passe
     * toucherait les seize cents lignes et le mode incrémental ne voudrait plus rien dire.
     *
     * @param  array<int, string>  $names
     * @param  array<int, string>  $sourceTypes
     * @param  array<int, TaxonomyEntry>  $taxonomy
     * @param  array<int, array{spell_id: int|null, icon_url: string|null}>  $reference
     * @param  array<int, array{name_fr: string, source: string|null, category: string|null, source_spell_id: int|null, icon_url: string|null, is_active: bool}>  $existing
     * @return list<array{id: int, name_fr: string, source: string|null, category: string|null, source_spell_id: int|null, icon_url: string|null, is_active: bool}>
     */
    private function buildRows(array $names, array $sourceTypes, array $taxonomy, array $reference, array $existing): array
    {
        $rows = [];
        $awaitingArbitration = 0;
        $unchanged = 0;

        foreach ($names as $id => $nameFr) {
            $entry = $taxonomy[$id] ?? null;
            if (! $entry instanceof TaxonomyEntry) {
                $awaitingArbitration++;
            }

            $previous = $existing[$id] ?? null;

            $row = [
                'name_fr' => $nameFr,
                'source' => $entry instanceof TaxonomyEntry
                    ? $entry->source
                    : ApiSourceTypeVocabulary::toPendingSource($sourceTypes[$id] ?? null),
                'category' => $entry?->category,
                'source_spell_id' => $reference[$id]['spell_id'] ?? $previous['source_spell_id'] ?? null,
                'icon_url' => $reference[$id]['icon_url'] ?? $previous['icon_url'] ?? null,
                'is_active' => true,
            ];

            if ($row === $previous) {
                $unchanged++;

                continue;
            }

            $rows[] = ['id' => $id] + $row;
        }

        $this->info(sprintf(
            '  %d mounts in catalog, %d with icon URL, %d already up to date.',
            count($names),
            count(array_filter($names, static fn (string $name, int $id): bool => ($reference[$id]['icon_url'] ?? null) !== null, ARRAY_FILTER_USE_BOTH)),
            $unchanged,
        ));
        $this->info(sprintf('  %d awaiting arbitration (absent from the taxonomy).', $awaitingArbitration));

        return $rows;
    }

    /**
     * @param  list<array{id: int, name_fr: string, source: string|null, category: string|null, source_spell_id: int|null, icon_url: string|null, is_active: bool}>  $rows
     * @param  list<int>  $catalogIds
     */
    private function saveRows(array $rows, array $catalogIds): void
    {
        $this->info(sprintf('Saving %d mounts...', count($rows)));

        foreach (array_chunk($rows, self::SAVE_CHUNK) as $chunk) {
            WowMount::query()->upsert(
                $chunk,
                uniqueBy: ['id'],
                update: ['name_fr', 'source', 'category', 'source_spell_id', 'icon_url', 'is_active'],
            );
        }

        $this->deleteRowsOutsideCatalog(WowMount::class, $catalogIds, 'mounts');

        $this->info(sprintf('Mount import complete: %d items.', count($catalogIds)));
    }
}
