<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Importers;

use App\Application\DTOs\AppearanceImportProgress;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Concerns\ImportsFromBlizzardApi;
use App\Infrastructure\Blizzard\HourlyBudgetGuard;
use App\Infrastructure\Blizzard\ItemSearchSweep;
use App\Infrastructure\Blizzard\Responses\ItemSearchDocument;
use App\Infrastructure\Blizzard\Responses\MediaSearchDocument;
use App\Models\WowAppearance;
use Illuminate\Support\Sleep;

/**
 * Importe la garde-robe depuis l'API officielle Blizzard.
 *
 * Deux autorités, jamais mélangées : les 18 index de slots disent ce qui est
 * collectionnable, le balayage du catalogue d'items dit ce que chaque apparence
 * contient. Une apparence absente des index n'entre pas, quel que soit le nombre
 * d'items qui la portent.
 *
 * Le balayage remplace l'appel unitaire par apparence — 22 000 requêtes — par quelques
 * centaines de fenêtres d'identifiants, un document de recherche d'item portant déjà le
 * nom, la qualité, le media, la classe d'objet et les apparences liées.
 *
 * L'unité de reprise est la fenêtre : la passe items occupe les `n` premières, la passe
 * media les `n` suivantes. Les lignes en base servent d'accumulateur d'une fenêtre à
 * l'autre, ce qui permet de reprendre un balayage interrompu sans rien porter d'une
 * passe à la suivante.
 */
