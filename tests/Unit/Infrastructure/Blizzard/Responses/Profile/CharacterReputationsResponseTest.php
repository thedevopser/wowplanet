<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\Profile\CharacterReputationsResponse;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * @param  array<string, mixed>  $decoded
 */
function characterReputations(array $decoded): CharacterReputationsResponse
{
    return CharacterReputationsResponse::fromPayload(ResponsePayload::forEndpoint('profile/wow/character/hyjal/thrall/reputations', $decoded));
}

test('a standing carries its faction and every progress counter', function (): void {
    $standing = characterReputations(['reputations' => [[
        'faction' => ['id' => 2590, 'name' => 'Conseil de Dornogal'],
        'standing' => ['raw' => 50000, 'value' => 10, 'max' => 2500, 'tier' => 0, 'name' => 'Renom 20', 'renown_level' => 20],
    ]]])->standings[0];

    expect($standing->factionId)->toBe(2590)
        ->and($standing->factionName)->toBe('Conseil de Dornogal')
        ->and($standing->standingName)->toBe('Renom 20')
        ->and($standing->tier)->toBe(0)
        ->and($standing->value)->toBe(10)
        ->and($standing->max)->toBe(2500)
        ->and($standing->raw)->toBe(50000)
        ->and($standing->renownLevel)->toBe(20);
});

test('a standing without faction nor counters leaves them null', function (): void {
    $standing = characterReputations(['reputations' => [[]]])->standings[0];

    expect($standing->factionId)->toBeNull()
        ->and($standing->factionName)->toBeNull()
        ->and($standing->tier)->toBeNull()
        ->and($standing->renownLevel)->toBeNull();
});

test('a character without reputation has no standing', function (): void {
    expect(characterReputations([])->standings)->toBe([]);
});
