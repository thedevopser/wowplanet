<?php

declare(strict_types=1);

use App\Application\Services\Progress\ReputationProgressAggregator;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterReputationsResponse;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;
use App\Infrastructure\Reference\FactionReference;

function makeAggregator(
    array $buildMap = [],
    array $maxRenownMap = [],
    array $namesMap = [],
    array $factionMap = [],
    array $accountWideFactionIds = [],
): ReputationProgressAggregator {
    $mock = Mockery::mock(FactionReference::class);
    $mock->shouldReceive('expansions')->andReturn($buildMap);
    $mock->shouldReceive('maxRenownLevels')->andReturn($maxRenownMap);
    $mock->shouldReceive('names')->andReturn($namesMap);
    $mock->shouldReceive('accountWideIds')->andReturn($accountWideFactionIds);
    $mock->shouldReceive('factions')->andReturn($factionMap);

    return new ReputationProgressAggregator($mock);
}

test('aggregate groups reputations by expansion', function (): void {
    $reputationProgressAggregator = makeAggregator(
        buildMap: [72 => 0, 1037 => 2],
        namesMap: [72 => 'Hurlevent', 1037 => 'Chevaliers de la Lame d\'ébène'],
    );

    $result = reputationProgress($reputationProgressAggregator, [
        'reputations' => [
            [
                'faction' => ['id' => 72, 'name' => 'Hurlevent'],
                'standing' => ['name' => 'Exalté', 'tier' => 7, 'value' => 0, 'max' => 0, 'raw' => 42999],
            ],
            [
                'faction' => ['id' => 1037, 'name' => 'Chevaliers de la Lame d\'ébène'],
                'standing' => ['name' => 'Honoré', 'tier' => 5, 'value' => 5000, 'max' => 12000, 'raw' => 14000],
            ],
        ],
    ]);

    expect($result[0]['total'])->toBe(1)
        ->and($result[0]['completed'])->toBe(1)
        ->and($result[0]['factions'])->toHaveCount(1)
        ->and($result[0]['factions'][0]['name'])->toBe('Hurlevent')
        ->and($result[0]['factions'][0]['tier'])->toBe(7)
        ->and($result[0]['factions'][0]['started'])->toBeTrue();

    expect($result[2]['total'])->toBe(1)
        ->and($result[2]['completed'])->toBe(0)
        ->and($result[2]['factions'][0]['name'])->toBe('Chevaliers de la Lame d\'ébène')
        ->and($result[2]['factions'][0]['value'])->toBe(5000)
        ->and($result[2]['factions'][0]['max'])->toBe(12000)
        ->and($result[2]['factions'][0]['started'])->toBeTrue();
});

test('aggregate counts exalted tier as completed', function (): void {
    $reputationProgressAggregator = makeAggregator(
        buildMap: [72 => 0],
        namesMap: [72 => 'Hurlevent'],
    );

    $result = reputationProgress($reputationProgressAggregator, [
        'reputations' => [
            [
                'faction' => ['id' => 72, 'name' => 'Hurlevent'],
                'standing' => ['name' => 'Exalté', 'tier' => 7, 'value' => 0, 'max' => 0, 'raw' => 42999],
            ],
        ],
    ]);

    expect($result[0]['completed'])->toBe(1)
        ->and($result[0]['factions'][0]['completed'])->toBeTrue()
        ->and($result[0]['factions'][0]['started'])->toBeTrue();
});

test('aggregate counts max renown as completed via renown_level', function (): void {
    $reputationProgressAggregator = makeAggregator(
        buildMap: [2503 => 9],
        maxRenownMap: [2503 => 25],
        namesMap: [2503 => 'Centaure maruuk'],
    );

    $result = reputationProgress($reputationProgressAggregator, [
        'reputations' => [
            [
                'faction' => ['id' => 2503, 'name' => 'Centaure maruuk'],
                'standing' => ['name' => 'Renom 25', 'value' => 0, 'max' => 2500, 'raw' => 62500, 'renown_level' => 25],
            ],
        ],
    ]);

    expect($result[9]['completed'])->toBe(1)
        ->and($result[9]['factions'][0]['completed'])->toBeTrue()
        ->and($result[9]['factions'][0]['renown_level'])->toBe(25);
});

