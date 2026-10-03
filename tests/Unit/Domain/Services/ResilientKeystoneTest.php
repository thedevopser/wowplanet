<?php

declare(strict_types=1);

use App\Domain\Exceptions\InvalidKeystoneDataException;
use App\Domain\Services\ResilientKeystone;

const SEASON_DUNGEONS = [
    249 => 'Repos des rois',
    250 => 'Temple de Sephraliss',
    399 => 'Bassins de l’Essence rubis',
    584 => 'Le val Aveuglant',
    585 => 'Arène de la Cicatrice du Vide',
    586 => 'Antre de Nalorakk',
    587 => 'Allée du meurtre',
    588 => 'Autel des crochets',
];

/**
 * @return array{dungeon_id: int, level: int, is_timed: bool}
 */
function keystoneRun(int $dungeonId, int $level, bool $timed = true): array
{
    return ['dungeon_id' => $dungeonId, 'level' => $level, 'is_timed' => $timed];
}

/**
 * One timed run per dungeon of the season, at the given level unless overridden.
 *
 * @param  array<int, int>  $overrides
 * @return list<array{dungeon_id: int, level: int, is_timed: bool}>
 */
function everyDungeonTimedAt(int $level, array $overrides = []): array
{
    return array_map(
        static fn (int $dungeonId): array => keystoneRun($dungeonId, $overrides[$dungeonId] ?? $level),
        array_keys(SEASON_DUNGEONS),
    );
}

/**
 * @param  array{targets: list<array{level: int, remaining: list<int>}>}  $resilience
 * @return list<int>
 */
function remainingAt(array $resilience, int $level): array
{
    foreach ($resilience['targets'] as $target) {
        if ($target['level'] === $level) {
            return $target['remaining'];
        }
    }

    throw new LogicException(sprintf('No target at level %d.', $level));
}

beforeEach(function (): void {
    $this->resilientKeystone = new ResilientKeystone;
});

test('a character without any run has no resilience and every dungeon left at every level', function (): void {
    $resilience = $this->resilientKeystone->assess(SEASON_DUNGEONS, []);

    expect($resilience['level'])->toBeNull()
        ->and($resilience['dungeons'])->toHaveCount(8)
        ->and($resilience['dungeons'][0])->toBe(['dungeon_id' => 249, 'name' => 'Repos des rois', 'best_timed_level' => null])
        ->and(array_column($resilience['dungeons'], 'best_timed_level'))->toBe(array_fill(0, 8, null))
        ->and(remainingAt($resilience, 12))->toBe(array_keys(SEASON_DUNGEONS))
        ->and(remainingAt($resilience, 25))->toBe(array_keys(SEASON_DUNGEONS));
});

test('the bounds and one target per level from 12 to 25 are given, in rising order', function (): void {
    $resilience = $this->resilientKeystone->assess(SEASON_DUNGEONS, []);

    expect($resilience['min_level'])->toBe(12)
        ->and($resilience['max_level'])->toBe(25)
        ->and(array_column($resilience['targets'], 'level'))->toBe(range(12, 25));
});

test('seven dungeons out of eight timed at 12 give no resilience and one dungeon left', function (): void {
    $runs = array_slice(everyDungeonTimedAt(12), 0, 7);

    $resilience = $this->resilientKeystone->assess(SEASON_DUNGEONS, $runs);

    expect($resilience['level'])->toBeNull()
        ->and(remainingAt($resilience, 12))->toBe([588]);
});

test('every dungeon timed at exactly 12 gives resilience 12', function (): void {
    $resilience = $this->resilientKeystone->assess(SEASON_DUNGEONS, everyDungeonTimedAt(12));

    expect($resilience['level'])->toBe(12)
        ->and(remainingAt($resilience, 12))->toBe([])
        ->and(remainingAt($resilience, 13))->toBe(array_keys(SEASON_DUNGEONS));
});

