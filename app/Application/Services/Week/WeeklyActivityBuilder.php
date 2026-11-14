<?php

declare(strict_types=1);

namespace App\Application\Services\Week;

use App\Application\Services\Progress\RaidProgressAggregator;
use App\Domain\Services\GreatVault;
use App\Domain\Services\WeeklyReset;
use App\Domain\ValueObjects\RaidDifficulty;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterRaidsResponse;
use App\Infrastructure\Blizzard\Responses\Profile\MythicKeystoneProfileResponse;
use App\Infrastructure\Blizzard\Responses\Profile\MythicRun;
use App\Infrastructure\Blizzard\Responses\Profile\RaidEncounterProgress;
use App\Infrastructure\Blizzard\Responses\Profile\RaidInstance;
use App\Infrastructure\Blizzard\Responses\Profile\RaidMode;
use DateTimeImmutable;

/**
 * Ce qu'il reste à faire à chaque personnage du compte avant la remise à zéro : ses paliers de la
 * Grande Chambre forte, les boss encore disponibles et les donjons sans clé cette semaine.
 *
 * Pur : il reçoit les réponses Blizzard de chaque personnage, le journal des raids de la saison et
 * ses donjons, et l'instant présent.
 *
 * @phpstan-import-type RaidKill from GreatVault
 * @phpstan-import-type RaidVault from GreatVault
 * @phpstan-import-type MythicPlusVault from GreatVault
 *
 * @phpstan-type WeeklyCharacterInput array{name: string, realm: string, realmSlug: string, level: int, classId: int, raids: CharacterRaidsResponse|null, mythicKeystone: MythicKeystoneProfileResponse|null}
 * @phpstan-type RaidJournal array<int, array{name: string, encounters: array<int, string>}>
 * @phpstan-type RemainingDifficulty array{difficulty: string, encounters: list<string>}
 * @phpstan-type RemainingRaid array{instanceId: int, name: string, difficulties: list<RemainingDifficulty>}
 * @phpstan-type WeeklyRaid array{count: int, slots: RaidVault['slots'], remaining: list<RemainingRaid>}
 * @phpstan-type WeeklyMythicPlus array{count: int, slots: MythicPlusVault['slots'], missingDungeons: list<string>}
 * @phpstan-type WeeklyCharacter array{name: string, realm: string, realmSlug: string, level: int, classId: int, raid: WeeklyRaid, mythicPlus: WeeklyMythicPlus, nextSlotGap: int|null}
 * @phpstan-type WeeklyActivity array{characters: list<WeeklyCharacter>, belowMaxLevel: int, maxLevel: int}
 */
