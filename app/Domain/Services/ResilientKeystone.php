<?php

declare(strict_types=1);

namespace App\Domain\Services;

use App\Domain\Exceptions\InvalidKeystoneDataException;

/**
 * Resilient keystones: a character is resilient at level N once every dungeon of the season
 * has been completed in time at level N or above, from level 12 up.
 *
 * @phpstan-type KeystoneRunArray array{dungeon_id: int, level: int, is_timed: bool}
 * @phpstan-type ResilienceDungeonArray array{dungeon_id: int, name: string, best_timed_level: int|null}
 * @phpstan-type ResilienceTargetArray array{level: int, remaining: list<int>}
 * @phpstan-type ResilienceArray array{level: int|null, min_level: int, max_level: int, dungeons: list<ResilienceDungeonArray>, targets: list<ResilienceTargetArray>}
 */
class ResilientKeystone
{
    public const MIN_LEVEL = 12; // @pest-mutate-ignore

    public const MAX_LEVEL = 25; // @pest-mutate-ignore

    /**
     * @param  array<int, string>  $dungeonNames  dungeons of the season, name by identifier
     * @param  list<KeystoneRunArray>  $runs
     * @return ResilienceArray
     */
    public function assess(array $dungeonNames, array $runs): array
    {
        if ($dungeonNames === []) {
            throw InvalidKeystoneDataException::seasonWithoutDungeon();
        }

        $bestTimedLevels = $this->bestTimedLevels($dungeonNames, $runs);

        $dungeons = [];
        foreach ($dungeonNames as $dungeonId => $name) {
            $dungeons[] = ['dungeon_id' => $dungeonId, 'name' => $name, 'best_timed_level' => $bestTimedLevels[$dungeonId]];
        }

        $targets = [];
        for ($level = self::MIN_LEVEL; $level <= self::MAX_LEVEL; $level++) {
            $targets[] = ['level' => $level, 'remaining' => $this->remainingAt($level, $bestTimedLevels)];
        }

        return [
            'level' => $this->levelReached($bestTimedLevels),
            'min_level' => self::MIN_LEVEL,
            'max_level' => self::MAX_LEVEL,
            'dungeons' => $dungeons,
            'targets' => $targets,
        ];
    }

    /**
     * @param  array<int, string>  $dungeonNames
     * @param  list<KeystoneRunArray>  $runs
     * @return array<int, int|null>
     */
    private function bestTimedLevels(array $dungeonNames, array $runs): array
    {
        $bestTimedLevels = array_fill_keys(array_keys($dungeonNames), null);

        foreach ($runs as $run) {
            $dungeonId = $run['dungeon_id'];

            if ($run['level'] < 0) {
                throw InvalidKeystoneDataException::negativeLevel($dungeonId, $run['level']);
            }

            if (! $run['is_timed']) {
                continue;
            }

            if (! array_key_exists($dungeonId, $bestTimedLevels)) {
                continue;
            }

            $bestTimedLevels[$dungeonId] = max($bestTimedLevels[$dungeonId] ?? 0, $run['level']);
        }

        return $bestTimedLevels;
    }

    /**
     * @param  array<int, int|null>  $bestTimedLevels
     * @return list<int>
     */
    private function remainingAt(int $level, array $bestTimedLevels): array
    {
        return array_keys(array_filter(
            $bestTimedLevels,
            static fn (?int $bestTimedLevel): bool => $bestTimedLevel === null || $bestTimedLevel < $level,
        ));
    }

    /**
     * @param  array<int, int|null>  $bestTimedLevels
     */
    private function levelReached(array $bestTimedLevels): ?int
    {
        $lowest = self::MAX_LEVEL;

        foreach ($bestTimedLevels as $bestTimedLevel) {
            if ($bestTimedLevel === null || $bestTimedLevel < self::MIN_LEVEL) {
                return null;
            }

            $lowest = min($lowest, $bestTimedLevel);
        }

        return $lowest;
    }
}
