<?php

declare(strict_types=1);

use App\Domain\Exceptions\InvalidWeeklyActivityException;
use App\Domain\ValueObjects\RaidDifficulty;

test('it reads the difficulties Blizzard sends, in order from the easiest', function (): void {
    $difficulties = array_map(RaidDifficulty::fromBlizzard(...), ['LFR', 'NORMAL', 'HEROIC', 'MYTHIC']);

    expect($difficulties)->toBe([RaidDifficulty::Lfr, RaidDifficulty::Normal, RaidDifficulty::Heroic, RaidDifficulty::Mythic])
        ->and(array_map(fn (RaidDifficulty $raidDifficulty): int => $raidDifficulty->rank(), $difficulties))->toBe([0, 1, 2, 3]);
});

test('it refuses a difficulty it does not know', function (): void {
    RaidDifficulty::fromBlizzard('TIMEWALKING');
})->throws(InvalidWeeklyActivityException::class, 'Unknown raid difficulty TIMEWALKING');