test('aggregate does not count in-progress renown as completed', function (): void {
    $reputationProgressAggregator = makeAggregator(
        buildMap: [2503 => 9],
        maxRenownMap: [2503 => 25],
        namesMap: [2503 => 'Centaure maruuk'],
    );

    $result = reputationProgress($reputationProgressAggregator, [
        'reputations' => [
            [
                'faction' => ['id' => 2503, 'name' => 'Centaure maruuk'],
                'standing' => ['name' => 'Renom 15', 'value' => 1200, 'max' => 2500, 'raw' => 37500, 'renown_level' => 15],
            ],
        ],
    ]);

    expect($result[9]['completed'])->toBe(0)
        ->and($result[9]['factions'][0]['completed'])->toBeFalse()
        ->and($result[9]['factions'][0]['renown_level'])->toBe(15);
});

test('aggregate skips factions not in expansion map', function (): void {
    $reputationProgressAggregator = makeAggregator();

    $result = reputationProgress($reputationProgressAggregator, [
        'reputations' => [
            [
                'faction' => ['id' => 9999, 'name' => 'Unknown'],
                'standing' => ['name' => 'Neutre', 'tier' => 3, 'value' => 0, 'max' => 3000, 'raw' => 0],
            ],
        ],
    ]);

    for ($i = 0; $i <= 11; $i++) {
        expect($result[$i]['total'])->toBe(0);
    }
});

test('aggregate returns all 12 expansion slots', function (): void {
    $reputationProgressAggregator = makeAggregator();

    $result = reputationProgress($reputationProgressAggregator, ['reputations' => []]);

    expect($result)->toHaveCount(12);
    expect(array_keys($result))->toBe(range(0, 11));
});

test('aggregate adds unstarted factions from DB2 map when API response is empty', function (): void {
    $reputationProgressAggregator = makeAggregator(
        buildMap: [72 => 0],
        namesMap: [72 => 'Hurlevent'],
    );

    $result = reputationProgress($reputationProgressAggregator, []);

    expect($result[0]['total'])->toBe(1)
        ->and($result[0]['completed'])->toBe(0)
        ->and($result[0]['factions'][0]['name'])->toBe('Hurlevent')
        ->and($result[0]['factions'][0]['started'])->toBeFalse()
        ->and($result[0]['factions'][0]['tier'])->toBe(-1)
        ->and($result[0]['factions'][0]['standing_name'])->toBe('Non commencée');
});

test('aggregate preserves faction data fields', function (): void {
    $reputationProgressAggregator = makeAggregator(
        buildMap: [72 => 0],
        namesMap: [72 => 'Hurlevent'],
    );

    $result = reputationProgress($reputationProgressAggregator, [
        'reputations' => [
            [
                'faction' => ['id' => 72, 'name' => 'Hurlevent'],
                'standing' => ['name' => 'Révéré', 'tier' => 6, 'value' => 8000, 'max' => 21000, 'raw' => 35000],
            ],
        ],
    ]);

    $faction = $result[0]['factions'][0];
    expect($faction['id'])->toBe(72)
        ->and($faction['name'])->toBe('Hurlevent')
        ->and($faction['standing_name'])->toBe('Révéré')
        ->and($faction['tier'])->toBe(6)
        ->and($faction['value'])->toBe(8000)
        ->and($faction['max'])->toBe(21000)
        ->and($faction['raw'])->toBe(35000)
        ->and($faction['renown_level'])->toBe(0)
        ->and($faction['completed'])->toBeFalse()
        ->and($faction['started'])->toBeTrue();
});

test('aggregate counts max === 0 with tier > 0 as completed', function (): void {
    $reputationProgressAggregator = makeAggregator(
        buildMap: [2600 => 10],
        namesMap: [2600 => 'Council of Dornogal'],
    );

    $result = reputationProgress($reputationProgressAggregator, [
        'reputations' => [
            [
                'faction' => ['id' => 2600, 'name' => 'Council of Dornogal'],
                'standing' => ['name' => 'Amis', 'tier' => 5, 'value' => 0, 'max' => 0, 'raw' => 50000],
            ],
        ],
    ]);

    expect($result[10]['completed'])->toBe(1)
        ->and($result[10]['factions'][0]['completed'])->toBeTrue();
});

