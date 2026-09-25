<?php

declare(strict_types=1);

use App\Infrastructure\Reference\RaceMask;

/**
 * Les deux masques complets mesurés sur le build courant, recombinés depuis les
 * deux moitiés que Blizzard sert désormais.
 */
const ALLIANCE_LOW = -1321907123;
const ALLIANCE_HIGH = 1427461461;
const HORDE_LOW = 1309324210;
const HORDE_HIGH = -1440044374;

test('it recombines the two halves Blizzard splits a race mask into', function (): void {
    expect(RaceMask::combine(ALLIANCE_LOW, ALLIANCE_HIGH))->toBe(6130900294268439629)
        ->and(RaceMask::combine(HORDE_LOW, HORDE_HIGH))->toBe(-6184943489809468494);
});

test('it treats the first half as the low bits and the second as the high bits', function (): void {
    expect(RaceMask::combine(1, 0))->toBe(1)
        ->and(RaceMask::combine(0, 1))->toBe(4294967296);
});

test('it recombines two halves of all bits set into all bits set', function (): void {
    expect(RaceMask::combine(-1, -1))->toBe(-1);
});

test('it cannot recombine a mask whose halves are not both known', function (?int $low, ?int $high): void {
    expect(RaceMask::combine($low, $high))->toBeNull();
})->with([
    'low half missing' => [null, 0],
    'high half missing' => [0, null],
    'both missing' => [null, null],
]);

test('it reads the Alliance faction off the recombined mask', function (): void {
    expect(RaceMask::faction(ALLIANCE_LOW, ALLIANCE_HIGH))->toBe('Alliance');
});

test('it reads the Horde faction off the recombined mask', function (): void {
    expect(RaceMask::faction(HORDE_LOW, HORDE_HIGH))->toBe('Horde');
});

test('it needs both halves to recognise a standard mask, a single half naming no faction', function (): void {
    expect(RaceMask::faction(0, ALLIANCE_HIGH))->toBeNull()
        ->and(RaceMask::faction(0, HORDE_HIGH))->toBeNull();
});

test('it reads no faction from a mask that restricts nothing', function (?int $low, ?int $high): void {
    expect(RaceMask::faction($low, $high))->toBeNull();
})->with([
    'every race allowed' => [-1, -1],
    'no race at all' => [0, 0],
    'unknown halves' => [null, null],
]);

test('it reads a faction from a partial mask whose races all belong to one side', function (): void {
    // Bit 21 = Worgen (race 22), Alliance seule.
    expect(RaceMask::faction(1 << 21, 0))->toBe('Alliance')
        // Bit 9 = Elfe de sang (race 10), Horde seule.
        ->and(RaceMask::faction(1 << 9, 0))->toBe('Horde');
});

test('it reads no faction from a partial mask that mixes both sides', function (): void {
    expect(RaceMask::faction((1 << 21) | (1 << 9), 0))->toBeNull();
});

test('it reads no faction from a partial mask of races it does not place', function (): void {
    // Bit 14 = race 15, qui n'appartient à aucune des deux listes connues.
    expect(RaceMask::faction(1 << 14, 0))->toBeNull();
});

test('it reads no faction from a negative mask it does not recognise', function (): void {
    expect(RaceMask::faction(-2, -1))->toBeNull();
});
