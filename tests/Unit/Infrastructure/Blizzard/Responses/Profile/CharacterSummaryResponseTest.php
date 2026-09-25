<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\Exceptions\UnexpectedFieldTypeException;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterSummaryResponse;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * @param  array<string, mixed>  $decoded
 */
function characterSummary(array $decoded): CharacterSummaryResponse
{
    return CharacterSummaryResponse::fromPayload(
        ResponsePayload::forEndpoint('profile/wow/character/hyjal/thrall', $decoded),
    );
}

test('a complete summary carries the identity shown on the character sheet', function (): void {
    $characterSummaryResponse = characterSummary([
        'name' => 'Thrall',
        'realm' => ['name' => 'Hyjal'],
        'race' => ['name' => 'Orc'],
        'character_class' => ['id' => 7, 'name' => 'Chaman'],
        'level' => 80,
        'equipped_item_level' => 620,
        'faction' => ['type' => 'HORDE', 'name' => 'Horde'],
        'guild' => ['name' => 'Les Loups'],
    ]);

    expect($characterSummaryResponse->name)->toBe('Thrall')
        ->and($characterSummaryResponse->realmName)->toBe('Hyjal')
        ->and($characterSummaryResponse->raceName)->toBe('Orc')
        ->and($characterSummaryResponse->classId)->toBe(7)
        ->and($characterSummaryResponse->className)->toBe('Chaman')
        ->and($characterSummaryResponse->level)->toBe(80)
        ->and($characterSummaryResponse->equippedItemLevel)->toBe(620)
        ->and($characterSummaryResponse->factionName)->toBe('Horde')
        ->and($characterSummaryResponse->guildName)->toBe('Les Loups');
});

test('a character without guild nor class data is a normal case', function (): void {
    $characterSummaryResponse = characterSummary(['name' => 'Thrall']);

    expect($characterSummaryResponse->guildName)->toBeNull()
        ->and($characterSummaryResponse->classId)->toBeNull()
        ->and($characterSummaryResponse->className)->toBeNull()
        ->and($characterSummaryResponse->realmName)->toBeNull()
        ->and($characterSummaryResponse->level)->toBeNull();
});

test('an empty summary reads as an unknown character', function (): void {
    expect(characterSummary([])->name)->toBeNull();
});

test('a level that is not an integer breaks the contract', function (): void {
    characterSummary(['level' => '80']);
})->throws(UnexpectedFieldTypeException::class, 'level');
