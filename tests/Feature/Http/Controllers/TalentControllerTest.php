<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardApiClient;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;

test('show returns talent data for character', function (): void {
    // Pre-cache the spell icon so the controller does not attempt getAsync
    \Illuminate\Support\Facades\Cache::put('spell_icon:100', 'https://render.worldofwarcraft.com/eu/icons/56/charge.jpg', 60);
    // Ensure clean talent tree cache
    \Illuminate\Support\Facades\Cache::forget('talent_tree:v2:786:72');
    \Illuminate\Support\Facades\Cache::forget('playable_spec:72');

    $mock = $this->partialMock(BlizzardApiClient::class);

    // Specializations endpoint
    /** @var \Mockery\Expectation $specExp */
    $specExp = $mock->shouldReceive('get');
    $specExp->once()
        ->with('profile/wow/character/hyjal/thrall/specializations', [])
        ->andReturn([
            'active_specialization' => ['name' => 'Fureur', 'id' => 72],
            'specializations' => [
                [
                    'specialization' => ['name' => 'Fureur', 'id' => 72],
                    'loadouts' => [
                        [
                            'is_active' => true,
                            'selected_class_talents' => [],
                            'selected_spec_talents' => [],
                            'selected_hero_talents' => [],
                            'selected_hero_talent_tree' => ['id' => 60, 'name' => 'Colosse'],
                        ],
                    ],
                ],
            ],
        ]);

    // Playable specialization (for talentTreeId resolution)
    /** @var \Mockery\Expectation $regionExp */
    $regionExp = $mock->shouldReceive('getRegion');
    $regionExp->andReturn('eu');

    /** @var \Mockery\Expectation $playableExp */
    $playableExp = $mock->shouldReceive('get');
    $playableExp->once()
        ->with('data/wow/playable-specialization/72', ['namespace' => 'static-eu'])
        ->andReturn([
            'spec_talent_tree' => ['key' => ['href' => 'https://eu.api.blizzard.com/data/wow/talent-tree/786/playable-specialization/72']],
        ]);

    // Talent tree structure
    /** @var \Mockery\Expectation $treeExp */
    $treeExp = $mock->shouldReceive('get');
    $treeExp->once()
        ->with('data/wow/talent-tree/786/playable-specialization/72', ['namespace' => 'static-eu'])
        ->andReturn([
            'id' => 786,
            'playable_class' => ['name' => 'Guerrier', 'id' => 1],
            'playable_specialization' => ['name' => 'Fureur', 'id' => 72],
            'class_talent_nodes' => [
                [
                    'id' => 1,
                    'node_type' => ['id' => 0, 'type' => 'ACTIVE'],
                    'ranks' => [['rank' => 1, 'tooltip' => ['talent' => ['name' => 'Charge', 'id' => 10], 'spell_tooltip' => ['spell' => ['name' => 'Charge', 'id' => 100]]]]],
                    'display_row' => 1,
                    'display_col' => 5,
                ],
            ],
            'spec_talent_nodes' => [],
            'hero_talent_trees' => [],
        ]);

    $this->getJson('/api/character/hyjal/thrall/talents')
        ->assertOk()
        ->assertJsonFragment([
            'spec_name' => 'Fureur',
            'class_name' => 'Guerrier',
        ])
        ->assertJsonStructure([
            'spec_name',
            'spec_id',
            'class_name',
            'class_nodes',
            'spec_nodes',
            'hero_trees',
        ]);
});

test('show returns 404 when no active specialization', function (): void {
    $mock = $this->partialMock(BlizzardApiClient::class);

    /** @var \Mockery\Expectation $specExp */
    $specExp = $mock->shouldReceive('get');
    $specExp->once()
        ->with('profile/wow/character/hyjal/unknown/specializations', [])
        ->andReturn([
            'active_specialization' => [],
            'specializations' => [],
        ]);

    $this->getJson('/api/character/hyjal/unknown/talents')
        ->assertNotFound();
});

