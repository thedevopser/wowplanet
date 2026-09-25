<?php

declare(strict_types=1);

use App\Application\Services\CharacterProfileService;
use App\Application\Services\UserCharacterService;
use App\Domain\ValueObjects\ScoreWeights;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Models\WowAppearance;
use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;
use App\Models\WowQuest;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;

function asyncResponse(array $data): FulfilledPromise
{
    return new FulfilledPromise(new Response(200, [], json_encode($data, JSON_THROW_ON_ERROR)));
}

/**
 * @param  array<string, array<string, mixed>>  $endpointResponses
 */
function mockAsyncEndpoints(\Mockery\MockInterface $mock, array $endpointResponses): void
{
    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('getAsync');
    $exp->andReturnUsing(function (string $endpoint) use ($endpointResponses): FulfilledPromise {
        foreach ($endpointResponses as $pattern => $data) {
            if (str_contains($endpoint, $pattern)) {
                return asyncResponse($data);
            }
        }

        return asyncResponse([]);
    });
}

test('get profile returns correct dto', function (): void {
    WowQuest::factory()->create([
        'id' => 100,
        'name_fr' => 'Quête Test',
        'expansion_id' => 0,
        'zone_name' => 'Forêt d\'Elwynn',
        'is_active' => true,
    ]);

    WowMount::factory()->create([
        'id' => 200,
        'name_fr' => 'Loup noir',
        'is_active' => true,
    ]);

    WowPet::factory()->create([
        'id' => 300,
        'name_fr' => 'Dragonnet',
        'is_active' => true,
    ]);

    WowDecor::factory()->create([
        'id' => 500,
        'name_fr' => 'Foyer orné en pierre',
        'item_id' => 245000,
        'is_active' => true,
    ]);

    $mock = $this->partialMock(BlizzardApiClient::class);

    // Summary is fetched synchronously
    /** @var \Mockery\Expectation $profileExp */
    $profileExp = $mock->shouldReceive('get');
    $profileExp->with('profile/wow/character/hyjal/thrall', [])
        ->andReturn([
            'name' => 'Thrall',
            'realm' => ['name' => 'Hyjal'],
            'race' => ['name' => 'Orc'],
            'character_class' => ['id' => 7, 'name' => 'Chaman'],
            'level' => 80,
            'equipped_item_level' => 620,
            'faction' => ['name' => 'Horde'],
        ]);

    /** @var \Mockery\Expectation $seasonExp */
    $seasonExp = $mock->shouldReceive('getCurrentMythicSeasonId');
    $seasonExp->andReturn(0);

    $mock->shouldReceive('getRegion')->andReturn('eu');

    $userCharMock = $this->mock(UserCharacterService::class);
    /** @var \Mockery\Expectation $classIconsExp */
    $classIconsExp = $userCharMock->shouldReceive('getClassIcons');
    $classIconsExp->andReturn([7 => 'https://render.com/class-icon.jpg']);

    // All other endpoints are fetched async
    mockAsyncEndpoints($mock, [
        'character-media' => [
            'assets' => [
                ['key' => 'avatar', 'value' => 'https://render.com/avatar.jpg'],
                ['key' => 'inset', 'value' => 'https://render.com/inset.jpg'],
            ],
        ],
        'quests/completed' => ['quests' => [['id' => 100]]],
        'achievements' => ['achievements' => []],
        'collections/mounts' => ['mounts' => [['mount' => ['id' => 200]]]],
        'collections/pets' => ['pets' => [['species' => ['id' => 300]]]],
        'collections/decor' => ['decor_collected' => [['decor' => ['id' => 500]]]],
        '/professions' => ['primaries' => [], 'secondaries' => []],
        '/reputations' => ['reputations' => []],
        '/equipment' => [
            'equipped_items' => [
                [
                    'slot' => ['type' => 'HEAD', 'name' => 'Tête'],
                    'item' => ['id' => 12345],
                    'name' => 'Casque du Néant',
                    'quality' => ['type' => 'EPIC', 'name' => 'Épique'],
                    'level' => ['value' => 639],
                    'media' => ['id' => 123456],
                ],
            ],
        ],
    ]);

    $characterProfileService = resolve(CharacterProfileService::class);
    $characterProfileDTO = $characterProfileService->getProfile('Hyjal', 'Thrall');

    expect($characterProfileDTO->name)->toBe('Thrall')
        ->and($characterProfileDTO->realm)->toBe('Hyjal')
        ->and($characterProfileDTO->level)->toBe(80)
        ->and($characterProfileDTO->classId)->toBe(7)
        ->and($characterProfileDTO->mountsCount)->toBe(1)
        ->and($characterProfileDTO->petsCount)->toBe(1)
        ->and($characterProfileDTO->decorCount)->toBe(1)
        ->and($characterProfileDTO->exaltedCount)->toBe(0)
        ->and($characterProfileDTO->classIconUrl)->toBe('https://render.com/class-icon.jpg')
        ->and($characterProfileDTO->professions)->toBe([])
        ->and($characterProfileDTO->decor)->toHaveCount(1)
        ->and($characterProfileDTO->decor[0]['is_completed'])->toBeTrue()
        ->and($characterProfileDTO->decor[0]['name'])->toBe('Foyer orné en pierre')
        ->and($characterProfileDTO->equipment)->toHaveCount(1)
        ->and($characterProfileDTO->equipment[0]['slot'])->toBe('HEAD')
        ->and($characterProfileDTO->equipment[0]['item_id'])->toBe(12345)
        ->and($characterProfileDTO->equipment[0]['icon_url'])->toBeNull();
});