test('aggregate handles renown without max renown map entry', function (): void {
    $reputationProgressAggregator = makeAggregator(
        buildMap: [2503 => 9],
        namesMap: [2503 => 'Centaure maruuk'],
    );

    $result = reputationProgress($reputationProgressAggregator, [
        'reputations' => [
            [
                'faction' => ['id' => 2503, 'name' => 'Centaure maruuk'],
                'standing' => ['name' => 'Renom 25', 'value' => 0, 'max' => 2500, 'raw' => 62500, 'renown_level' => 25],
            ],
        ],
    ]);

    expect($result[9]['completed'])->toBe(0)
        ->and($result[9]['factions'][0]['completed'])->toBeFalse();
});

test('aggregate does not count Midnight Niveau X friendship as completed', function (): void {
    $reputationProgressAggregator = makeAggregator(
        buildMap: [2744 => 11],
        namesMap: [2744 => 'Valeera Sanguinar'],
    );

    $result = reputationProgress($reputationProgressAggregator, [
        'reputations' => [
            [
                'faction' => ['id' => 2744, 'name' => 'Valeera Sanguinar'],
                'standing' => ['name' => 'Niveau 41', 'tier' => 40, 'value' => 1838, 'max' => 88625, 'raw' => 1662898],
            ],
        ],
    ]);

    expect($result[11]['completed'])->toBe(0)
        ->and($result[11]['factions'][0]['completed'])->toBeFalse()
        ->and($result[11]['factions'][0]['tier'])->toBe(40)
        ->and($result[11]['factions'][0]['max'])->toBe(88625);
});

test('aggregate does not count Brann Barbe-de-Bronze level as completed', function (): void {
    $reputationProgressAggregator = makeAggregator(
        buildMap: [2640 => 10],
        namesMap: [2640 => 'Brann Barbe-de-Bronze'],
    );

    $result = reputationProgress($reputationProgressAggregator, [
        'reputations' => [
            [
                'faction' => ['id' => 2640, 'name' => 'Brann Barbe-de-Bronze'],
                'standing' => ['name' => 'Niveau 81', 'tier' => 80, 'value' => 64033, 'max' => 166000, 'raw' => 5872433],
            ],
        ],
    ]);

    expect($result[10]['completed'])->toBe(0)
        ->and($result[10]['factions'][0]['completed'])->toBeFalse();
});

test('aggregate counts paragon-capped exalted as completed', function (): void {
    $reputationProgressAggregator = makeAggregator(
        buildMap: [2413 => 8],
        namesMap: [2413 => 'Cour des Moissonneurs'],
    );

    $result = reputationProgress($reputationProgressAggregator, [
        'reputations' => [
            [
                'faction' => ['id' => 2413, 'name' => 'Cour des Moissonneurs'],
                'standing' => ['name' => 'Exalté', 'tier' => 7, 'value' => 0, 'max' => 0, 'raw' => 42000],
            ],
        ],
    ]);

    expect($result[8]['completed'])->toBe(1)
        ->and($result[8]['factions'][0]['completed'])->toBeTrue();
});

test('aggregate counts Génie tier 8 as completed', function (): void {
    $reputationProgressAggregator = makeAggregator(
        buildMap: [2601 => 10],
        namesMap: [2601 => 'La Tisserande'],
    );

    $result = reputationProgress($reputationProgressAggregator, [
        'reputations' => [
            [
                'faction' => ['id' => 2601, 'name' => 'La Tisserande'],
                'standing' => ['name' => 'Génie', 'tier' => 8, 'value' => 0, 'max' => 0, 'raw' => 20000],
            ],
        ],
    ]);

    expect($result[10]['completed'])->toBe(1)
        ->and($result[10]['factions'][0]['completed'])->toBeTrue();
});

test('aggregate counts Légende tier 4 with max=0 as completed', function (): void {
    $reputationProgressAggregator = makeAggregator(
        buildMap: [2553 => 9],
        namesMap: [2553 => 'Soridormi'],
    );

    $result = reputationProgress($reputationProgressAggregator, [
        'reputations' => [
            [
                'faction' => ['id' => 2553, 'name' => 'Soridormi'],
                'standing' => ['name' => 'Légende', 'tier' => 4, 'value' => 0, 'max' => 0, 'raw' => 42000],
            ],
        ],
    ]);

    expect($result[9]['completed'])->toBe(1)
        ->and($result[9]['factions'][0]['completed'])->toBeTrue();
});