test('show returns 500 on API error', function (): void {
    $mock = $this->partialMock(BlizzardApiClient::class);

    /** @var \Mockery\Expectation $specExp */
    $specExp = $mock->shouldReceive('get');
    $specExp->once()
        ->with('profile/wow/character/hyjal/thrall/specializations', [])
        ->andThrow(new RuntimeException('API error'));

    $this->getJson('/api/character/hyjal/thrall/talents')
        ->assertStatus(500)
        ->assertJsonStructure(['error']);
});

/**
 * @return array<string, mixed>
 */
function richTalentTree(): array
{
    $rank = fn (int $talentId, string $name, int $spellId): array => [
        'rank' => 1,
        'tooltip' => ['talent' => ['id' => $talentId, 'name' => $name], 'spell_tooltip' => ['spell' => ['id' => $spellId, 'name' => $name]]],
    ];

    return [
        'id' => 786,
        'playable_class' => ['name' => 'Guerrier', 'id' => 1],
        'playable_specialization' => ['name' => 'Fureur', 'id' => 72],
        'class_talent_nodes' => [
            ['id' => 1, 'node_type' => ['type' => 'ACTIVE'], 'ranks' => [$rank(10, 'Charge', 100)], 'display_row' => 1, 'display_col' => 5, 'unlocks' => [2, 3]],
            ['id' => 2, 'node_type' => ['type' => 'PASSIVE'], 'ranks' => [$rank(20, 'Rage', 200), $rank(20, 'Rage', 200)], 'display_row' => 2, 'display_col' => 4, 'locked_by' => [1]],
            [
                'id' => 3,
                'node_type' => ['type' => 'CHOICE'],
                'ranks' => [['rank' => 1, 'choice_of_tooltips' => [
                    ['talent' => ['id' => 30, 'name' => 'Choix A'], 'spell_tooltip' => ['spell' => ['id' => 300]]],
                    ['talent' => ['id' => 31, 'name' => 'Choix B'], 'spell_tooltip' => ['spell' => ['id' => 301]]],
                ]]],
                'display_row' => 2,
                'display_col' => 6,
                'locked_by' => [1],
            ],
            ['id' => 4, 'node_type' => ['type' => 'ACTIVE'], 'ranks' => [], 'display_row' => 3, 'display_col' => 5],
            ['id' => 5, 'ranks' => [['rank' => 1]], 'display_row' => 3, 'display_col' => 6],
            ['id' => 900, 'node_type' => ['type' => 'ACTIVE'], 'ranks' => [$rank(9000, 'Doublon héroïque', 90000)], 'display_row' => 9, 'display_col' => 9],
        ],
        'spec_talent_nodes' => [
            ['id' => 50, 'node_type' => ['type' => 'ACTIVE'], 'ranks' => [$rank(500, 'Tourbillon', 5000)], 'display_row' => 1, 'display_col' => 1],
        ],
        'hero_talent_trees' => [
            [
                'id' => 60,
                'name' => 'Colosse',
                'playable_specializations' => [['id' => 71], ['id' => 72]],
                'hero_talent_nodes' => [
                    ['id' => 900, 'node_type' => ['type' => 'ACTIVE'], 'ranks' => [$rank(9000, 'Doublon héroïque', 90000)], 'display_row' => 1, 'display_col' => 1],
                ],
            ],
            [
                'id' => 61,
                'name' => 'Massacreur',
                'playable_specializations' => [['id' => 72]],
                'hero_talent_nodes' => [
                    ['id' => 910, 'node_type' => ['type' => 'PASSIVE'], 'ranks' => [$rank(9100, 'Massacre', 91000)], 'display_row' => 1, 'display_col' => 1],
                ],
            ],
            [
                'id' => 62,
                'name' => 'Autre spé',
                'playable_specializations' => [['id' => 73]],
                'hero_talent_nodes' => [],
            ],
        ],
    ];
}