test('get profile aggregates unlocked transmog appearances by slot', function (): void {
    WowAppearance::factory()->create(['id' => 321, 'slot' => 'HEAD', 'category' => 'Armure', 'is_active' => true]);
    WowAppearance::factory()->create(['id' => 322, 'slot' => 'HEAD', 'category' => 'Armure', 'is_active' => true]);
    WowAppearance::factory()->create(['id' => 400, 'slot' => 'WEAPON', 'category' => 'Arme', 'is_active' => true]);

    $mock = $this->partialMock(BlizzardApiClient::class);

    /** @var \Mockery\Expectation $profileExp */
    $profileExp = $mock->shouldReceive('get');
    $profileExp->andReturn([
        'name' => 'Thrall',
        'realm' => ['name' => 'Hyjal'],
        'race' => ['name' => 'Orc'],
        'character_class' => ['id' => 7, 'name' => 'Chaman'],
        'level' => 80,
        'equipped_item_level' => 600,
        'faction' => ['name' => 'Horde'],
    ]);

    /** @var \Mockery\Expectation $seasonExp */
    $seasonExp = $mock->shouldReceive('getCurrentMythicSeasonId');
    $seasonExp->andReturn(0);
    $mock->shouldReceive('getRegion')->andReturn('eu');
    $this->mock(UserCharacterService::class)->shouldReceive('getClassIcons')->andReturn([]);

    mockAsyncEndpoints($mock, [
        'character-media' => ['assets' => [['key' => 'avatar', 'value' => '']]],
        'quests/completed' => ['quests' => []],
        'achievements' => ['achievements' => []],
        'collections/mounts' => ['mounts' => []],
        'collections/pets' => ['pets' => []],
        'collections/decor' => ['decor_collected' => []],
        'collections/transmogs' => [
            'slots' => [
                ['slot' => ['type' => 'HEAD'], 'appearances' => [['id' => 321], ['id' => 999]]],
                ['slot' => ['type' => 'WEAPON'], 'appearances' => []],
            ],
        ],
        '/professions' => ['primaries' => [], 'secondaries' => []],
        '/reputations' => ['reputations' => []],
    ]);

    $characterProfileService = resolve(CharacterProfileService::class);
    $characterProfileDTO = $characterProfileService->getProfile('hyjal', 'thrall');

    $head = collect($characterProfileDTO->appearances)->firstWhere('slot', 'HEAD');
    $weapon = collect($characterProfileDTO->appearances)->firstWhere('slot', 'WEAPON');

    expect($head['total'])->toBe(2)
        ->and($head['completed'])->toBe(1) // id 321 compté, id 999 inconnu ignoré
        ->and($weapon['total'])->toBe(1)
        ->and($weapon['completed'])->toBe(0)
        ->and($characterProfileDTO->appearancesCount)->toBe(1);

    // La garde-robe alimente le score : 1 apparence sur 3, tous slots confondus.
    $transmog = collect($characterProfileDTO->score->dimensions)->firstWhere('key', 'transmog');

    expect($characterProfileDTO->score->version)->toBe(ScoreWeights::VERSION)
        ->and($characterProfileDTO->score->dimensions)->toHaveCount(count(ScoreWeights::WEIGHTS))
        ->and($transmog->completed)->toBe(1.0)
        ->and($transmog->total)->toBe(3);
});

