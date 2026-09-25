<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\Profile\CharacterRaidsResponse;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * @param  array<string, mixed>  $decoded
 */
function characterRaids(array $decoded): CharacterRaidsResponse
{
    return CharacterRaidsResponse::fromPayload(
        ResponsePayload::forEndpoint('profile/wow/character/hyjal/thrall/encounters/raids', $decoded),
    );
}

test('it lists the instances of an expansion', function (): void {
    $characterRaidsResponse = characterRaids(['expansions' => [
        ['expansion' => ['id' => 516], 'instances' => [['instance' => ['id' => 1300]]]],
        ['expansion' => ['id' => 505], 'instances' => [['instance' => ['id' => 1307]], ['instance' => ['id' => 1308]]]],
    ]]);

    expect($characterRaidsResponse->instanceIdsOf(505))->toBe([1307, 1308])
        ->and($characterRaidsResponse->instanceIdsOf(516))->toBe([1300]);
});

test('the first occurrence of an expansion wins', function (): void {
    $characterRaidsResponse = characterRaids(['expansions' => [
        ['expansion' => ['id' => 505], 'instances' => [['instance' => ['id' => 1307]]]],
        ['expansion' => ['id' => 505], 'instances' => [['instance' => ['id' => 9999]]]],
    ]]);

    expect($characterRaidsResponse->instanceIdsOf(505))->toBe([1307]);
});

test('an instance without a usable id is skipped', function (): void {
    expect(characterRaids(['expansions' => [['expansion' => ['id' => 505], 'instances' => [['instance' => []], ['modes' => []]]]]])->instanceIdsOf(505))
        ->toBe([]);
});

test('an expansion the character never raided has no instance', function (): void {
    expect(characterRaids([])->instanceIdsOf(505))->toBe([]);
});

test('the instances of an expansion carry their modes and encounters', function (): void {
    $instances = characterRaids(['expansions' => [[
        'expansion' => ['id' => 505],
        'instances' => [[
            'instance' => ['id' => 1307, 'name' => 'The Voidspire'],
            'modes' => [[
                'difficulty' => ['type' => 'MYTHIC', 'name' => 'Mythique'],
                'progress' => [
                    'completed_count' => 1,
                    'total_count' => 6,
                    'encounters' => [['encounter' => ['id' => 2733, 'name' => 'Averzian'], 'last_kill_timestamp' => 1_775_411_018_000]],
                ],
            ]],
        ]],
    ]]])->instancesOf(505);

    $mode = $instances[0]->modes[0] ?? null;
    $encounter = $mode?->encounters[0];

    expect($instances[0]->id ?? null)->toBe(1307)
        ->and($instances[0]->name ?? null)->toBe('The Voidspire')
        ->and($mode?->difficultyType)->toBe('MYTHIC')
        ->and($mode?->completedCount)->toBe(1)
        ->and($mode?->totalCount)->toBe(6)
        ->and($encounter?->id)->toBe(2733)
        ->and($encounter?->name)->toBe('Averzian')
        ->and($encounter?->lastKillTimestamp)->toBe(1_775_411_018_000);
});

test('a mode without progress has no count and no encounter', function (): void {
    $mode = characterRaids(['expansions' => [['expansion' => ['id' => 505], 'instances' => [['modes' => [['difficulty' => ['type' => 'HEROIC']]]]]]]])
        ->instancesOf(505)[0]->modes[0] ?? null;

    expect($mode?->completedCount)->toBeNull()
        ->and($mode?->totalCount)->toBeNull()
        ->and($mode?->encounters)->toBe([]);
});

test('a kill timestamp served as a numeric string is still read', function (): void {
    $encounter = characterRaids(['expansions' => [['expansion' => ['id' => 505], 'instances' => [['modes' => [[
        'progress' => ['encounters' => [['encounter' => ['id' => 1], 'last_kill_timestamp' => '1775000000001']]],
    ]]]]]]])->instancesOf(505)[0]->modes[0]->encounters[0] ?? null;

    expect($encounter?->lastKillTimestamp)->toBe(1_775_000_000_001);
});

test('an expansion absent from the response has no instance list at all', function (): void {
    expect(characterRaids(['expansions' => [['expansion' => ['id' => 516], 'instances' => []]]])->instancesOf(505))->toBeNull()
        ->and(characterRaids(['expansions' => [['expansion' => ['id' => 505], 'instances' => []]]])->instancesOf(505))->toBe([]);
});

test('an instance without id is kept among the instances but not among the ids', function (): void {
    $characterRaidsResponse = characterRaids(['expansions' => [['expansion' => ['id' => 505], 'instances' => [['modes' => []]]]]]);

    expect($characterRaidsResponse->instancesOf(505))->toHaveCount(1)
        ->and($characterRaidsResponse->instanceIdsOf(505))->toBe([]);
});
