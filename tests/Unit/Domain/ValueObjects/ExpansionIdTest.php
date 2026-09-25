<?php

declare(strict_types=1);

use App\Domain\ValueObjects\ExpansionId;

test('it creates valid expansion ids', function (int $value, string $expectedName): void {
    $expansionId = new ExpansionId($value);

    expect($expansionId->value)->toBe($value)
        ->and($expansionId->toString())->toBe($expectedName);
})->with([
    'Classic' => [0, 'Classic'],
    'Burning Crusade' => [1, 'The Burning Crusade'],
    'Wrath of the Lich King' => [2, 'Wrath of the Lich King'],
    'Cataclysm' => [3, 'Cataclysm'],
    'Mists of Pandaria' => [4, 'Mists of Pandaria'],
    'Warlords of Draenor' => [5, 'Warlords of Draenor'],
    'Legion' => [6, 'Legion'],
    'Battle for Azeroth' => [7, 'Battle for Azeroth'],
    'Shadowlands' => [8, 'Shadowlands'],
    'Dragonflight' => [9, 'Dragonflight'],
    'The War Within' => [10, 'The War Within'],
    'Midnight' => [11, 'Midnight'],
]);

test('it creates the unclassified bucket', function (): void {
    $expansionId = new ExpansionId(ExpansionId::UNCLASSIFIED);

    expect($expansionId->value)->toBe(99)
        ->and($expansionId->toString())->toBe('Non classé')
        ->and($expansionId->toSlug())->toBe('non-classe')
        ->and($expansionId->toOrdinal())->toBe('aucune extension');
});

test('the unclassified bucket is reachable by its slug and comes last', function (): void {
    expect(ExpansionId::fromSlug('non-classe')?->value)->toBe(ExpansionId::UNCLASSIFIED)
        ->and(array_key_last(ExpansionId::allSlugs()))->toBe(ExpansionId::UNCLASSIFIED);
});

test('it finds no expansion for an unknown slug', function (): void {
    expect(ExpansionId::fromSlug('warcraft-4'))->toBeNull();
});

test('it rejects invalid values', function (int $value): void {
    expect(fn (): \App\Domain\ValueObjects\ExpansionId => new ExpansionId($value))->toThrow(
        InvalidArgumentException::class,
        'Invalid Expansion ID: '.$value,
    );
})->with([
    'negative' => [-1],
    'too high' => [12],
    'way too high' => [999],
]);

test('it uses correct constants', function (): void {
    expect(ExpansionId::CLASSIC)->toBe(0)
        ->and(ExpansionId::BURNING_CRUSADE)->toBe(1)
        ->and(ExpansionId::THE_WAR_WITHIN)->toBe(10)
        ->and(ExpansionId::MIDNIGHT)->toBe(11);
});

test('it exposes a French ordinal label for every expansion', function (): void {
    expect((new ExpansionId(ExpansionId::CLASSIC))->toOrdinal())->toBe('le jeu original')
        ->and((new ExpansionId(ExpansionId::BURNING_CRUSADE))->toOrdinal())->toBe('la 1re extension')
        ->and((new ExpansionId(ExpansionId::WRATH_OF_THE_LICH_KING))->toOrdinal())->toBe('la 2e extension')
        ->and((new ExpansionId(ExpansionId::CATACLYSM))->toOrdinal())->toBe('la 3e extension')
        ->and((new ExpansionId(ExpansionId::MISTS_OF_PANDARIA))->toOrdinal())->toBe('la 4e extension')
        ->and((new ExpansionId(ExpansionId::WARLORDS_OF_DRAENOR))->toOrdinal())->toBe('la 5e extension')
        ->and((new ExpansionId(ExpansionId::LEGION))->toOrdinal())->toBe('la 6e extension')
        ->and((new ExpansionId(ExpansionId::BATTLE_FOR_AZEROTH))->toOrdinal())->toBe('la 7e extension')
        ->and((new ExpansionId(ExpansionId::SHADOWLANDS))->toOrdinal())->toBe('la 8e extension')
        ->and((new ExpansionId(ExpansionId::DRAGONFLIGHT))->toOrdinal())->toBe('la 9e extension')
        ->and((new ExpansionId(ExpansionId::THE_WAR_WITHIN))->toOrdinal())->toBe('la 10e extension')
        ->and((new ExpansionId(ExpansionId::MIDNIGHT))->toOrdinal())->toBe('la 11e extension');
});