test('aggregate progress groups by expansion and zone', function (): void {
    \App\Models\WowAchievement::factory()->create([
        'id' => 1685,
        'expansion_id' => \App\Domain\ValueObjects\ExpansionId::UNCLASSIFIED,
        'category_name' => 'Évènements mondiaux',
        'is_active' => true,
    ]);
    WowQuest::factory()->create([
        'id' => 1,
        'name_fr' => 'Quête Classic',
        'expansion_id' => 0,
        'zone_name' => 'Durotar',
        'is_active' => true,
    ]);
    WowQuest::factory()->create([
        'id' => 2,
        'name_fr' => 'Quête Classic 2',
        'expansion_id' => 0,
        'zone_name' => 'Durotar',
        'is_active' => true,
    ]);
    WowQuest::factory()->create([
        'id' => 3,
        'name_fr' => 'Quête TWW',
        'expansion_id' => 10,
        'zone_name' => 'Khaz Algar',
        'is_active' => true,
    ]);

    $mock = $this->partialMock(BlizzardApiClient::class);

    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('get');
    $exp->andReturn([
        'name' => 'Test',
        'realm' => ['name' => 'Test'],
        'race' => ['name' => 'Human'],
        'character_class' => ['id' => 1, 'name' => 'Warrior'],
        'level' => 80,
        'equipped_item_level' => 600,
        'faction' => ['name' => 'Alliance'],
    ]);

    /** @var \Mockery\Expectation $seasonExp */
    $seasonExp = $mock->shouldReceive('getCurrentMythicSeasonId');
    $seasonExp->andReturn(0);

    $mock->shouldReceive('getRegion')->andReturn('eu');

    $this->mock(UserCharacterService::class)->shouldReceive('getClassIcons')->andReturn([]);

    mockAsyncEndpoints($mock, [
        'character-media' => ['assets' => [['key' => 'avatar', 'value' => '']]],
        'quests/completed' => ['quests' => [['id' => 1]]],
        'achievements' => ['achievements' => []],
        'collections/mounts' => ['mounts' => []],
        'collections/pets' => ['pets' => []],
        'collections/decor' => ['decor_collected' => []],
        '/professions' => ['primaries' => [], 'secondaries' => []],
        '/reputations' => ['reputations' => []],
    ]);

    $characterProfileService = resolve(CharacterProfileService::class);
    $characterProfileDTO = $characterProfileService->getProfile('test', 'test');

    /** @var array{quests: array{total: int, completed: int}, achievements: array{total: int, completed: int}} $classicData */
    $classicData = $characterProfileDTO->collections[0];
    expect($classicData['quests']['total'])->toBe(2)
        ->and($classicData['quests']['completed'])->toBe(1);

    /** @var array{quests: array{total: int, completed: int}, achievements: array{total: int, completed: int}} $twwData */
    $twwData = $characterProfileDTO->collections[10];
    expect($twwData['quests']['total'])->toBe(1)
        ->and($twwData['quests']['completed'])->toBe(0);

    /** @var array{achievements: array{total: int}} $unclassifiedData */
    $unclassifiedData = $characterProfileDTO->collections[\App\Domain\ValueObjects\ExpansionId::UNCLASSIFIED];
    expect($unclassifiedData['achievements']['total'])->toBe(1)
        ->and(array_keys($characterProfileDTO->collections))->toBe([...range(0, 11), \App\Domain\ValueObjects\ExpansionId::UNCLASSIFIED]);
});

