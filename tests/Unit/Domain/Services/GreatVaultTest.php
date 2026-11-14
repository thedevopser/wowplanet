<?php

declare(strict_types=1);

use App\Domain\Exceptions\InvalidWeeklyActivityException;
use App\Domain\Services\GreatVault;
use App\Domain\ValueObjects\RaidDifficulty;

/**
 * @return array{encounter_id: int, difficulty: RaidDifficulty}
 */
function kill(int $encounterId, RaidDifficulty $raidDifficulty): array
{
    return ['encounter_id' => $encounterId, 'difficulty' => $raidDifficulty];
}

describe('raid', function (): void {
    test('without a kill, no slot is reached', function (): void {
        expect((new GreatVault)->raid([]))->toBe([
            'count' => 0,
            'slots' => [
                ['threshold' => 2, 'reached' => false, 'reward' => null],
                ['threshold' => 4, 'reached' => false, 'reward' => null],
                ['threshold' => 6, 'reached' => false, 'reward' => null],
            ],
        ]);
    });

    test('the bosses beaten fill the slots at 2, 4 and 6', function (): void {
        $kills = array_map(fn (int $encounterId): array => kill($encounterId, RaidDifficulty::Normal), [1, 2, 3, 4, 5]);

        $raid = (new GreatVault)->raid($kills);

        expect($raid['count'])->toBe(5)
            ->and(array_column($raid['slots'], 'reached'))->toBe([true, true, false]);
    });

    test('each slot is worth the difficulty of its boss, the bosses ranked from the hardest down', function (): void {
        $raid = (new GreatVault)->raid([
            kill(1, RaidDifficulty::Normal),
            kill(2, RaidDifficulty::Mythic),
            kill(3, RaidDifficulty::Heroic),
            kill(4, RaidDifficulty::Lfr),
            kill(5, RaidDifficulty::Heroic),
            kill(6, RaidDifficulty::Normal),
        ]);

        expect(array_column($raid['slots'], 'reward'))->toBe([RaidDifficulty::Heroic, RaidDifficulty::Normal, RaidDifficulty::Lfr]);
    });

    test('a boss beaten in two difficulties counts once, at the higher one', function (array $kills): void {
        $raid = (new GreatVault)->raid($kills);

        expect($raid['count'])->toBe(2)
            ->and($raid['slots'][0])->toBe(['threshold' => 2, 'reached' => true, 'reward' => RaidDifficulty::Heroic]);
    })->with([
        'the easier kill first' => [[kill(1, RaidDifficulty::Normal), kill(1, RaidDifficulty::Heroic), kill(2, RaidDifficulty::Heroic)]],
        'the harder kill first' => [[kill(1, RaidDifficulty::Heroic), kill(1, RaidDifficulty::Normal), kill(2, RaidDifficulty::Heroic)]],
    ]);
});

describe('mythic plus', function (): void {
    test('without a key, no slot is reached', function (): void {
        expect((new GreatVault)->mythicPlus([]))->toBe([
            'count' => 0,
            'slots' => [
                ['threshold' => 1, 'reached' => false, 'reward' => null],
                ['threshold' => 4, 'reached' => false, 'reward' => null],
                ['threshold' => 8, 'reached' => false, 'reward' => null],
            ],
        ]);
    });

    test('each slot is worth the level of its key, the keys ranked from the highest down', function (): void {
        $mythicPlus = (new GreatVault)->mythicPlus([7, 12, 10, 9, 15]);

        expect($mythicPlus['count'])->toBe(5)
            ->and($mythicPlus['slots'])->toBe([
                ['threshold' => 1, 'reached' => true, 'reward' => 15],
                ['threshold' => 4, 'reached' => true, 'reward' => 9],
                ['threshold' => 8, 'reached' => false, 'reward' => null],
            ]);
    });

    test('eight keys fill every slot', function (): void {
        $mythicPlus = (new GreatVault)->mythicPlus([2, 3, 4, 5, 6, 7, 8, 9]);

        expect(array_column($mythicPlus['slots'], 'reward'))->toBe([9, 6, 2]);
    });

    test('a key level under 2 does not exist', function (): void {
        (new GreatVault)->mythicPlus([10, 1]);
    })->throws(InvalidWeeklyActivityException::class, 'Mythic+ key level 1 is under 2');
});
