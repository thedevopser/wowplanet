<?php

declare(strict_types=1);

namespace App\Application\Services\Progress;

use App\Infrastructure\Blizzard\Responses\Profile\CharacterRaidsResponse;
use App\Infrastructure\Blizzard\Responses\Profile\RaidEncounterProgress;
use App\Infrastructure\Blizzard\Responses\Profile\RaidInstance;
use App\Infrastructure\Blizzard\Responses\Profile\RaidMode;

/**
 * @phpstan-type RaidEncounterView array{id: int, name: string, last_kill_timestamp: int}
 * @phpstan-type RaidModeView array{difficulty_type: string, difficulty_label: string, completed_count: int, total_count: int, encounters: list<RaidEncounterView>}
 * @phpstan-type RaidProgress array{instance_id: int, instance_name: string, modes: list<RaidModeView>}
 * @phpstan-type RaidNameMap array<int, array{name?: string, encounters?: array<int, string>}>
 */
class RaidProgressAggregator
{
    /**
     * Pseudo-extension Blizzard regroupant les raids du tier courant dans
     * la réponse encounters/raids. Stable d'un patch à l'autre : Blizzard
     * en met le contenu à jour, aucun identifiant à maintenir côté app.
     *
     * @pest-mutate-ignore
     */
    public const CURRENT_SEASON_EXPANSION_ID = 505;

    // @pest-mutate-ignore
    private const int UNKNOWN_DIFFICULTY_ORDER = 99;

    // @pest-mutate-ignore
    private const DIFFICULTY_ORDER = [
        'LFR' => 0,
        'NORMAL' => 1,
        'HEROIC' => 2,
        'MYTHIC' => 3,
    ];

    // @pest-mutate-ignore
    private const DIFFICULTY_LABELS = [
        'LFR' => 'LFR',
        'NORMAL' => 'Normal',
        'HEROIC' => 'Héroïque',
        'MYTHIC' => 'Mythique',
    ];

    /**
     * @param  RaidNameMap  $nameMap  Noms FR résolus via les données statiques journal-instance
     * @return list<RaidProgress>|null
     */
    public function aggregate(CharacterRaidsResponse $characterRaidsResponse, array $nameMap = []): ?array
    {
        $instances = $characterRaidsResponse->instancesOf(self::CURRENT_SEASON_EXPANSION_ID);

        if ($instances === null) {
            return null;
        }

        return array_map(
            fn (RaidInstance $raidInstance): array => $this->buildRaid($raidInstance, $nameMap),
            $instances,
        );
    }

    /**
     * @param  RaidNameMap  $nameMap
     * @return RaidProgress
     */
    private function buildRaid(RaidInstance $raidInstance, array $nameMap): array
    {
        $instanceId = $raidInstance->id ?? 0;
        $localizedNames = $nameMap[$instanceId] ?? [];
        $encounterNames = $localizedNames['encounters'] ?? [];

        $modes = array_map(
            fn (RaidMode $raidMode): array => $this->buildMode($raidMode, $encounterNames),
            $raidInstance->modes,
        );

        usort($modes, fn (array $a, array $b): int => $this->difficultyOrder($a['difficulty_type']) <=> $this->difficultyOrder($b['difficulty_type']));

        return [
            'instance_id' => $instanceId,
            'instance_name' => $this->preferLocalized($localizedNames['name'] ?? null, $raidInstance->name),
            'modes' => $modes,
        ];
    }

    /**
     * Privilégie le nom FR (données statiques) et retombe sur le nom brut de l'API profil.
     */
    private function preferLocalized(?string $localized, ?string $fallback): string
    {
        if ($localized !== null && $localized !== '') {
            return $localized;
        }

        return $fallback ?? '';
    }

    private function difficultyOrder(string $difficultyType): int
    {
        return self::DIFFICULTY_ORDER[$difficultyType] ?? self::UNKNOWN_DIFFICULTY_ORDER;
    }

    /**
     * @param  array<int, string>  $encounterNames
     * @return RaidModeView
     */
    private function buildMode(RaidMode $raidMode, array $encounterNames): array
    {
        $type = $raidMode->difficultyType ?? '';

        return [
            'difficulty_type' => $type,
            'difficulty_label' => self::DIFFICULTY_LABELS[$type] ?? $type,
            'completed_count' => $raidMode->completedCount ?? 0,
            'total_count' => $raidMode->totalCount ?? 0,
            'encounters' => array_map(
                fn (RaidEncounterProgress $raidEncounterProgress): array => $this->buildEncounter($raidEncounterProgress, $encounterNames),
                $raidMode->encounters,
            ),
        ];
    }

    /**
     * @param  array<int, string>  $encounterNames
     * @return RaidEncounterView
     */
    private function buildEncounter(RaidEncounterProgress $raidEncounterProgress, array $encounterNames): array
    {
        $encounterId = $raidEncounterProgress->id ?? 0;

        return [
            'id' => $encounterId,
            'name' => $this->preferLocalized($encounterNames[$encounterId] ?? null, $raidEncounterProgress->name),
            'last_kill_timestamp' => $raidEncounterProgress->lastKillTimestamp ?? 0,
        ];
    }
}