// ─── New tests: unstarted factions ──────────────────────────

test('aggregate mixes started and unstarted factions in same expansion', function (): void {
    $reputationProgressAggregator = makeAggregator(
        buildMap: [72 => 0, 76 => 0],
        namesMap: [72 => 'Hurlevent', 76 => 'Orgrimmar'],
    );

    $result = reputationProgress($reputationProgressAggregator, [
        'reputations' => [
            [
                'faction' => ['id' => 72, 'name' => 'Hurlevent'],
                'standing' => ['name' => 'Exalté', 'tier' => 7, 'value' => 0, 'max' => 0, 'raw' => 42999],
            ],
        ],
    ]);

    expect($result[0]['total'])->toBe(2)
        ->and($result[0]['completed'])->toBe(1);

    $started = collect($result[0]['factions'])->where('started', true);
    $unstarted = collect($result[0]['factions'])->where('started', false);

    expect($started)->toHaveCount(1)
        ->and($started->first()['name'])->toBe('Hurlevent');
    expect($unstarted)->toHaveCount(1)
        ->and($unstarted->first()['name'])->toBe('Orgrimmar')
        ->and($unstarted->first()['standing_name'])->toBe('Non commencée')
        ->and($unstarted->first()['tier'])->toBe(-1)
        ->and($unstarted->first()['completed'])->toBeFalse();
});

// ─── New tests: faction filtering ───────────────────────────

test('aggregate filters opposite faction reputations for Alliance character', function (): void {
    $reputationProgressAggregator = makeAggregator(
        buildMap: [72 => 0, 530 => 0, 1037 => 0],
        namesMap: [72 => 'Hurlevent', 530 => 'Trolls Sombrelance', 1037 => 'Neutres'],
        factionMap: [72 => 'Alliance', 530 => 'Horde'],
    );

    $result = reputationProgress($reputationProgressAggregator, ['reputations' => []], 'Alliance');

    $names = collect($result[0]['factions'])->pluck('name')->all();
    expect($names)->toContain('Hurlevent')
        ->and($names)->toContain('Neutres')
        ->and($names)->not->toContain('Trolls Sombrelance')
        ->and($result[0]['total'])->toBe(2);
});

test('aggregate filters opposite faction reputations for Horde character', function (): void {
    $reputationProgressAggregator = makeAggregator(
        buildMap: [72 => 0, 530 => 0, 1037 => 0],
        namesMap: [72 => 'Hurlevent', 530 => 'Trolls Sombrelance', 1037 => 'Neutres'],
        factionMap: [72 => 'Alliance', 530 => 'Horde'],
    );

    $result = reputationProgress($reputationProgressAggregator, ['reputations' => []], 'Horde');

    $names = collect($result[0]['factions'])->pluck('name')->all();
    expect($names)->toContain('Trolls Sombrelance')
        ->and($names)->toContain('Neutres')
        ->and($names)->not->toContain('Hurlevent')
        ->and($result[0]['total'])->toBe(2);
});

test('aggregate does not filter when characterFaction is empty', function (): void {
    $reputationProgressAggregator = makeAggregator(
        buildMap: [72 => 0, 530 => 0],
        namesMap: [72 => 'Hurlevent', 530 => 'Trolls Sombrelance'],
        factionMap: [72 => 'Alliance', 530 => 'Horde'],
    );

    $result = reputationProgress($reputationProgressAggregator, ['reputations' => []]);

    expect($result[0]['total'])->toBe(2);
});

test('aggregate keeps opposite faction reputations when started via API', function (): void {
    $reputationProgressAggregator = makeAggregator(
        buildMap: [72 => 0, 530 => 0],
        namesMap: [72 => 'Hurlevent', 530 => 'Trolls Sombrelance'],
        factionMap: [72 => 'Alliance', 530 => 'Horde'],
    );

    $result = reputationProgress($reputationProgressAggregator, [
        'reputations' => [
            [
                'faction' => ['id' => 530, 'name' => 'Trolls Sombrelance'],
                'standing' => ['name' => 'Neutre', 'tier' => 3, 'value' => 0, 'max' => 3000, 'raw' => 0],
            ],
        ],
    ], 'Alliance');

    $names = collect($result[0]['factions'])->pluck('name')->all();
    // API returned it for this character, so it must be kept even if tagged Horde
    expect($names)->toContain('Trolls Sombrelance')
        ->and($names)->toContain('Hurlevent')
        ->and($result[0]['total'])->toBe(2);
});

