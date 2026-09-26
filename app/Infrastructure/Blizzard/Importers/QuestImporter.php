<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Importers;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\BlizzardInternalName;
use App\Infrastructure\Blizzard\Concerns\ImportsFromBlizzardApi;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;
use App\Models\WowQuest;

final readonly class QuestImporter
{
    use ImportsFromBlizzardApi;

    public function __construct(
        BlizzardApiClient $blizzardApiClient,
    ) {
        $this->blizzardApiClient = $blizzardApiClient;
    }

    /**
     * @param  array<int, int>  $areaExpansionMap  [areaId => expansionId]
     * @param  array<int, int>  $questExpansionMap  [questId => expansionId] from ContentTuning
     * @param  array<int, string>  $questFactionMap  [questId => 'Alliance'|'Horde']
     * @param  array<int, string>  $zoneFactionMap  [areaId => 'Alliance'|'Horde']
     */
    public function import(
        array $areaExpansionMap,
        array $questExpansionMap = [],
        array $questFactionMap = [],
        array $zoneFactionMap = [],
    ): void {
        $this->info('Fetching quest area index from Blizzard API...');
        $this->info(sprintf('  Area expansion map: %d entries', count($areaExpansionMap)));
        $this->info(sprintf('  Quest expansion map (ContentTuning): %d entries', count($questExpansionMap)));
        $this->info(sprintf('  Quest faction map: %d entries', count($questFactionMap)));
        $this->info(sprintf('  Zone faction map: %d entries', count($zoneFactionMap)));

        $index = $this->fetchWithRetry('data/wow/quest/area/index');
        if (! $index instanceof \App\Infrastructure\Blizzard\Responses\ResponsePayload) {
            $this->info('ERROR: Could not fetch quest area index.');

            return;
        }

        $areas = array_map(
            static fn (ResponsePayload $responsePayload): array => ['id' => $responsePayload->requiredInt('id'), 'name' => $responsePayload->optionalString('name') ?? ''],
            $index->objectList('areas'),
        );
        $this->info(sprintf('Found %d quest areas. Fetching details concurrently...', count($areas)));

        $areaDetails = $this->fetchAreaDetailsConcurrently($areas);
        $this->info(sprintf('Fetched %d area details. Building quest rows...', count(array_filter($areaDetails))));

        $builtRows = $this->buildQuestRows($areas, $areaDetails, $areaExpansionMap, $questExpansionMap, $questFactionMap, $zoneFactionMap);
        $rows = array_values(array_filter($builtRows, static fn (array $row): bool => ! BlizzardInternalName::isInternal($row['name_fr'])));
        $this->info(sprintf('Built %d quest rows. Saving to database...', count($rows)));

        $this->saveQuests($rows);
        $this->deleteInternalQuests(array_values(array_diff(array_column($builtRows, 'id'), array_column($rows, 'id'))));
    }

    /**
     * @param  list<int>  $internalIds
     */
    private function deleteInternalQuests(array $internalIds): void
    {
        if ($internalIds === []) {
            return;
        }

        $deleted = WowQuest::destroy($internalIds);

        $this->info(sprintf('  %d quests with an internal Blizzard name skipped, %d deleted.', count($internalIds), $deleted));
    }

    /**
     * @param  list<array{id: int, name: string}>  $areas
     * @return array<int|string, ResponsePayload|null>
     */
    private function fetchAreaDetailsConcurrently(array $areas): array
    {
        $endpoints = [];
        foreach ($areas as $area) {
            $endpoints[$area['id']] = 'data/wow/quest/area/'.$area['id'];
        }

        return $this->fetchBatchAsync($endpoints);
    }

    /**
     * @param  list<array{id: int, name: string}>  $areas
     * @param  array<int|string, ResponsePayload|null>  $areaDetails
     * @param  array<int, int>  $areaExpansionMap
     * @param  array<int, int>  $questExpansionMap
     * @param  array<int, string>  $questFactionMap
     * @param  array<int, string>  $zoneFactionMap
     * @return list<array{id: int, name_fr: string, expansion_id: int, zone_name: string|null, faction: string|null}>
     */
    private function buildQuestRows(
        array $areas,
        array $areaDetails,
        array $areaExpansionMap,
        array $questExpansionMap,
        array $questFactionMap,
        array $zoneFactionMap,
    ): array {
        /** @var array<string, string> $areaNameFallback */
        $areaNameFallback = [];
        foreach ($areas as $area) {
            $areaNameFallback[$area['id']] = $area['name'];
        }

        $rows = [];
        $mapped = 0;
        $withZone = 0;

        foreach ($areaDetails as $areaId => $detail) {
            if ($detail === null) {
                continue;
            }

            $areaName = $this->resolveAreaName($detail, $areaNameFallback[$areaId] ?? '');
            $areaExpansionId = $areaExpansionMap[$areaId] ?? 0;

            foreach ($detail->objectList('quests') as $quest) {
                $questName = $quest->optionalString('name') ?? '';
                if ($questName === '') {
                    continue;
                }

                $questId = $quest->requiredInt('id');
                $expansionId = $questExpansionMap[$questId] ?? $areaExpansionId;
                $faction = $questFactionMap[$questId] ?? $zoneFactionMap[$areaId] ?? null;

                if ($expansionId > 0) {
                    $mapped++;
                }

                if ($areaName !== '') {
                    $withZone++;
                }

                $rows[] = [
                    'id' => $questId,
                    'name_fr' => $questName,
                    'expansion_id' => $expansionId,
                    'zone_name' => $areaName !== '' ? $areaName : null,
                    'faction' => $faction,
                ];
            }
        }

        $this->info(sprintf('  %d with expansion, %d with zone.', $mapped, $withZone));

        return $rows;
    }

    /**
     * @param  list<array{id: int, name_fr: string, expansion_id: int, zone_name: string|null, faction: string|null}>  $rows
     */
    private function saveQuests(array $rows): void
    {
        $written = 0;

        foreach (array_chunk($rows, 500) as $chunk) {
            /** @var \Illuminate\Support\Collection<int, WowQuest> $stored */
            $stored = WowQuest::query()
                ->whereIn('id', array_column($chunk, 'id'))
                ->get()
                ->keyBy('id');

            $upsertData = [];

            foreach ($chunk as $row) {
                /** @var WowQuest|null $existing */
                $existing = $stored->get($row['id']);
                $candidate = $this->questRow($row, $existing);

                if ($existing instanceof WowQuest && $this->isUnchanged($existing, $candidate)) {
                    continue;
                }

                $upsertData[] = $candidate;
            }

            if ($upsertData === []) {
                continue;
            }

            WowQuest::query()->upsert($upsertData, uniqueBy: ['id'], update: [
                'name_fr', 'expansion_id', 'zone_name', 'faction', 'is_active',
            ]);

            $written += count($upsertData);
            $this->info(sprintf('  Saved %d...', $written));
        }

        $this->info(sprintf('Quest import complete: %d quests, %d written.', count($rows), $written));
    }

    /**
     * La faction stockée l'emporte quand les cartes de référence n'en portent pas.
     *
     * `tagMirrorQuestFactions()` déduit une faction des récompenses de réputation, que
     * ni `questFactionMap` ni `zoneFactionMap` ne connaissent. L'écraser par un nul à
     * chaque passe détruisait son travail, et le lui faisait refaire : il ne cherche
     * que parmi les quêtes sans faction, donc il repayait ses appels de détail à
     * chaque import.
     *
     * @param  array{id: int, name_fr: string, expansion_id: int, zone_name: string|null, faction: string|null}  $row
     * @return array{id: int, name_fr: string, expansion_id: int, zone_name: string|null, faction: string|null, is_active: bool}
     */
    private function questRow(array $row, ?WowQuest $wowQuest): array
    {
        return [
            'id' => $row['id'],
            'name_fr' => $row['name_fr'],
            'expansion_id' => $row['expansion_id'],
            'zone_name' => $row['zone_name'],
            'faction' => $row['faction'] ?? $wowQuest?->faction,
            'is_active' => true,
        ];
    }

    /**
     * @param  array{id: int, name_fr: string, expansion_id: int, zone_name: string|null, faction: string|null, is_active: bool}  $row
     */
    private function isUnchanged(WowQuest $wowQuest, array $row): bool
    {
        return $wowQuest->name_fr === $row['name_fr']
            && $wowQuest->expansion_id === $row['expansion_id']
            && $wowQuest->zone_name === $row['zone_name']
            && $wowQuest->faction === $row['faction']
            && $wowQuest->is_active === $row['is_active'];
    }

    /**
     * L'API sert la zone tantôt en texte, tantôt en objet nommé.
     */
    private function resolveAreaName(ResponsePayload $responsePayload, string $fallbackName): string
    {
        $areaName = $responsePayload->lenientString('area');
        if ($areaName !== null) {
            return $areaName;
        }

        $area = $responsePayload->optionalObject('area');

        return $area instanceof ResponsePayload ? $area->optionalString('name') ?? $fallbackName : $fallbackName;
    }

    /**
     * @param  array<int, string>  $reputationFactionMap
     */
    public function tagMirrorFactions(array $reputationFactionMap): void
    {
        $this->info('Tagging mirror quest pairs via API reputation rewards...');
        $pairs = $this->findMirrorPairs();
        $this->info(sprintf('  Found %d mirror pairs to process.', count($pairs)));

        if ($pairs === []) {
            $this->info('  No mirror pairs found.');

            return;
        }

        $questDetails = $this->fetchQuestDetailsConcurrently($pairs);
        $this->resolvePairsFromCache($pairs, $questDetails, $reputationFactionMap);
    }

    /**
     * @param  list<array{id_a: int, id_b: int, name: string, zone: string}>  $pairs
     * @return array<int|string, ResponsePayload|null>
     */
    private function fetchQuestDetailsConcurrently(array $pairs): array
    {
        $endpoints = [];
        foreach ($pairs as $pair) {
            $endpoints[$pair['id_a']] = 'data/wow/quest/'.$pair['id_a'];
            $endpoints[$pair['id_b']] = 'data/wow/quest/'.$pair['id_b'];
        }

        $this->info(sprintf('  Fetching %d unique quest details concurrently...', count($endpoints)));

        return $this->fetchBatchAsync($endpoints);
    }

    /**
     * @param  list<array{id_a: int, id_b: int, name: string, zone: string}>  $pairs
     * @param  array<int|string, ResponsePayload|null>  $questDetails
     * @param  array<int, string>  $reputationFactionMap
     */
    private function resolvePairsFromCache(array $pairs, array $questDetails, array $reputationFactionMap): void
    {
        $tagged = 0;
        $skipped = 0;
        $notFound = 0;

        foreach ($pairs as $pair) {
            $detailA = $questDetails[$pair['id_a']] ?? null;
            $detailB = $questDetails[$pair['id_b']] ?? null;

            if ($detailA === null && $detailB === null) {
                $notFound++;

                continue;
            }

            $factionFromA = ($detailA !== null) ? $this->detectFactionFromReputations($detailA, $reputationFactionMap) : null;
            $factionFromB = ($detailB !== null) ? $this->detectFactionFromReputations($detailB, $reputationFactionMap) : null;
            $resolvedFaction = $factionFromA ?? $factionFromB;

            if ($resolvedFaction === null) {
                $skipped++;

                continue;
            }

            $mirrorFaction = $resolvedFaction === 'Alliance' ? 'Horde' : 'Alliance';
            $factionA = $factionFromA ?? $mirrorFaction;
            $factionB = $factionFromB ?? $mirrorFaction;

            $this->info(sprintf('  [TAG] %s → %d=%s, %d=%s', $pair['name'], $pair['id_a'], $factionA, $pair['id_b'], $factionB));
            WowQuest::query()->where('id', $pair['id_a'])->update(['faction' => $factionA]);
            WowQuest::query()->where('id', $pair['id_b'])->update(['faction' => $factionB]);
            $tagged++;
        }

        $this->info(sprintf('Mirror tagging complete: %d tagged, %d no reputation data, %d not found in API.', $tagged, $skipped, $notFound));
    }

    /**
     * @param  array<int, string>  $reputationFactionMap
     */
    private function detectFactionFromReputations(ResponsePayload $responsePayload, array $reputationFactionMap): ?string
    {
        foreach ($responsePayload->optionalObject('rewards')?->objectList('reputations') ?? [] as $reputation) {
            $factionId = $reputation->optionalObject('reward')?->optionalInt('id');
            if ($factionId !== null && isset($reputationFactionMap[$factionId])) {
                return $reputationFactionMap[$factionId];
            }
        }

        return null;
    }

    /**
     * @return list<array{id_a: int, id_b: int, name: string, zone: string}>
     */
    private function findMirrorPairs(): array
    {
        /** @var array<string, list<int>> $groups */
        $groups = [];

        foreach (WowQuest::query()->where('is_active', true)->whereNull('faction')->lazy() as $lazyCollection) {
            $key = $lazyCollection->name_fr.'|||'.$lazyCollection->zone_name;
            $groups[$key][] = $lazyCollection->id;
        }

        $pairs = [];
        foreach ($groups as $key => $ids) {
            if (count($ids) < 2) {
                continue;
            }

            sort($ids);
            [$name, $zone] = explode('|||', $key);
            $pairs[] = [
                'id_a' => $ids[0],
                'id_b' => $ids[1],
                'name' => $name,
                'zone' => $zone,
            ];
        }

        return $pairs;
    }
}
