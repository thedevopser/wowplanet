<?php

declare(strict_types=1);

namespace App\Domain\Services;

use App\Domain\Exceptions\InvalidWeeklyActivityException;
use App\Domain\ValueObjects\RaidDifficulty;

/**
 * Les paliers de la Grande Chambre forte, calculés comme le jeu : en raid, 2, 4 et 6 boss distincts
 * battus dans la semaine ; en Mythique+, 1, 4 et 8 clés. Un palier vaut ce que vaut son n-ième
 * meilleur boss ou sa n-ième meilleure clé.
 *
 * @phpstan-type RaidKill array{encounter_id: int, difficulty: RaidDifficulty}
 * @phpstan-type RaidSlot array{threshold: int, reached: bool, reward: RaidDifficulty|null}
 * @phpstan-type MythicPlusSlot array{threshold: int, reached: bool, reward: int|null}
 * @phpstan-type RaidVault array{count: int, slots: list<RaidSlot>}
 * @phpstan-type MythicPlusVault array{count: int, slots: list<MythicPlusSlot>}
 */
final readonly class GreatVault
{
    /** @var list<int> */
    public const array RAID_THRESHOLDS = [2, 4, 6]; // @pest-mutate-ignore

    /** @var list<int> */
    public const array MYTHIC_PLUS_THRESHOLDS = [1, 4, 8]; // @pest-mutate-ignore

    public const int LOWEST_KEY_LEVEL = 2; // @pest-mutate-ignore

    /**
     * @param  list<RaidKill>  $kills
     * @return RaidVault
     */
    public function raid(array $kills): array
    {
        $hardestByEncounter = [];
        foreach ($kills as $kill) {
            $known = $hardestByEncounter[$kill['encounter_id']] ?? null;
            $hardestByEncounter[$kill['encounter_id']] = $this->harder($known, $kill['difficulty']);
        }

        $difficulties = array_values($hardestByEncounter);
        usort($difficulties, static fn (RaidDifficulty $first, RaidDifficulty $second): int => $second->rank() <=> $first->rank());

        return ['count' => count($difficulties), 'slots' => $this->slots(self::RAID_THRESHOLDS, $difficulties)];
    }

    /**
     * @param  list<int>  $keyLevels  le niveau de chaque clé terminée dans la semaine
     * @return MythicPlusVault
     */
    public function mythicPlus(array $keyLevels): array
    {
        foreach ($keyLevels as $keyLevel) {
            $this->guardKeyLevel($keyLevel);
        }

        rsort($keyLevels);

        return ['count' => count($keyLevels), 'slots' => $this->slots(self::MYTHIC_PLUS_THRESHOLDS, $keyLevels)];
    }

    /**
     * @template TReward of RaidDifficulty|int
     *
     * @param  list<int>  $thresholds
     * @param  list<TReward>  $rankedRewards  du meilleur au moins bon
     * @return list<array{threshold: int, reached: bool, reward: TReward|null}>
     */
    private function slots(array $thresholds, array $rankedRewards): array
    {
        return array_map(static fn (int $threshold): array => [
            'threshold' => $threshold,
            'reached' => count($rankedRewards) >= $threshold,
            'reward' => $rankedRewards[$threshold - 1] ?? null,
        ], $thresholds);
    }

    private function harder(?RaidDifficulty $known, RaidDifficulty $raidDifficulty): RaidDifficulty
    {
        if (! $known instanceof RaidDifficulty) {
            return $raidDifficulty;
        }

        return $raidDifficulty->rank() > $known->rank() ? $raidDifficulty : $known;
    }

    private function guardKeyLevel(int $keyLevel): void
    {
        if ($keyLevel < self::LOWEST_KEY_LEVEL) {
            throw InvalidWeeklyActivityException::keyLevelTooLow($keyLevel, self::LOWEST_KEY_LEVEL);
        }
    }
}