test('show renders the whole talent tab unchanged', function (): void {
    Cache::put('spell_icon:100', 'https://render.com/charge.jpg', 60);

    $mock = $this->partialMock(BlizzardApiClient::class);
    $mock->shouldReceive('getRegion')->andReturn('eu');
    $mock->shouldReceive('get')->andReturnUsing(fn (string $endpoint): array => match ($endpoint) {
        'profile/wow/character/hyjal/thrall/specializations' => [
            'active_specialization' => ['name' => 'Fureur', 'id' => 72],
            'specializations' => [
                ['specialization' => ['id' => 71], 'loadouts' => [['is_active' => true, 'selected_class_talents' => [['id' => 1, 'rank' => 1]]]]],
                [
                    'specialization' => ['name' => 'Fureur', 'id' => 72],
                    'loadouts' => [
                        ['is_active' => false, 'selected_class_talents' => [['id' => 2, 'rank' => 2]]],
                        [
                            'is_active' => true,
                            'selected_class_talents' => [
                                ['id' => 1, 'rank' => 1, 'tooltip' => ['talent' => ['id' => 10]]],
                                ['id' => 2, 'rank' => 1],
                                ['id' => 3, 'rank' => 1, 'tooltip' => ['talent' => ['id' => 31]]],
                                ['rank' => 1],
                            ],
                            'selected_spec_talents' => [['id' => 50, 'rank' => 1, 'tooltip' => ['talent' => ['id' => 500]]]],
                            'selected_hero_talents' => [['id' => 900, 'rank' => 1, 'tooltip' => ['talent' => ['id' => 9000]]]],
                            'selected_hero_talent_tree' => ['id' => 60, 'name' => 'Colosse'],
                        ],
                    ],
                ],
            ],
        ],
        'data/wow/playable-specialization/72' => [
            'spec_talent_tree' => ['key' => ['href' => 'https://eu.api.blizzard.com/data/wow/talent-tree/786/playable-specialization/72?namespace=static-eu']],
        ],
        'data/wow/talent-tree/786/playable-specialization/72' => richTalentTree(),
    });
    $mock->shouldReceive('getAsync')->andReturnUsing(function (string $endpoint): FulfilledPromise {
        $spellId = (int) basename($endpoint);
        $assets = $spellId === 301
            ? [['key' => 'other', 'value' => 'https://render.com/other.jpg']]
            : [['key' => 'icon', 'value' => sprintf('https://render.com/spell-%d.jpg', $spellId)]];

        return new FulfilledPromise(new Response(200, [], json_encode(['assets' => $assets], JSON_THROW_ON_ERROR)));
    });

    $content = $this->getJson('/api/character/hyjal/thrall/talents')->assertOk()->getContent();

    expect(json_encode(json_decode((string) $content), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
        ->toMatchSnapshot();
});

test('the talent tree is fetched once, then read back from the cache', function (string $store): void {
    if ($store === 'redis') {
        useRedisCache();
    }

    $mock = $this->partialMock(BlizzardApiClient::class);
    $mock->shouldReceive('getRegion')->andReturn('eu');
    $mock->shouldReceive('get')->with('profile/wow/character/hyjal/thrall/specializations', [])->twice()->andReturn([
        'active_specialization' => ['id' => 72],
        'specializations' => [['specialization' => ['id' => 72], 'loadouts' => [['is_active' => true, 'selected_hero_talent_tree' => ['id' => 60]]]]],
    ]);
    $mock->shouldReceive('get')->with('data/wow/playable-specialization/72', ['namespace' => 'static-eu'])->once()->andReturn([
        'spec_talent_tree' => ['key' => ['href' => 'https://eu.api.blizzard.com/data/wow/talent-tree/786/playable-specialization/72']],
    ]);
    $mock->shouldReceive('get')->with('data/wow/talent-tree/786/playable-specialization/72', ['namespace' => 'static-eu'])->once()->andReturn(richTalentTree());
    $mock->shouldReceive('getAsync')->andReturnUsing(fn (string $endpoint): FulfilledPromise => new FulfilledPromise(new Response(200, [], json_encode([
        'assets' => [['key' => 'icon', 'value' => 'https://render.com/'.basename($endpoint).'.jpg']],
    ], JSON_THROW_ON_ERROR))));

    $first = $this->getJson('/api/character/hyjal/thrall/talents')->assertOk()->json();
    $second = $this->getJson('/api/character/hyjal/thrall/talents')->assertOk()->json();

    expect($second)->toBe($first)
        ->and($first['class_nodes'][0]['entries'][0]['icon_url'])->toBe('https://render.com/100.jpg');
})->with(['array', 'redis']);
