<?php

declare(strict_types=1);

namespace App\Application\Services\Progress;

use App\Infrastructure\Blizzard\Responses\Profile\CharacterReputationsResponse;
use App\Infrastructure\Reference\FactionReference;

/**
 * @phpstan-type FactionProgress array{id: int, name: string, standing_name: string, tier: int, value: int, max: int, raw: int, renown_level: int, completed: bool, started: bool, account_wide: bool}
 * @phpstan-type ReputationProgress array{total: int, completed: int, factions: list<FactionProgress>}
 */
class ReputationProgressAggregator
{
    // @pest-mutate-ignore
    private const int UNSTARTED_TIER = -1;

    private const string UNSTARTED_STANDING = 'Non commencée';

    public function __construct(
        private readonly FactionReference $factionReference,
    ) {}

    /**
     * @return array<int, ReputationProgress>
     */
    public function aggregate(CharacterReputationsResponse $characterReputationsResponse, string $characterFaction = ''): array
    {
        $factionExpansionMap = $this->factionReference->expansions();
        $maxRenownMap = $this->factionReference->maxRenownLevels();
        $factionNamesMap = $this->factionReference->names();
        $accountWideFactionIds = $this->factionReference->accountWideIds();
        $reputationFactionMap = $this->factionReference->factions();

        /** @var array<int, list<FactionProgress>> $grouped */
        $grouped = [];

        /** @var array<int, true> $startedFactionIds */
        $startedFactionIds = [];

        foreach ($characterReputationsResponse->standings as $reputationStanding) {
            $factionId = $reputationStanding->factionId ?? 0;
            if ($factionId === 0) {
                continue;
            }

            if (! isset($factionExpansionMap[$factionId])) {
                continue;
            }

            $expansionId = $factionExpansionMap[$factionId];

            $tier = $reputationStanding->tier ?? 0;
            $renownLevel = $reputationStanding->renownLevel ?? 0;
            $max = $reputationStanding->max ?? 0;

            $grouped[$expansionId][] = [
                'id' => $factionId,
                'name' => $reputationStanding->factionName ?? '',
                'standing_name' => $reputationStanding->standingName ?? '',
                'tier' => $tier,
                'value' => $reputationStanding->value ?? 0,
                'max' => $max,
                'raw' => $reputationStanding->raw ?? 0,
                'renown_level' => $renownLevel,
                'completed' => $this->isCompleted($factionId, $tier, $max, $renownLevel, $maxRenownMap),
                'started' => true,
                'account_wide' => $renownLevel > 0 || isset($accountWideFactionIds[$factionId]),
            ];

            $startedFactionIds[$factionId] = true;
        }

        // Collect started faction names to avoid adding duplicates with different IDs
        /** @var array<string, true> $startedFactionNames */
        $startedFactionNames = [];
        foreach ($grouped as $factions) {
            foreach ($factions as $faction) {
                $startedFactionNames[$faction['name']] = true;
            }
        }

        $unstartedNames = [];
        foreach ($factionExpansionMap as $factionId => $expansionId) {
            if (isset($startedFactionIds[$factionId])) {
                continue;
            }

            if ($this->isOppositeFaction($factionId, $characterFaction, $reputationFactionMap)) {
                continue;
            }

            $name = $factionNamesMap[$factionId] ?? '';

            // Skip if a faction with the same name is already started (duplicate faction ID)
            if (isset($startedFactionNames[$name])) {
                continue;
            }

            // Skip if an unstarted faction with the same name was already added
            if (isset($unstartedNames[$name])) {
                continue;
            }

            $unstartedNames[$name] = true;

            $grouped[$expansionId][] = [
                'id' => $factionId,
                'name' => $name,
                'standing_name' => self::UNSTARTED_STANDING,
                'tier' => self::UNSTARTED_TIER,
                'value' => 0,
                'max' => 0,
                'raw' => 0,
                'renown_level' => 0,
                'completed' => false,
                'started' => false,
                'account_wide' => isset($accountWideFactionIds[$factionId]),
            ];
        }

        return $this->buildExpansionProgress($grouped);
    }

    /**
     * @param  array<int, int>  $maxRenownMap
     */
    private function isCompleted(int $factionId, int $tier, int $max, int $renownLevel, array $maxRenownMap): bool
    {
        // Renown au cap : renown reste à max>0 par palier, on compare via DB2
        if ($renownLevel > 0 && isset($maxRenownMap[$factionId]) && $renownLevel >= $maxRenownMap[$factionId]) {
            return true;
        }

        // Tous les autres systèmes (exalted, friendship maxée, paragon, "Niveau X" Midnight) :
        // l'API renvoie max=0 quand la progression est au cap.
        return $max === 0 && $tier > 0;
    }

    /**
     * @param  array<int, string>  $reputationFactionMap
     */
    private function isOppositeFaction(int $factionId, string $characterFaction, array $reputationFactionMap): bool
    {
        if ($characterFaction === '') {
            return false;
        }

        $requiredFaction = $reputationFactionMap[$factionId] ?? null;

        return $requiredFaction !== null && $requiredFaction !== $characterFaction;
    }

    /**
     * @param  array<int, list<FactionProgress>>  $grouped
     * @return array<int, ReputationProgress>
     */
    private function buildExpansionProgress(array $grouped): array
    {
        $results = [];

        for ($i = 0; $i <= 11; $i++) {
            $factions = $grouped[$i] ?? [];
            $completed = 0;

            foreach ($factions as $faction) {
                if ($faction['completed'] === true) {
                    $completed++;
                }
            }

            $results[$i] = [
                'total' => count($factions),
                'completed' => $completed,
                'factions' => $factions,
            ];
        }

        return $results;
    }
}
