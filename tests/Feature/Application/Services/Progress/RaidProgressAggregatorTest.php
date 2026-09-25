<?php

declare(strict_types=1);

use App\Application\Services\Progress\RaidProgressAggregator;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterRaidsResponse;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Fabrique un mode (difficulté) tel que renvoyé par l'API encounters/raids.
 *
 * @param  list<array{id: int, name: string, last_kill_timestamp?: int}>  $encounters
 * @return array<string, mixed>
 */
function raidMode(string $type, string $name, int $completed, int $total, array $encounters): array
{
    return [
        'difficulty' => ['type' => $type, 'name' => $name],
        'status' => ['type' => $completed >= $total ? 'COMPLETE' : 'IN_PROGRESS'],
        'progress' => [
            'completed_count' => $completed,
            'total_count' => $total,
            'encounters' => array_map(fn (array $e): array => [
                'encounter' => ['id' => $e['id'], 'name' => $e['name']],
                'completed_count' => 1,
                'last_kill_timestamp' => $e['last_kill_timestamp'] ?? 0,
            ], $encounters),
        ],
    ];
}

/**
 * @param  list<array<string, mixed>>  $instances
 * @return array<string, mixed>
 */
function raidExpansion(int $id, string $name, array $instances): array
{
    return [
        'expansion' => ['id' => $id, 'name' => $name],
        'instances' => $instances,
    ];
}

test('aggregate returns null when response is empty', function (): void {
    expect(aggregateRaids([]))->toBeNull();
});

test('aggregate returns null when no current season expansion is present', function (): void {
    $response = [
        'expansions' => [
            raidExpansion(503, 'Dragonflight', [
                [
                    'instance' => ['id' => 1208, 'name' => 'Aberrus'],
                    'modes' => [raidMode('MYTHIC', 'Mythique', 9, 9, [['id' => 2522, 'name' => 'Kazzara']])],
                ],
            ]),
        ],
    ];

    expect(aggregateRaids($response))->toBeNull();
});

test('aggregate keeps only the current season expansion', function (): void {
    $currentSeasonInstances = [
        [
            'instance' => ['id' => 1307, 'name' => 'The Voidspire'],
            'modes' => [raidMode('LFR', 'Raids', 6, 6, [['id' => 2733, 'name' => 'Imperator Averzian']])],
        ],
    ];

    $response = [
        'expansions' => [
            raidExpansion(395, 'Legion', [
                [
                    'instance' => ['id' => 768, 'name' => 'The Emerald Nightmare'],
                    'modes' => [raidMode('NORMAL', 'Normal', 2, 7, [['id' => 1703, 'name' => 'Nythendra']])],
                ],
            ]),
            raidExpansion(505, 'Current Season', $currentSeasonInstances),
            // Les mêmes raids apparaissent aussi sous l'extension réelle : doivent être ignorés.
            raidExpansion(516, 'Midnight', $currentSeasonInstances),
        ],
    ];

    $result = aggregateRaids($response);

    expect($result)->toHaveCount(1)
        ->and($result[0]['instance_id'])->toBe(1307)
        ->and($result[0]['instance_name'])->toBe('The Voidspire')
        ->and($result[0]['modes'])->toHaveCount(1)
        ->and($result[0]['modes'][0]['difficulty_type'])->toBe('LFR')
        ->and($result[0]['modes'][0]['completed_count'])->toBe(6)
        ->and($result[0]['modes'][0]['total_count'])->toBe(6)
        ->and($result[0]['modes'][0]['encounters'])->toHaveCount(1)
        ->and($result[0]['modes'][0]['encounters'][0]['name'])->toBe('Imperator Averzian');
});

test('aggregate orders difficulties LFR < Normal < Heroic < Mythic', function (): void {
    $response = [
        'expansions' => [
            raidExpansion(505, 'Current Season', [
                [
                    'instance' => ['id' => 1305, 'name' => 'Sporefall'],
                    'modes' => [
                        raidMode('MYTHIC', 'Mythique', 1, 3, [['id' => 1, 'name' => 'A']]),
                        raidMode('LFR', 'Raids', 3, 3, [['id' => 2, 'name' => 'B']]),
                        raidMode('HEROIC', 'Héroïque', 2, 3, [['id' => 3, 'name' => 'C']]),
                        raidMode('NORMAL', 'Normal', 3, 3, [['id' => 4, 'name' => 'D']]),
                    ],
                ],
            ]),
        ],
    ];

    $result = aggregateRaids($response);

    $types = array_column($result[0]['modes'], 'difficulty_type');
    expect($types)->toBe(['LFR', 'NORMAL', 'HEROIC', 'MYTHIC']);
});

test('aggregate maps difficulty types to French labels', function (): void {
    $response = [
        'expansions' => [
            raidExpansion(505, 'Current Season', [
                [
                    'instance' => ['id' => 1305, 'name' => 'Sporefall'],
                    'modes' => [
                        raidMode('LFR', 'Raids', 1, 1, [['id' => 1, 'name' => 'A']]),
                        raidMode('HEROIC', 'ignored', 1, 1, [['id' => 2, 'name' => 'B']]),
                        raidMode('MYTHIC', 'ignored', 1, 1, [['id' => 3, 'name' => 'C']]),
                    ],
                ],
            ]),
        ],
    ];

    $labels = array_column(aggregateRaids($response)[0]['modes'], 'difficulty_label');

    expect($labels)->toBe(['LFR', 'Héroïque', 'Mythique']);
});