test('aggregate progress filters quests by character faction', function (): void {
    WowQuest::factory()->create([
        'id' => 1,
        'name_fr' => 'Quête neutre',
        'expansion_id' => 0,
        'zone_name' => 'Durotar',
        'faction' => null,
        'is_active' => true,
    ]);
    WowQuest::factory()->create([
        'id' => 2,
        'name_fr' => 'Quête Horde',
        'expansion_id' => 0,
        'zone_name' => 'Durotar',
        'faction' => 'Horde',
        'is_active' => true,
    ]);
    WowQuest::factory()->create([
        'id' => 3,
        'name_fr' => 'Quête Alliance',
        'expansion_id' => 0,
        'zone_name' => 'Durotar',
        'faction' => 'Alliance',
        'is_active' => true,
    ]);

    $mock = $this->partialMock(BlizzardApiClient::class);

    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('get');
    $exp->andReturn([
        'name' => 'Thrall',
        'realm' => ['name' => 'Hyjal'],
        'race' => ['name' => 'Orc'],
        'character_class' => ['id' => 7, 'name' => 'Chaman'],
        'level' => 80,
        'equipped_item_level' => 600,
        'faction' => ['name' => 'Horde'],
    ]);

    /** @var \Mockery\Expectation $seasonExp */
    $seasonExp = $mock->shouldReceive('getCurrentMythicSeasonId');
    $seasonExp->andReturn(0);

    $mock->shouldReceive('getRegion')->andReturn('eu');

    $this->mock(UserCharacterService::class)->shouldReceive('getClassIcons')->andReturn([]);

    mockAsyncEndpoints($mock, [
        'character-media' => ['assets' => [['key' => 'avatar', 'value' => '']]],
        'quests/completed' => ['quests' => [['id' => 1], ['id' => 2]]],
        'achievements' => ['achievements' => []],
        'collections/mounts' => ['mounts' => []],
        'collections/pets' => ['pets' => []],
        'collections/decor' => ['decor_collected' => []],
        '/professions' => ['primaries' => [], 'secondaries' => []],
        '/reputations' => ['reputations' => []],
    ]);

    $characterProfileService = resolve(CharacterProfileService::class);
    $characterProfileDTO = $characterProfileService->getProfile('hyjal', 'thrall');

    /** @var array{quests: array{total: int, completed: int}} $classicData */
    $classicData = $characterProfileDTO->collections[0];
    // Should see 2 quests (neutral + Horde), NOT the Alliance quest
    expect($classicData['quests']['total'])->toBe(2)
        ->and($classicData['quests']['completed'])->toBe(2);
});