/**
 * @param  array<string, mixed>  $reputationsResponse
 * @return array<int, array<string, mixed>>
 */
function reputationProgress(ReputationProgressAggregator $reputationProgressAggregator, array $reputationsResponse, string $characterFaction = ''): array
{
    return $reputationProgressAggregator->aggregate(
        CharacterReputationsResponse::fromPayload(ResponsePayload::forEndpoint('profile/wow/character/hyjal/thrall/reputations', $reputationsResponse)),
        $characterFaction,
    );
}

test('the reputation payload is unchanged', function (): void {
    $reputationProgressAggregator = makeAggregator(
        buildMap: [72 => 0, 76 => 0, 1037 => 2, 2590 => 10, 2594 => 10, 2600 => 10, 2601 => 10, 2602 => 10, 9999 => 11],
        maxRenownMap: [2590 => 20, 2594 => 25],
        namesMap: [72 => 'Hurlevent', 76 => 'Orgrimmar', 1037 => 'Lame d\'ébène', 2590 => 'Conseil de Dornogal', 2594 => 'Assemblée', 2600 => 'Tisse-nuit', 2601 => 'Doublon', 2602 => 'Doublon', 9999 => 'Doublon'],
        factionMap: [72 => 'Alliance', 76 => 'Horde'],
        accountWideFactionIds: [2600 => true],
    );

    $result = reputationProgress($reputationProgressAggregator, [
        'reputations' => [
            ['faction' => ['id' => 72, 'name' => 'Hurlevent'], 'standing' => ['name' => 'Exalté', 'tier' => 7, 'value' => 0, 'max' => 0, 'raw' => 42999]],
            ['faction' => ['id' => 1037, 'name' => 'Lame d\'ébène'], 'standing' => ['name' => 'Honoré', 'tier' => 5, 'value' => 5000, 'max' => 12000, 'raw' => 14000]],
            ['faction' => ['id' => 2590, 'name' => 'Conseil de Dornogal'], 'standing' => ['name' => 'Renom 20', 'tier' => 0, 'value' => 0, 'max' => 2500, 'raw' => 50000, 'renown_level' => 20]],
            ['faction' => ['id' => 2594, 'name' => 'Assemblée'], 'standing' => ['name' => 'Renom 3', 'tier' => 0, 'value' => 10, 'max' => 2500, 'raw' => 7510, 'renown_level' => 3]],
            ['faction' => ['id' => 2601, 'name' => 'Doublon'], 'standing' => ['tier' => 2]],
            ['faction' => ['id' => 5555, 'name' => 'Hors carte'], 'standing' => ['tier' => 7]],
            ['faction' => ['name' => 'Sans id'], 'standing' => ['tier' => 7]],
            ['standing' => ['tier' => 7]],
        ],
    ], 'Alliance');

    expect(json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))->toMatchSnapshot();
});

/**
 * @return list<array{id: int, name: string, started: bool}>
 */
function reputationFactionsOf(array $result, int $expansionId): array
{
    return array_map(fn (array $faction): array => ['id' => $faction['id'], 'name' => $faction['name'], 'started' => $faction['started']], $result[$expansionId]['factions']);
}

test('aggregate keeps every faction when no character faction is given', function (): void {
    $reputationProgressAggregator = makeAggregator(buildMap: [72 => 0, 76 => 0], namesMap: [72 => 'Hurlevent', 76 => 'Orgrimmar'], factionMap: [72 => 'ALLIANCE', 76 => 'HORDE']);

    $result = $reputationProgressAggregator->aggregate(CharacterReputationsResponse::fromPayload(ResponsePayload::forEndpoint('profile/wow/character/hyjal/thrall/reputations', [])));

    expect(array_column($result[0]['factions'], 'id'))->toBe([72, 76]);
});