test('aggregate overrides instance and boss names with localized name map', function (): void {
    $response = [
        'expansions' => [
            raidExpansion(505, 'Current Season', [
                [
                    'instance' => ['id' => 1307, 'name' => 'The Voidspire'],
                    'modes' => [
                        raidMode('LFR', 'Raids', 1, 1, [['id' => 2733, 'name' => 'Imperator Averzian']]),
                    ],
                ],
            ]),
        ],
    ];

    $nameMap = [
        1307 => [
            'name' => 'La Flèche du Vide',
            'encounters' => [2733 => 'Imperator Averzian FR'],
        ],
    ];

    $result = aggregateRaids($response, $nameMap);

    expect($result[0]['instance_name'])->toBe('La Flèche du Vide')
        ->and($result[0]['modes'][0]['encounters'][0]['name'])->toBe('Imperator Averzian FR');
});

test('aggregate falls back to api names when name map is missing an entry', function (): void {
    $response = [
        'expansions' => [
            raidExpansion(505, 'Current Season', [
                [
                    'instance' => ['id' => 1307, 'name' => 'The Voidspire'],
                    'modes' => [
                        raidMode('LFR', 'Raids', 1, 1, [['id' => 2733, 'name' => 'Imperator Averzian']]),
                    ],
                ],
            ]),
        ],
    ];

    $result = aggregateRaids($response, []);

    expect($result[0]['instance_name'])->toBe('The Voidspire')
        ->and($result[0]['modes'][0]['encounters'][0]['name'])->toBe('Imperator Averzian');
});

test('aggregate preserves boss kill timestamp', function (): void {
    $response = [
        'expansions' => [
            raidExpansion(505, 'Current Season', [
                [
                    'instance' => ['id' => 1305, 'name' => 'Sporefall'],
                    'modes' => [
                        raidMode('LFR', 'Raids', 1, 1, [['id' => 2711, 'name' => 'Rotmire', 'last_kill_timestamp' => 1783527196000]]),
                    ],
                ],
            ]),
        ],
    ];

    $encounter = aggregateRaids($response)[0]['modes'][0]['encounters'][0];

    expect($encounter['id'])->toBe(2711)
        ->and($encounter['last_kill_timestamp'])->toBe(1783527196000);
});

/**
 * @param  array<string, mixed>  $raidsResponse
 * @param  array<int, array{name?: string, encounters?: array<int, string>}>  $nameMap
 * @return list<array<string, mixed>>|null
 */
function aggregateRaids(array $raidsResponse, array $nameMap = []): ?array
{
    return (new RaidProgressAggregator)->aggregate(
        CharacterRaidsResponse::fromPayload(ResponsePayload::forEndpoint('profile/wow/character/hyjal/thrall/encounters/raids', $raidsResponse)),
        $nameMap,
    );
}

test('the raid tab payload is unchanged', function (): void {
    $response = ['expansions' => [
        raidExpansion(516, 'Midnight', [['instance' => ['id' => 1300, 'name' => 'Ailleurs'], 'modes' => []]]),
        raidExpansion(505, 'Current Season', [
            [
                'instance' => ['id' => 1307, 'name' => 'The Voidspire'],
                'modes' => [
                    raidMode('MYTHIC', 'Mythique', 1, 6, [['id' => 2733, 'name' => 'Averzian', 'last_kill_timestamp' => 1_775_411_018_000]]),
                    raidMode('STORY', 'Histoire', 6, 6, []),
                    raidMode('LFR', 'Raids', 6, 6, [['id' => 2733, 'name' => 'Averzian'], ['id' => 2734, 'name' => 'Salhadaar', 'last_kill_timestamp' => 1_775_000_000_000]]),
                    raidMode('HEROIC', 'Héroïque', 3, 6, [['id' => 2735, 'name' => 'Inconnu']]),
                ],
            ],
            ['instance' => ['id' => 1308, 'name' => "March on Quel'Danas"], 'modes' => [
                ['difficulty' => ['type' => 'NORMAL'], 'progress' => ['completed_count' => 2, 'total_count' => 2, 'encounters' => [['encounter' => ['id' => 3001], 'last_kill_timestamp' => '1775000000001']]]],
                ['difficulty' => ['type' => 'HEROIC']],
            ]],
            ['instance' => [], 'modes' => []],
        ]),
    ]];

    $nameMap = [
        1307 => ['name' => 'La Flèche du Vide', 'encounters' => [2733 => 'Empereur Averzian', 2734 => '']],
        1308 => ['name' => '', 'encounters' => []],
    ];

    expect(json_encode(aggregateRaids($response, $nameMap), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
        ->toMatchSnapshot();
});

test('aggregate falls back to neutral values when Blizzard omits a difficulty, a boss id or a kill', function (): void {
    $response = [
        'expansions' => [
            raidExpansion(505, 'Current Season', [
                [
                    'instance' => ['id' => 1305, 'name' => 'Sporefall'],
                    'modes' => [[
                        'progress' => [
                            'encounters' => [['encounter' => ['name' => 'Rotmire']]],
                        ],
                    ]],
                ],
            ]),
        ],
    ];

    $mode = aggregateRaids($response, [1305 => ['encounters' => [1 => 'Premier boss']]])[0]['modes'][0];

    expect($mode)->toBe([
        'difficulty_type' => '',
        'difficulty_label' => '',
        'completed_count' => 0,
        'total_count' => 0,
        'encounters' => [['id' => 0, 'name' => 'Rotmire', 'last_kill_timestamp' => 0]],
    ]);
});