test('get profile exposes current season raid progression', function (): void {
    $mock = $this->partialMock(BlizzardApiClient::class);

    $summary = [
        'name' => 'Thrall',
        'realm' => ['name' => 'Hyjal'],
        'race' => ['name' => 'Orc'],
        'character_class' => ['id' => 7, 'name' => 'Chaman'],
        'level' => 80,
        'equipped_item_level' => 620,
        'faction' => ['name' => 'Horde'],
    ];

    // journal-instance résout les noms FR ; le profil renvoie le résumé.
    /** @var \Mockery\Expectation $profileExp */
    $profileExp = $mock->shouldReceive('get');
    $profileExp->andReturnUsing(function (string $endpoint) use ($summary): array {
        if (str_contains($endpoint, 'journal-instance/1307')) {
            return [
                'name' => 'La Flèche du Vide',
                'encounters' => [['id' => 2733, 'name' => 'Empereur Averzian']],
            ];
        }

        return $summary;
    });

    /** @var \Mockery\Expectation $seasonExp */
    $seasonExp = $mock->shouldReceive('getCurrentMythicSeasonId');
    $seasonExp->andReturn(0);
    $mock->shouldReceive('getRegion')->andReturn('eu');
    $this->mock(UserCharacterService::class)->shouldReceive('getClassIcons')->andReturn([]);

    mockAsyncEndpoints($mock, [
        'character-media' => ['assets' => [['key' => 'avatar', 'value' => '']]],
        'quests/completed' => ['quests' => []],
        'achievements' => ['achievements' => []],
        'collections/mounts' => ['mounts' => []],
        'collections/pets' => ['pets' => []],
        'collections/decor' => ['decor_collected' => []],
        '/professions' => ['primaries' => [], 'secondaries' => []],
        '/reputations' => ['reputations' => []],
        'encounters/raids' => [
            'expansions' => [
                [
                    'expansion' => ['id' => 516, 'name' => 'Midnight'],
                    'instances' => [
                        [
                            'instance' => ['id' => 1307, 'name' => 'The Voidspire'],
                            'modes' => [[
                                'difficulty' => ['type' => 'LFR', 'name' => 'Raids'],
                                'status' => ['type' => 'COMPLETE'],
                                'progress' => ['completed_count' => 6, 'total_count' => 6, 'encounters' => []],
                            ]],
                        ],
                    ],
                ],
                [
                    'expansion' => ['id' => 505, 'name' => 'Current Season'],
                    'instances' => [
                        [
                            'instance' => ['id' => 1307, 'name' => 'The Voidspire'],
                            'modes' => [[
                                'difficulty' => ['type' => 'MYTHIC', 'name' => 'Mythique'],
                                'status' => ['type' => 'IN_PROGRESS'],
                                'progress' => [
                                    'completed_count' => 3,
                                    'total_count' => 6,
                                    'encounters' => [
                                        ['encounter' => ['id' => 2733, 'name' => 'Imperator Averzian'], 'completed_count' => 1, 'last_kill_timestamp' => 1775411018000],
                                    ],
                                ],
                            ]],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $characterProfileService = resolve(CharacterProfileService::class);
    $characterProfileDTO = $characterProfileService->getProfile('hyjal', 'thrall');

    expect($characterProfileDTO->raids)->toHaveCount(1)
        ->and($characterProfileDTO->raids[0]['instance_name'])->toBe('La Flèche du Vide')
        ->and($characterProfileDTO->raids[0]['modes'][0]['difficulty_type'])->toBe('MYTHIC')
        ->and($characterProfileDTO->raids[0]['modes'][0]['completed_count'])->toBe(3)
        ->and($characterProfileDTO->raids[0]['modes'][0]['encounters'][0]['name'])->toBe('Empereur Averzian')
        ->and($characterProfileDTO->raidsCount)->toBe(3);
});

test('get profile falls back to raw raid names when journal-instance lookup fails', function (): void {
    $summary = [
        'name' => 'Thrall',
        'realm' => ['name' => 'Hyjal'],
        'race' => ['name' => 'Orc'],
        'character_class' => ['id' => 7, 'name' => 'Chaman'],
        'level' => 80,
        'equipped_item_level' => 620,
        'faction' => ['name' => 'Horde'],
    ];

    $mock = $this->partialMock(BlizzardApiClient::class);

    /** @var \Mockery\Expectation $profileExp */
    $profileExp = $mock->shouldReceive('get');
    $profileExp->andReturnUsing(function (string $endpoint) use ($summary): array {
        throw_if(str_contains($endpoint, 'journal-instance'), \RuntimeException::class, 'static data unavailable');

        return $summary;
    });

    /** @var \Mockery\Expectation $seasonExp */
    $seasonExp = $mock->shouldReceive('getCurrentMythicSeasonId');
    $seasonExp->andReturn(0);
    $mock->shouldReceive('getRegion')->andReturn('eu');
    $this->mock(UserCharacterService::class)->shouldReceive('getClassIcons')->andReturn([]);

    mockAsyncEndpoints($mock, [
        'character-media' => ['assets' => [['key' => 'avatar', 'value' => '']]],
        'quests/completed' => ['quests' => []],
        'achievements' => ['achievements' => []],
        'collections/mounts' => ['mounts' => []],
        'collections/pets' => ['pets' => []],
        'collections/decor' => ['decor_collected' => []],
        '/professions' => ['primaries' => [], 'secondaries' => []],
        '/reputations' => ['reputations' => []],
        'encounters/raids' => [
            'expansions' => [
                [
                    'expansion' => ['id' => 505, 'name' => 'Current Season'],
                    'instances' => [
                        [
                            'instance' => ['id' => 1307, 'name' => 'The Voidspire'],
                            'modes' => [[
                                'difficulty' => ['type' => 'LFR', 'name' => 'Raids'],
                                'status' => ['type' => 'COMPLETE'],
                                'progress' => [
                                    'completed_count' => 1,
                                    'total_count' => 1,
                                    'encounters' => [
                                        ['encounter' => ['id' => 2733, 'name' => 'Imperator Averzian'], 'completed_count' => 1, 'last_kill_timestamp' => 1775411018000],
                                    ],
                                ],
                            ]],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $characterProfileService = resolve(CharacterProfileService::class);
    $characterProfileDTO = $characterProfileService->getProfile('hyjal', 'thrall');

    expect($characterProfileDTO->raids)->toHaveCount(1)
        ->and($characterProfileDTO->raids[0]['instance_name'])->toBe('The Voidspire')
        ->and($characterProfileDTO->raids[0]['modes'][0]['encounters'][0]['name'])->toBe('Imperator Averzian');
});

test('get profile has null raids when no current season progression', function (): void {
    $mock = $this->partialMock(BlizzardApiClient::class);

    /** @var \Mockery\Expectation $profileExp */
    $profileExp = $mock->shouldReceive('get');
    $profileExp->andReturn([
        'name' => 'Thrall',
        'realm' => ['name' => 'Hyjal'],
        'race' => ['name' => 'Orc'],
        'character_class' => ['id' => 7, 'name' => 'Chaman'],
        'level' => 80,
        'equipped_item_level' => 620,
        'faction' => ['name' => 'Horde'],
    ]);

    /** @var \Mockery\Expectation $seasonExp */
    $seasonExp = $mock->shouldReceive('getCurrentMythicSeasonId');
    $seasonExp->andReturn(0);
    $mock->shouldReceive('getRegion')->andReturn('eu');
    $this->mock(UserCharacterService::class)->shouldReceive('getClassIcons')->andReturn([]);

    mockAsyncEndpoints($mock, [
        'character-media' => ['assets' => [['key' => 'avatar', 'value' => '']]],
        'quests/completed' => ['quests' => []],
        'achievements' => ['achievements' => []],
        'collections/mounts' => ['mounts' => []],
        'collections/pets' => ['pets' => []],
        'collections/decor' => ['decor_collected' => []],
        '/professions' => ['primaries' => [], 'secondaries' => []],
        '/reputations' => ['reputations' => []],
        'encounters/raids' => [],
    ]);

    $characterProfileService = resolve(CharacterProfileService::class);
    $characterProfileDTO = $characterProfileService->getProfile('hyjal', 'thrall');

    expect($characterProfileDTO->raids)->toBeNull()
        ->and($characterProfileDTO->raidsCount)->toBe(0);
});

test('get profile handles decor api 404 gracefully', function (): void {
    WowDecor::factory()->create([
        'id' => 500,
        'name_fr' => 'Foyer orné',
        'is_active' => true,
    ]);

    $mock = $this->partialMock(BlizzardApiClient::class);

    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('get');
    $exp->andReturn([
        'name' => 'Test',
        'realm' => ['name' => 'Test'],
        'race' => ['name' => 'Human'],
        'character_class' => ['id' => 1, 'name' => 'Warrior'],
        'level' => 80,
        'equipped_item_level' => 600,
        'faction' => ['name' => 'Alliance'],
    ]);

    /** @var \Mockery\Expectation $seasonExp */
    $seasonExp = $mock->shouldReceive('getCurrentMythicSeasonId');
    $seasonExp->andReturn(0);

    $mock->shouldReceive('getRegion')->andReturn('eu');

    $this->mock(UserCharacterService::class)->shouldReceive('getClassIcons')->andReturn([]);

    // Mock async - decor returns 404 (empty), others return normally
    /** @var \Mockery\Expectation $asyncExp */
    $asyncExp = $mock->shouldReceive('getAsync');
    $asyncExp->andReturnUsing(function (string $endpoint): FulfilledPromise {
        if (str_contains($endpoint, 'collections/decor')) {
            // Simulate 404 → async resolves with empty body (handled as empty in fetchAsync)
            return new FulfilledPromise(
                new \GuzzleHttp\Psr7\Response(404, [], json_encode([], JSON_THROW_ON_ERROR)),
            );
        }

        $responses = [
            'character-media' => ['assets' => [['key' => 'avatar', 'value' => '']]],
            'quests/completed' => ['quests' => []],
            'achievements' => ['achievements' => []],
            'collections/mounts' => ['mounts' => []],
            'collections/pets' => ['pets' => []],
            '/professions' => ['primaries' => [], 'secondaries' => []],
            '/reputations' => ['reputations' => []],
        ];

        foreach ($responses as $pattern => $data) {
            if (str_contains($endpoint, $pattern)) {
                return asyncResponse($data);
            }
        }

        return asyncResponse([]);
    });

    $characterProfileService = resolve(CharacterProfileService::class);
    $characterProfileDTO = $characterProfileService->getProfile('test', 'test');

    // Decor API failed but profile still works — no decor collected
    expect($characterProfileDTO->decorCount)->toBe(0)
        ->and($characterProfileDTO->decor)->toHaveCount(1)
        ->and($characterProfileDTO->decor[0]['is_completed'])->toBeFalse();
});

const PROFILE_SNAPSHOT_FLAGS = JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

/**
 * @return array<string, array<string, mixed>>
 */
function fullProfileEndpoints(): array
{
    return [
        'character-media' => [
            'assets' => [
                ['key' => 'avatar', 'value' => 'https://render.com/avatar.jpg'],
                ['key' => 'inset', 'value' => 'https://render.com/inset.jpg'],
            ],
        ],
        'quests/completed' => ['quests' => [['id' => 100], ['id' => 101]]],
        'achievements' => [
            'total_points' => 12345,
            'achievements' => [
                ['id' => 6, 'completed_timestamp' => 1_600_000_000_000],
                ['id' => 7],
            ],
        ],
        'collections/mounts' => ['mounts' => [['mount' => ['id' => 200]]]],
        'collections/pets' => ['pets' => [['species' => ['id' => 300]], ['species' => ['id' => 301]]]],
        'collections/decor' => ['decor_collected' => [['decor' => ['id' => 500]]]],
        'collections/transmogs' => [
            'slots' => [
                ['slot' => ['type' => 'HEAD'], 'appearances' => [['id' => 9001], ['id' => '9002'], ['name' => 'no id']]],
                ['slot' => ['type' => 'CHEST']],
            ],
        ],
        '/professions' => [
            'primaries' => [[
                'profession' => ['id' => 164, 'name' => 'Blacksmithing'],
                'skill_points' => 100,
                'max_skill_points' => 100,
                'tiers' => [[
                    'tier' => ['name' => 'Khaz Algar Blacksmithing'],
                    'skill_points' => 50,
                    'max_skill_points' => 100,
                    'known_recipes' => [['id' => 1]],
                ]],
            ]],
            'secondaries' => [],
        ],
        '/reputations' => [
            'reputations' => [[
                'faction' => ['id' => 2590, 'name' => 'Conseil de Dornogal'],
                'standing' => ['raw' => 100, 'value' => 100, 'max' => 2500, 'tier' => 0, 'name' => 'Renom 3', 'renown_level' => 3],
            ]],
        ],
        'mythic-keystone-profile' => ['current_period' => ['period' => ['id' => 1000]]],
        'encounters/raids' => [
            'expansions' => [[
                'expansion' => ['id' => 505, 'name' => 'Current Season'],
                'instances' => [[
                    'instance' => ['id' => 1307, 'name' => 'The Voidspire'],
                    'modes' => [
                        [
                            'difficulty' => ['type' => 'NORMAL', 'name' => 'Normal'],
                            'status' => ['type' => 'COMPLETE'],
                            'progress' => ['completed_count' => 1, 'total_count' => 1, 'encounters' => [
                                ['encounter' => ['id' => 2733, 'name' => 'Imperator Averzian'], 'completed_count' => 2, 'last_kill_timestamp' => 1775411018000],
                            ]],
                        ],
                        [
                            'difficulty' => ['type' => 'HEROIC', 'name' => 'Héroïque'],
                            'status' => ['type' => 'IN_PROGRESS'],
                            'progress' => ['completed_count' => 1, 'total_count' => 2, 'encounters' => [
                                ['encounter' => ['id' => 2733, 'name' => 'Imperator Averzian'], 'completed_count' => 1, 'last_kill_timestamp' => 1775411018000],
                            ]],
                        ],
                    ],
                ]],
            ]],
        ],
        '/equipment' => [
            'equipped_items' => [
                [
                    'slot' => ['type' => 'HEAD', 'name' => 'Tête'],
                    'item' => ['id' => 12345],
                    'name' => 'Casque du Néant',
                    'quality' => ['type' => 'EPIC', 'name' => 'Épique'],
                    'level' => ['value' => 639],
                ],
                [
                    'slot' => ['type' => 'NECK', 'name' => 'Cou'],
                    'item' => ['id' => 23456],
                    'name' => 'Collier',
                    'quality' => ['type' => 'RARE', 'name' => 'Rare'],
                    'level' => ['value' => 610],
                ],
            ],
        ],
        'media/item/12345' => ['assets' => [['key' => 'icon', 'value' => 'https://render.com/icon-12345.jpg']]],
        'media/item/23456' => ['assets' => [['key' => 'other', 'value' => 'https://render.com/other.jpg']]],
    ];
}

/**
 * @return array<string, mixed>
 */
function fullMythicSeason(): array
{
    return [
        'season' => ['id' => 15],
        'mythic_rating' => ['rating' => 2456.789, 'color' => ['r' => 255, 'g' => 128, 'b' => 0, 'a' => 1.0]],
        'best_runs' => [
            [
                'dungeon' => ['id' => 501, 'name' => 'Faille'],
                'keystone_level' => 12,
                'duration' => 1_800_000,
                'completed_timestamp' => 1_700_000_000_000,
                'is_completed_within_time' => true,
                'mythic_rating' => ['rating' => 250.45, 'color' => ['r' => 1, 'g' => 2, 'b' => 3, 'a' => 1.0]],
                'map_rating' => ['rating' => 300.04, 'color' => ['r' => 4, 'g' => 5, 'b' => 6, 'a' => 1.0]],
                'members' => [
                    ['character' => ['name' => 'Thrall', 'realm' => ['name' => 'Hyjal']], 'specialization' => ['name' => 'Amélioration'], 'equipped_item_level' => 620],
                    ['character' => ['name' => 'Jaina']],
                ],
            ],
            [
                'dungeon' => ['id' => 502, 'name' => 'Prieuré'],
                'keystone_level' => 14,
                'duration' => 2_000_000,
                'completed_timestamp' => 1_700_000_100_000,
                'is_completed_within_time' => false,
                'mythic_rating' => ['rating' => 280],
                'map_rating' => ['rating' => 310.96],
                'members' => [],
            ],
        ],
    ];
}

function configureFullProfileClient(\Mockery\MockInterface $mock): void
{

    /** @var \Mockery\Expectation $getExp */
    $getExp = $mock->shouldReceive('get');
    $getExp->andReturnUsing(function (string $endpoint): array {
        if (str_contains($endpoint, 'journal-instance/1307')) {
            return [
                'name' => 'La Flèche du Vide',
                'encounters' => [['id' => 2733, 'name' => 'Empereur Averzian'], ['id' => 2734, 'name' => 'Deuxième boss']],
            ];
        }

        if (str_contains($endpoint, 'mythic-keystone-profile/season/15')) {
            return fullMythicSeason();
        }

        return [
            'name' => 'Thrall',
            'realm' => ['name' => 'Hyjal'],
            'race' => ['name' => 'Orc'],
            'character_class' => ['id' => 7, 'name' => 'Chaman'],
            'level' => 80,
            'equipped_item_level' => 620,
            'faction' => ['name' => 'Horde'],
            'guild' => ['name' => 'Les Loups'],
        ];
    });

    $mock->shouldReceive('getCurrentMythicSeasonId')->andReturn(15);
    $mock->shouldReceive('getRegion')->andReturn('eu');

    $endpoints = fullProfileEndpoints();
    /** @var \Mockery\Expectation $asyncExp */
    $asyncExp = $mock->shouldReceive('getAsync');
    $asyncExp->andReturnUsing(function (string $endpoint) use ($endpoints): FulfilledPromise {
        foreach ($endpoints as $pattern => $data) {
            if (str_contains($endpoint, $pattern)) {
                return asyncResponse($data);
            }
        }

        return asyncResponse([]);
    });
}

function seedFullProfileCatalog(): void
{
    WowQuest::factory()->create(['id' => 100, 'name_fr' => 'Quête A', 'expansion_id' => 0, 'zone_name' => 'Elwynn', 'is_active' => true]);
    WowQuest::factory()->create(['id' => 102, 'name_fr' => 'Quête B', 'expansion_id' => 0, 'zone_name' => 'Elwynn', 'is_active' => true]);
    WowMount::factory()->create(['id' => 200, 'name_fr' => 'Loup noir', 'source' => null, 'is_active' => true]);
    WowMount::factory()->create(['id' => 201, 'name_fr' => 'Loup blanc', 'source' => 'Vendeur', 'is_active' => true]);
    WowPet::factory()->create(['id' => 300, 'name_fr' => 'Dragonnet', 'source' => 'Drop', 'is_active' => true]);
    WowDecor::factory()->create(['id' => 500, 'name_fr' => 'Foyer', 'item_id' => 245000, 'is_active' => true]);
    WowAppearance::factory()->create(['id' => 9001, 'slot' => 'HEAD', 'category' => 'Armure', 'is_active' => true]);
    WowAppearance::factory()->create(['id' => 9003, 'slot' => 'CHEST', 'category' => 'Armure', 'is_active' => true]);
}

test('get profile renders the full profile unchanged', function (): void {
    seedFullProfileCatalog();
    configureFullProfileClient($this->partialMock(BlizzardApiClient::class));
    $this->mock(UserCharacterService::class)->shouldReceive('getClassIcons')->andReturn([7 => 'https://render.com/class-icon.jpg']);

    $characterProfileDTO = resolve(CharacterProfileService::class)->getProfile('Hyjal', 'Thrall');

    expect(json_encode($characterProfileDTO, PROFILE_SNAPSHOT_FLAGS))->toMatchSnapshot();
});

test('get profile falls back to empty values when every endpoint is empty', function (): void {
    $mock = $this->partialMock(BlizzardApiClient::class);
    $mock->shouldReceive('get')->andReturnUsing(function (string $endpoint): array {
        throw_if(str_contains($endpoint, '/season/'), RuntimeException::class, 'season unavailable');

        return [];
    });
    $mock->shouldReceive('getCurrentMythicSeasonId')->andReturn(15);
    $mock->shouldReceive('getRegion')->andReturn('eu');
    $mock->shouldReceive('getAsync')->andReturnUsing(fn (): FulfilledPromise => asyncResponse([]));
    $this->mock(UserCharacterService::class)->shouldReceive('getClassIcons')->andReturn([]);

    $characterProfileDTO = resolve(CharacterProfileService::class)->getProfile('Hyjal', 'Nobody');

    expect(json_encode($characterProfileDTO, PROFILE_SNAPSHOT_FLAGS))->toMatchSnapshot();
});