test('the resilience is the lowest of the best timed levels', function (): void {
    $resilience = $this->resilientKeystone->assess(SEASON_DUNGEONS, everyDungeonTimedAt(15, [586 => 13]));

    expect($resilience['level'])->toBe(13)
        ->and(remainingAt($resilience, 13))->toBe([])
        ->and(remainingAt($resilience, 14))->toBe([586])
        ->and(remainingAt($resilience, 15))->toBe([586])
        ->and(remainingAt($resilience, 16))->toBe(array_keys(SEASON_DUNGEONS));
});

test('a single dungeon timed at 11 at best keeps the character out of resilience', function (): void {
    $resilience = $this->resilientKeystone->assess(SEASON_DUNGEONS, everyDungeonTimedAt(18, [250 => 11]));

    expect($resilience['level'])->toBeNull()
        ->and(remainingAt($resilience, 12))->toBe([250]);
});

test('a run out of time never counts, even above the target', function (): void {
    $runs = [keystoneRun(399, 14, timed: false), ...everyDungeonTimedAt(13, [399 => 11])];

    $resilience = $this->resilientKeystone->assess(SEASON_DUNGEONS, $runs);

    expect($resilience['level'])->toBeNull()
        ->and($resilience['dungeons'][2])->toBe(['dungeon_id' => 399, 'name' => 'Bassins de l’Essence rubis', 'best_timed_level' => 11])
        ->and(remainingAt($resilience, 12))->toBe([399]);
});

test('the highest timed run of a dungeon is kept, whatever the order of the runs', function (array $levels): void {
    $runs = array_map(static fn (int $level): array => keystoneRun(249, $level), $levels);

    $resilience = $this->resilientKeystone->assess(SEASON_DUNGEONS, $runs);

    expect($resilience['dungeons'][0]['best_timed_level'])->toBe(16);
})->with([
    'rising' => [[12, 14, 16]],
    'falling' => [[16, 14, 12]],
    'mixed' => [[14, 16, 12]],
]);

test('a run of a dungeon outside the season is ignored', function (): void {
    $runs = [keystoneRun(9999, 20), ...everyDungeonTimedAt(12)];

    $resilience = $this->resilientKeystone->assess(SEASON_DUNGEONS, $runs);

    expect($resilience['level'])->toBe(12)
        ->and(array_column($resilience['dungeons'], 'dungeon_id'))->toBe(array_keys(SEASON_DUNGEONS));
});

test('the resilience never goes above the ceiling', function (int $level): void {
    $resilience = $this->resilientKeystone->assess(SEASON_DUNGEONS, everyDungeonTimedAt($level));

    expect($resilience['level'])->toBe(25)
        ->and(remainingAt($resilience, 25))->toBe([])
        ->and($resilience['dungeons'][0]['best_timed_level'])->toBe($level);
})->with([25, 26, 31]);

test('the dungeons left follow the order of the season', function (): void {
    $resilience = $this->resilientKeystone->assess([588 => 'Autel des crochets', 249 => 'Repos des rois', 399 => 'Bassins'], [keystoneRun(249, 12)]);

    expect(array_column($resilience['dungeons'], 'dungeon_id'))->toBe([588, 249, 399])
        ->and(remainingAt($resilience, 12))->toBe([588, 399]);
});

test('a season without any dungeon is refused', function (): void {
    $this->resilientKeystone->assess([], [keystoneRun(249, 12)]);
})->throws(InvalidKeystoneDataException::class, 'The season has no dungeon.');

test('a run with a negative level is refused', function (): void {
    $this->resilientKeystone->assess(SEASON_DUNGEONS, [keystoneRun(249, -1)]);
})->throws(InvalidKeystoneDataException::class, 'Keystone level -1 of dungeon 249 is negative.');

test('a run at level zero is accepted and counts for nothing', function (): void {
    $resilience = $this->resilientKeystone->assess(SEASON_DUNGEONS, [keystoneRun(249, 0)]);

    expect($resilience['dungeons'][0]['best_timed_level'])->toBe(0)
        ->and(remainingAt($resilience, 12))->toContain(249);
});