test('aggregate skips a standing without faction or outside the reference and reads the following ones', function (): void {
    $reputationProgressAggregator = makeAggregator(buildMap: [1 => 0, 72 => 0], namesMap: [1 => 'Première', 72 => 'Hurlevent']);

    $result = reputationProgress($reputationProgressAggregator, ['reputations' => [
        ['standing' => ['name' => 'Exalté', 'tier' => 7, 'max' => 0]],
        ['faction' => ['id' => 9999, 'name' => 'Inconnue'], 'standing' => ['tier' => 3]],
        ['faction' => ['id' => 72, 'name' => 'Hurlevent'], 'standing' => ['tier' => 3, 'max' => 3000]],
    ]]);

    expect(reputationFactionsOf($result, 0))->toBe([
        ['id' => 72, 'name' => 'Hurlevent', 'started' => true],
        ['id' => 1, 'name' => 'Première', 'started' => false],
    ]);
});

test('aggregate reads a standing of faction one', function (): void {
    $reputationProgressAggregator = makeAggregator(buildMap: [1 => 0], namesMap: [1 => 'Première']);

    $result = reputationProgress($reputationProgressAggregator, ['reputations' => [
        ['faction' => ['id' => 1, 'name' => 'Première'], 'standing' => ['tier' => 3, 'max' => 3000]],
    ]]);

    expect(reputationFactionsOf($result, 0))->toBe([['id' => 1, 'name' => 'Première', 'started' => true]]);
});

test('aggregate falls back to neutral values when Blizzard omits a standing field', function (): void {
    $reputationProgressAggregator = makeAggregator(buildMap: [72 => 0]);

    $result = reputationProgress($reputationProgressAggregator, ['reputations' => [['faction' => ['id' => 72]]]]);

    expect($result[0]['factions'][0])->toBe([
        'id' => 72,
        'name' => '',
        'standing_name' => '',
        'tier' => 0,
        'value' => 0,
        'max' => 0,
        'raw' => 0,
        'renown_level' => 0,
        'completed' => false,
        'started' => true,
        'account_wide' => false,
    ])->and($result[0]['completed'])->toBe(0);
});

test('aggregate counts a capped standing as completed from the first tier', function (): void {
    $reputationProgressAggregator = makeAggregator(buildMap: [72 => 0]);

    $result = reputationProgress($reputationProgressAggregator, ['reputations' => [
        ['faction' => ['id' => 72, 'name' => 'Hurlevent'], 'standing' => ['tier' => 1, 'max' => 0]],
    ]]);

    expect($result[0]['factions'][0]['completed'])->toBeTrue();
});

test('aggregate counts a renown capped at its first level as completed and account wide', function (): void {
    $reputationProgressAggregator = makeAggregator(buildMap: [2503 => 9], maxRenownMap: [2503 => 1]);

    $result = reputationProgress($reputationProgressAggregator, ['reputations' => [
        ['faction' => ['id' => 2503, 'name' => 'Centaure maruuk'], 'standing' => ['max' => 2500, 'renown_level' => 1]],
    ]]);

    expect($result[9]['factions'][0]['completed'])->toBeTrue()
        ->and($result[9]['factions'][0]['account_wide'])->toBeTrue();
});

test('aggregate lists once the unstarted factions sharing a name and keeps the following ones', function (): void {
    $reputationProgressAggregator = makeAggregator(
        buildMap: [72 => 0, 73 => 0, 74 => 0, 75 => 0, 76 => 0],
        namesMap: [72 => 'Hurlevent', 73 => 'Hurlevent', 74 => 'Forgefer', 75 => 'Forgefer', 76 => 'Darnassus'],
    );

    $result = reputationProgress($reputationProgressAggregator, ['reputations' => [
        ['faction' => ['id' => 72, 'name' => 'Hurlevent'], 'standing' => ['tier' => 3, 'max' => 3000]],
    ]]);

    expect(reputationFactionsOf($result, 0))->toBe([
        ['id' => 72, 'name' => 'Hurlevent', 'started' => true],
        ['id' => 74, 'name' => 'Forgefer', 'started' => false],
        ['id' => 76, 'name' => 'Darnassus', 'started' => false],
    ]);
});

test('aggregate names an unstarted faction missing from the names reference neutrally', function (): void {
    $result = reputationProgress(makeAggregator(buildMap: [72 => 0]), []);

    expect(reputationFactionsOf($result, 0))->toBe([['id' => 72, 'name' => '', 'started' => false]]);
});