final readonly class WeeklyActivityBuilder
{
    public function __construct(
        private WeeklyReset $weeklyReset,
        private GreatVault $greatVault,
    ) {}

    /**
     * @param  list<WeeklyCharacterInput>  $characters
     * @param  RaidJournal  $raidJournal  les raids de la saison et tous leurs boss, en français
     * @param  array<int, string>  $seasonDungeons  les donjons de la saison, nom par identifiant
     * @return WeeklyActivity
     */
    public function build(array $characters, array $raidJournal, array $seasonDungeons, DateTimeImmutable $now): array
    {
        $maxLevel = max([0, ...array_column($characters, 'level')]);
        $shown = array_values(array_filter($characters, static fn (array $character): bool => $character['level'] === $maxLevel));

        $weeks = array_map(fn (array $character): array => $this->week($character, $raidJournal, $seasonDungeons, $now), $shown);
        usort($weeks, $this->closestSlotFirst(...));

        return ['characters' => $weeks, 'belowMaxLevel' => count($characters) - count($shown), 'maxLevel' => $maxLevel];
    }

    /**
     * @param  WeeklyCharacterInput  $character
     * @param  RaidJournal  $raidJournal
     * @param  array<int, string>  $seasonDungeons
     * @return WeeklyCharacter
     */
    private function week(array $character, array $raidJournal, array $seasonDungeons, DateTimeImmutable $now): array
    {
        $kills = $this->killsThisWeek($character['raids'], $now);
        $runs = $character['mythicKeystone']->currentPeriodRuns ?? [];

        $raid = [...$this->greatVault->raid($kills), 'remaining' => $this->remainingRaids($raidJournal, $kills)];
        $mythicPlus = [...$this->greatVault->mythicPlus($this->keyLevels($runs)), 'missingDungeons' => $this->missingDungeons($seasonDungeons, $runs)];

        return [
            'name' => $character['name'],
            'realm' => $character['realm'],
            'realmSlug' => $character['realmSlug'],
            'level' => $character['level'],
            'classId' => $character['classId'],
            'raid' => $raid,
            'mythicPlus' => $mythicPlus,
            'nextSlotGap' => $this->nextSlotGap([...$this->gaps($raid['slots'], $raid['count']), ...$this->gaps($mythicPlus['slots'], $mythicPlus['count'])]),
        ];
    }

    /**
     * @return list<RaidKill>
     */
    private function killsThisWeek(?CharacterRaidsResponse $characterRaidsResponse, DateTimeImmutable $now): array
    {
        $instances = $characterRaidsResponse?->instancesOf(RaidProgressAggregator::CURRENT_SEASON_EXPANSION_ID) ?? [];
        $modes = array_merge(...array_map(static fn (RaidInstance $raidInstance): array => $raidInstance->modes, $instances));

        return array_merge(...array_map(fn (RaidMode $raidMode): array => $this->killsOf($raidMode, $now), $modes));
    }

    /**
     * Une difficulté que Blizzard enverrait sans que le site la connaisse est laissée de côté
     * plutôt que de faire échouer la page.
     *
     * @return list<RaidKill>
     */
    private function killsOf(RaidMode $raidMode, DateTimeImmutable $now): array
    {
        $raidDifficulty = RaidDifficulty::tryFrom($raidMode->difficultyType ?? '');

        if (! $raidDifficulty instanceof RaidDifficulty) {
            return [];
        }

        $thisWeek = array_filter(
            $raidMode->encounters,
            fn (RaidEncounterProgress $raidEncounterProgress): bool => $this->weeklyReset->isThisWeek($raidEncounterProgress->lastKillTimestamp ?? 0, $now),
        );

        return array_values(array_map(
            static fn (RaidEncounterProgress $raidEncounterProgress): array => ['encounter_id' => $raidEncounterProgress->id ?? 0, 'difficulty' => $raidDifficulty],
            $thisWeek,
        ));
    }

    /**
     * @param  RaidJournal  $raidJournal
     * @param  list<RaidKill>  $kills
     * @return list<RemainingRaid>
     */
    private function remainingRaids(array $raidJournal, array $kills): array
    {
        $remaining = [];
        foreach ($raidJournal as $instanceId => $raid) {
            $remaining[] = ['instanceId' => $instanceId, 'name' => $raid['name'], 'difficulties' => $this->remainingDifficulties($raid['encounters'], $kills)];
        }

        return array_values(array_filter($remaining, static fn (array $raid): bool => $raid['difficulties'] !== []));
    }

    /**
     * @param  array<int, string>  $encounters
     * @param  list<RaidKill>  $kills
     * @return list<RemainingDifficulty>
     */
    private function remainingDifficulties(array $encounters, array $kills): array
    {
        $remaining = array_map(fn (RaidDifficulty $raidDifficulty): array => [
            'difficulty' => $raidDifficulty->value,
            'encounters' => $this->notBeatenIn($raidDifficulty, $encounters, $kills),
        ], RaidDifficulty::cases());

        return array_values(array_filter($remaining, static fn (array $difficulty): bool => $difficulty['encounters'] !== []));
    }

    /**
     * @param  array<int, string>  $encounters
     * @param  list<RaidKill>  $kills
     * @return list<string>
     */
    private function notBeatenIn(RaidDifficulty $raidDifficulty, array $encounters, array $kills): array
    {
        $beaten = array_column(array_filter($kills, static fn (array $kill): bool => $kill['difficulty'] === $raidDifficulty), 'encounter_id');

        return array_values(array_diff_key($encounters, array_flip($beaten)));
    }

    /**
     * @param  list<MythicRun>  $runs
     * @return list<int>
     */
    private function keyLevels(array $runs): array
    {
        return array_values(array_filter(array_map(static fn (MythicRun $mythicRun): ?int => $mythicRun->keystoneLevel, $runs), is_int(...)));
    }

    /**
     * @param  array<int, string>  $seasonDungeons
     * @param  list<MythicRun>  $runs
     * @return list<string>
     */
    private function missingDungeons(array $seasonDungeons, array $runs): array
    {
        $done = array_map(static fn (MythicRun $mythicRun): ?int => $mythicRun->dungeonId, $runs);

        return array_values(array_diff_key($seasonDungeons, array_flip(array_filter($done, is_int(...)))));
    }

    /**
     * Le plus petit nombre de boss ou de donjons qui manque pour atteindre un palier ; nul quand
     * toute la chambre forte est remplie.
     *
     * @param  list<int>  $gaps
     */
    private function nextSlotGap(array $gaps): ?int
    {
        return $gaps === [] ? null : min($gaps);
    }

    /**
     * @param  list<array{threshold: int, reached: bool, reward: RaidDifficulty|int|null}>  $slots
     * @return list<int>
     */
    private function gaps(array $slots, int $count): array
    {
        $open = array_filter($slots, static fn (array $slot): bool => ! $slot['reached']);

        return array_values(array_map(static fn (array $slot): int => $slot['threshold'] - $count, $open));
    }

    /**
     * @param  WeeklyCharacter  $first
     * @param  WeeklyCharacter  $second
     */
    private function closestSlotFirst(array $first, array $second): int
    {
        return ($first['nextSlotGap'] ?? PHP_INT_MAX) <=> ($second['nextSlotGap'] ?? PHP_INT_MAX)
            ?: $this->reachedSlots($second) <=> $this->reachedSlots($first)
            ?: $first['name'] <=> $second['name'];
    }

    /**
     * @param  WeeklyCharacter  $weeklyCharacter
     */
    private function reachedSlots(array $weeklyCharacter): int
    {
        $slots = [...$weeklyCharacter['raid']['slots'], ...$weeklyCharacter['mythicPlus']['slots']];

        return count(array_filter($slots, static fn (array $slot): bool => $slot['reached']));
    }
}