final readonly class AppearanceImporter
{
    use ImportsFromBlizzardApi;

    /** Slots transmoggables exposés par l'Item Appearance API. */
    private const API_SLOTS = [
        'HEAD', 'SHOULDER', 'BODY', 'CHEST', 'WAIST', 'LEGS', 'FEET', 'WRIST', 'HAND',
        'CLOAK', 'TABARD', 'WEAPON', 'SHIELD', 'RANGED', 'TWOHWEAPON', 'WEAPONMAINHAND',
        'WEAPONOFFHAND', 'HOLDABLE',
    ];

    /** Alias API → vocabulaire de slots historique de la base (préserve les filtres du front). */
    private const SLOT_ALIASES = [
        'BODY' => 'SHIRT',
        'WEAPONMAINHAND' => 'WEAPON',
    ];

    /** Fenêtres balayées ensemble : c'est la manette du pic mémoire (~1,2 Mo par fenêtre). */
    private const DEFAULT_WINDOW_BATCH = 5;

    private const WRITE_CHUNK = 500;

    public function __construct(
        BlizzardApiClient $blizzardApiClient,
        private HourlyBudgetGuard $hourlyBudgetGuard,
        private ItemSearchSweep $itemSearchSweep,
    ) {
        $this->blizzardApiClient = $blizzardApiClient;
    }

    /**
     * Boucle bloquante sur importChunk() jusqu'à épuisement des fenêtres.
     *
     * Plus aucun chemin de production ne passe ici : `ImportPipeline` appelle `importChunk()`
     * et re-dispatche `RunImportJob` au lieu de bloquer le worker. Elle subsiste parce que
     * la suite de tests de cet importer pilote l'import complet par elle — la refaire dans
     * chaque test dupliquerait cette boucle une douzaine de fois.
     *
     * @param  int|null  $limit  Borne le nombre de fenêtres balayées par passe (smoke-test sans consommer le quota API)
     */
    public function import(bool $full = false, ?int $limit = null): void
    {
        $offset = 0;
        do {
            $progress = $this->importChunk($full, $offset, PHP_INT_MAX, $limit);
            $offset = $progress->offset;

            if (! $progress->done && $progress->secondsUntilBudget > 0) {
                $this->info(sprintf('  Hourly API budget reached, pausing %ds...', $progress->secondsUntilBudget));
                Sleep::sleep($progress->secondsUntilBudget);
            }
        } while (! $progress->done);
    }

    /**
     * Balaie les fenêtres depuis $offset en sauvegardant chaque lot. S'arrête sans
     * avancer l'offset quand le budget import est épuisé (retourne secondsUntilBudget),
     * quand le time-box est atteint, ou quand $stopRequested dit que l'administrateur a
     * repris la main. La suppression du rebut n'a lieu qu'une fois tout balayé, et jamais
     * en mode --limit : c'est ce qui fait qu'une passe interrompue ne peut pas amputer le
     * catalogue, les trois sorties anticipées passant toutes avant elle.
     *
     * @param  (\Closure(): bool)|null  $stopRequested  Consulté à chaque fenêtre, comme le time-box
     */
    public function importChunk(bool $full, int $offset, int $timeBoxSeconds, ?int $limit = null, ?\Closure $stopRequested = null): AppearanceImportProgress
    {
        $deadline = microtime(true) + $timeBoxSeconds;
        $stopRequested ??= static fn (): bool => false;

        $this->info('Fetching item appearance slot indexes from Blizzard API...');
        $slotByAppearance = $this->fetchSlotIndexes();
        if ($slotByAppearance === []) {
            $this->info('ERROR: Could not fetch any appearance slot index.');

            return new AppearanceImportProgress(done: true, offset: 0, total: 0, secondsUntilBudget: 0);
        }

        $itemWindows = $this->itemWindowCount($limit);
        if ($itemWindows === null) {
            return new AppearanceImportProgress(done: true, offset: 0, total: 0, secondsUntilBudget: 0);
        }

        $total = $itemWindows * 2;

        /** @var int $ceiling */
        $ceiling = config('services.blizzard.import_hourly_ceiling', 30000);
        /** @var int $configuredBatch */
        $configuredBatch = config('services.blizzard.appearance_window_batch', self::DEFAULT_WINDOW_BATCH);
        $batchSize = max(1, $configuredBatch);

        /** @var array<int, array<int, int>>|null $mediaTargets Lignes à illustrer, par fenêtre (calculé à l'entrée de la passe media). */
        $mediaTargets = null;

        $position = $offset;
        while ($position < $total) {
            if (microtime(true) >= $deadline || $stopRequested()) {
                return new AppearanceImportProgress(done: false, offset: $position, total: $total, secondsUntilBudget: 0);
            }

            $isMediaPass = $position >= $itemWindows;
            $passEnd = $isMediaPass ? $total : $itemWindows;
            $batch = range($position, min($position + $batchSize, $passEnd) - 1);

            if ($isMediaPass) {
                $mediaTargets ??= $this->mediaTargets($full);
                $windows = array_values(array_filter(
                    array_map(static fn (int $slot): int => $slot - $itemWindows, $batch),
                    static fn (int $window): bool => isset($mediaTargets[$window]),
                ));
            } else {
                $windows = $batch;
            }

            if ($windows !== []) {
                $wait = $this->hourlyBudgetGuard->secondsUntilAvailable(count($windows), $ceiling);
                if ($wait > 0) {
                    return new AppearanceImportProgress(done: false, offset: $position, total: $total, secondsUntilBudget: $wait);
                }

                $isMediaPass
                    ? $this->resolveIcons($windows, $mediaTargets ?? [])
                    : $this->sweepItemWindows($windows, $slotByAppearance);
            }

            $position += count($batch);
            $this->info(sprintf('  Appearance sweep: %d/%d windows.', $position, $total));
        }

        if ($limit === null) {
            $this->deleteRowsOutsideCatalog(WowAppearance::class, array_keys($slotByAppearance), 'appearances');
        }

        return new AppearanceImportProgress(done: true, offset: $total, total: $total, secondsUntilBudget: 0);
    }

    /**
     * Nombre de fenêtres couvrant le catalogue d'items, `null` si la borne est introuvable.
     *
     * Balayer une plage devinée manquerait les identifiants les plus hauts, donc le
     * contenu le plus récent, et la suppression finale prendrait les apparences neuves
     * pour du rebut. On s'arrête plutôt que d'importer à l'aveugle.
     */
    private function itemWindowCount(?int $limit): ?int
    {
        $highestItemId = $this->itemSearchSweep->highestItemId();
        if ($highestItemId === null) {
            $this->info('ERROR: Could not read the highest item id, aborting import (catalog left untouched).');

            return null;
        }

        $windows = ItemSearchSweep::windowCountFor($highestItemId);

        return $limit === null ? $windows : min($windows, max(1, $limit));
    }

    /**
     * Index par slot → [appearanceId => slot (vocabulaire base)].
     *
     * Ces index constituent le catalogue de référence : la suppression du rebut porte sur
     * tout ce qui n'y figure pas. Un index partiel effacerait donc les slots manquants. On
     * abandonne dès qu'un seul slot ne répond pas, plutôt que de le sauter.
     *
     * @return array<int, string>
     */
    private function fetchSlotIndexes(): array
    {
        $map = [];
        $failedSlots = [];

        foreach (self::API_SLOTS as $apiSlot) {
            $index = $this->fetchWithRetry('data/wow/item-appearance/slot/'.$apiSlot);
            if (! $index instanceof \App\Infrastructure\Blizzard\Responses\ResponsePayload) {
                $failedSlots[] = $apiSlot;

                continue;
            }

            $slot = self::SLOT_ALIASES[$apiSlot] ?? $apiSlot;

            foreach ($index->objectList('appearances') as $appearance) {
                $id = $appearance->optionalInt('id') ?? 0;
                if ($id > 0 && ! isset($map[$id])) {
                    $map[$id] = $slot;
                }
            }
        }

        if ($failedSlots !== []) {
            $this->info(sprintf(
                'ERROR: %d/%d slot indexes unavailable (%s), aborting import (catalog left untouched).',
                count($failedSlots),
                count(self::API_SLOTS),
                implode(', ', $failedSlots),
            ));

            return [];
        }

        return $map;
    }

    /**
     * Balaie des fenêtres d'items et sauvegarde les apparences qu'elles portent.
     *
     * @param  list<int>  $windows
     * @param  array<int, string>  $slotByAppearance
     */
    private function sweepItemWindows(array $windows, array $slotByAppearance): void
    {
        /** @var array<int, array{item_id: int, quality: int, name: string, category: string|null}> $candidates */
        $candidates = [];

        $this->itemSearchSweep->sweepItems($windows, function (ItemSearchDocument $itemSearchDocument) use (&$candidates, $slotByAppearance): void {
            if ($itemSearchDocument->nameFr === null) {
                return;
            }

            foreach ($itemSearchDocument->appearanceIds as $appearanceId) {
                if (! isset($slotByAppearance[$appearanceId])) {
                    continue;
                }

                $current = $candidates[$appearanceId] ?? null;
                if ($current !== null && ! $this->beats($itemSearchDocument->quality, $itemSearchDocument->id, $current['quality'], $current['item_id'])) {
                    continue;
                }

                $candidates[$appearanceId] = [
                    'item_id' => $itemSearchDocument->id,
                    'quality' => $itemSearchDocument->quality,
                    'name' => $itemSearchDocument->nameFr,
                    'category' => $itemSearchDocument->categoryFr,
                ];
            }
        });

        $this->saveCandidates($candidates, $slotByAppearance);
    }

    /**
     * Ordre total des candidats : meilleure qualité, puis plus petit identifiant d'item.
     *
     * Le départage par identifiant n'est pas cosmétique : le balayage traverse les items
     * par fenêtres reprenables, un critère dépendant de l'ordre de parcours ne rendrait
     * pas le même représentant après une reprise.
     */
    private function beats(int $quality, int $itemId, int $otherQuality, int $otherItemId): bool
    {
        return $quality > $otherQuality || ($quality === $otherQuality && $itemId < $otherItemId);
    }

    /**
     * @param  array<int, array{item_id: int, quality: int, name: string, category: string|null}>  $candidates
     * @param  array<int, string>  $slotByAppearance
     */
    private function saveCandidates(array $candidates, array $slotByAppearance): void
    {
        if ($candidates === []) {
            return;
        }

        $stored = WowAppearance::query()->whereIn('id', array_keys($candidates))->get()->keyBy('id');

        $rows = [];
        foreach ($candidates as $appearanceId => $candidate) {
            /** @var WowAppearance|null $existing */
            $existing = $stored->get($appearanceId);

            if ($existing instanceof WowAppearance && $this->storedWins($existing, $candidate['quality'], $candidate['item_id'])) {
                continue;
            }

            // Item représentatif inchangé : l'icône déjà résolue reste valable. Sinon elle
            // est remise à nul, ce qui suffit à la faire reprendre par la passe media.
            $keepsIcon = $existing instanceof WowAppearance && $existing->item_id === $candidate['item_id'];

            $row = [
                'id' => $appearanceId,
                'name_fr' => $candidate['name'],
                'slot' => $slotByAppearance[$appearanceId] ?? null,
                'category' => $candidate['category'],
                'quality' => $candidate['quality'],
                'item_id' => $candidate['item_id'],
                'icon_file_data_id' => $keepsIcon ? $existing->icon_file_data_id : null,
                'icon_url' => $keepsIcon ? $existing->icon_url : null,
                'expansion_id' => null,
                'source' => null,
                'is_active' => true,
            ];

            if ($existing instanceof WowAppearance && $this->isUnchanged($existing, $row)) {
                continue;
            }

            $rows[] = $row;
        }

        $this->upsertRows($rows, ['name_fr', 'slot', 'category', 'quality', 'item_id', 'icon_file_data_id', 'icon_url', 'expansion_id', 'source', 'is_active']);
    }

    private function storedWins(WowAppearance $wowAppearance, int $quality, int $itemId): bool
    {
        if ($wowAppearance->item_id === null) {
            return false;
        }

        if ($wowAppearance->item_id === $itemId) {
            return false;
        }

        return ! $this->beats($quality, $itemId, $wowAppearance->quality ?? 0, $wowAppearance->item_id);
    }

    /**
     * Une ligne identique n'est pas réécrite : sans ça chaque passe toucherait les
     * 22 000 lignes et le mode incrémental ne voudrait plus rien dire.
     *
     * @param  array{id: int, name_fr: string, slot: string|null, category: string|null, quality: int|null, item_id: int|null, icon_file_data_id: int|null, icon_url: string|null, expansion_id: int|null, source: string|null, is_active: bool}  $row
     */
    private function isUnchanged(WowAppearance $wowAppearance, array $row): bool
    {
        return $wowAppearance->name_fr === $row['name_fr']
            && $wowAppearance->slot === $row['slot']
            && $wowAppearance->category === $row['category']
            && $wowAppearance->quality === $row['quality']
            && $wowAppearance->item_id === $row['item_id']
            && $wowAppearance->icon_file_data_id === $row['icon_file_data_id']
            && $wowAppearance->icon_url === $row['icon_url']
            && $wowAppearance->expansion_id === $row['expansion_id']
            && $wowAppearance->source === $row['source']
            && $wowAppearance->is_active === $row['is_active'];
    }

    /**
     * Lignes à illustrer, groupées par fenêtre de l'item représentatif.
     *
     * Hors rafraîchissement complet, seules les lignes sans icône sont visées : une
     * icône est déjà celle de l'item représentatif courant, puisqu'un changement de
     * représentant l'a remise à nul.
     *
     * @return array<int, array<int, int>> [window => [appearanceId => itemId]]
     */
    private function mediaTargets(bool $full): array
    {
        $builder = WowAppearance::query()->whereNotNull('item_id');
        if (! $full) {
            $builder->whereNull('icon_url');
        }

        $targets = [];

        /** @var list<array{id: int, item_id: int}> $rows */
        $rows = $builder->get(['id', 'item_id'])->map(static fn (WowAppearance $wowAppearance): array => [
            'id' => $wowAppearance->id,
            'item_id' => (int) $wowAppearance->item_id,
        ])->all();

        foreach ($rows as $row) {
            $targets[intdiv($row['item_id'], ItemSearchSweep::WINDOW_SIZE)][$row['id']] = $row['item_id'];
        }

        return $targets;
    }

    /**
     * Balaie des fenêtres de media et pose les icônes des lignes qui les attendent.
     *
     * @param  list<int>  $windows
     * @param  array<int, array<int, int>>  $mediaTargets
     */
    private function resolveIcons(array $windows, array $mediaTargets): void
    {
        $media = $this->itemSearchSweep->sweepItemMedia($windows);

        /** @var array<int, MediaSearchDocument> $iconByAppearance */
        $iconByAppearance = [];
        foreach ($windows as $window) {
            foreach ($mediaTargets[$window] ?? [] as $appearanceId => $itemId) {
                $mediaSearchDocument = $media[$itemId] ?? null;
                if (! $mediaSearchDocument instanceof MediaSearchDocument) {
                    continue;
                }

                if ($mediaSearchDocument->iconUrl === null) {
                    continue;
                }

                $iconByAppearance[$appearanceId] = $mediaSearchDocument;
            }
        }

        $this->writeIcons($iconByAppearance);
    }

    /**
     * @param  array<int, MediaSearchDocument>  $iconByAppearance
     */
    private function writeIcons(array $iconByAppearance): void
    {
        if ($iconByAppearance === []) {
            return;
        }

        foreach (array_chunk($iconByAppearance, self::WRITE_CHUNK, preserve_keys: true) as $chunk) {
            $rows = [];

            foreach (WowAppearance::query()->whereIn('id', array_keys($chunk))->get() as $wowAppearance) {
                $mediaSearchDocument = $chunk[$wowAppearance->id];
                if ($wowAppearance->icon_url === $mediaSearchDocument->iconUrl && $wowAppearance->icon_file_data_id === $mediaSearchDocument->fileDataId) {
                    continue;
                }

                $rows[] = [
                    'id' => $wowAppearance->id,
                    'name_fr' => $wowAppearance->name_fr,
                    'slot' => $wowAppearance->slot,
                    'category' => $wowAppearance->category,
                    'quality' => $wowAppearance->quality,
                    'item_id' => $wowAppearance->item_id,
                    'icon_file_data_id' => $mediaSearchDocument->fileDataId,
                    'icon_url' => $mediaSearchDocument->iconUrl,
                    'expansion_id' => $wowAppearance->expansion_id,
                    'source' => $wowAppearance->source,
                    'is_active' => $wowAppearance->is_active,
                ];
            }

            $this->upsertRows($rows, ['icon_file_data_id', 'icon_url']);
        }
    }

    /**
     * @param  list<array{id: int, name_fr: string, slot: string|null, category: string|null, quality: int|null, item_id: int|null, icon_file_data_id: int|null, icon_url: string|null, expansion_id: int|null, source: string|null, is_active: bool}>  $rows
     * @param  list<string>  $update
     */
    private function upsertRows(array $rows, array $update): void
    {
        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, self::WRITE_CHUNK) as $chunk) {
            WowAppearance::query()->upsert($chunk, uniqueBy: ['id'], update: $update);
        }

        $this->info(sprintf('  %d appearances written.', count($rows)));
    }
}
