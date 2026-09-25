<?php

declare(strict_types=1);

use App\Application\Services\Progress\TalentAggregator;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;
use App\Infrastructure\Blizzard\Responses\Talent\CharacterSpecializationsResponse;
use App\Infrastructure\Blizzard\Responses\Talent\TalentTreeResponse;

/**
 * @param  array<string, mixed>  $specializations
 * @param  array<string, mixed>  $talentTree
 * @param  array<int, string>  $spellIcons
 * @return array<string, mixed>
 */
function aggregateTalents(TalentAggregator $talentAggregator, array $specializations, array $talentTree, array $spellIcons = []): array
{
    return $talentAggregator->aggregate(
        CharacterSpecializationsResponse::fromPayload(ResponsePayload::forEndpoint('profile/wow/character/hyjal/thrall/specializations', $specializations)),
        TalentTreeResponse::fromPayload(ResponsePayload::forEndpoint('data/wow/talent-tree/786/playable-specialization/72', $talentTree)),
        $spellIcons,
    );
}

function heroTree(int $id, string $name, array $nodes, array $specIds = [72]): array
{
    return [
        'id' => $id,
        'name' => $name,
        'hero_talent_nodes' => $nodes,
        'playable_specializations' => array_map(fn (int $specId): array => ['id' => $specId], $specIds),
    ];
}

function makeSpecializationsResponse(array $selectedClassTalents = [], array $selectedSpecTalents = [], array $selectedHeroTalents = [], int $heroTreeId = 60): array
{
    return [
        'active_specialization' => ['name' => 'Fureur', 'id' => 72],
        'specializations' => [
            [
                'specialization' => ['name' => 'Fureur', 'id' => 72],
                'loadouts' => [
                    [
                        'is_active' => true,
                        'selected_class_talents' => $selectedClassTalents,
                        'selected_spec_talents' => $selectedSpecTalents,
                        'selected_hero_talents' => $selectedHeroTalents,
                        'selected_hero_talent_tree' => ['id' => $heroTreeId, 'name' => 'Colosse'],
                    ],
                ],
            ],
        ],
    ];
}

function makeTalentTreeResponse(array $classNodes = [], array $specNodes = [], array $heroTrees = []): array
{
    return [
        'id' => 786,
        'playable_class' => ['name' => 'Guerrier', 'id' => 1],
        'playable_specialization' => ['name' => 'Fureur', 'id' => 72],
        'class_talent_nodes' => $classNodes,
        'spec_talent_nodes' => $specNodes,
        'hero_talent_trees' => $heroTrees,
    ];
}

function makeNode(int $id, int $row, int $col, string $type = 'ACTIVE', array $extraRank = [], array $extra = []): array
{
    $rank = array_merge([
        'rank' => 1,
        'tooltip' => [
            'talent' => ['name' => 'Talent '.$id, 'id' => $id * 10],
            'spell_tooltip' => ['spell' => ['name' => 'Sort '.$id, 'id' => $id * 100]],
        ],
    ], $extraRank);

    return array_merge([
        'id' => $id,
        'node_type' => ['id' => $type === 'CHOICE' ? 2 : ($type === 'PASSIVE' ? 1 : 0), 'type' => $type],
        'ranks' => [$rank],
        'display_row' => $row,
        'display_col' => $col,
        'raw_position_x' => $col * 1000,
        'raw_position_y' => $row * 1000,
    ], $extra);
}

test('aggregate transforms talent tree with selected loadout', function (): void {
    $specResponse = makeSpecializationsResponse(
        selectedClassTalents: [
            ['id' => 1, 'rank' => 1, 'tooltip' => ['talent' => ['id' => 10]]],
            ['id' => 2, 'rank' => 2, 'tooltip' => ['talent' => ['id' => 20]]],
        ],
    );

    $treeResponse = makeTalentTreeResponse(
        classNodes: [
            makeNode(1, 1, 1),
            makeNode(2, 2, 1, extra: ['locked_by' => [1], 'unlocks' => []]),
        ],
    );

    $aggregator = new TalentAggregator;
    $result = aggregateTalents($aggregator, $specResponse, $treeResponse);

    expect($result['spec_name'])->toBe('Fureur')
        ->and($result['spec_id'])->toBe(72)
        ->and($result['class_name'])->toBe('Guerrier')
        ->and($result['class_nodes'])->toHaveCount(2);

    $node1 = collect($result['class_nodes'])->firstWhere('id', 1);
    expect($node1['selected_rank'])->toBe(1)
        ->and($node1['x'])->toBe(1)
        ->and($node1['y'])->toBe(1)
        ->and($node1['type'])->toBe('active')
        ->and($node1['entries'])->toHaveCount(1);

    $node2 = collect($result['class_nodes'])->firstWhere('id', 2);
    expect($node2['selected_rank'])->toBe(2)
        ->and($node2['max_rank'])->toBe(1)
        ->and($node2['locked_by'])->toBe([1]);
});

test('aggregate handles empty loadouts', function (): void {
    $specResponse = [
        'active_specialization' => ['name' => 'Fureur', 'id' => 72],
        'specializations' => [
            [
                'specialization' => ['name' => 'Fureur', 'id' => 72],
                'loadouts' => [],
            ],
        ],
    ];

    $treeResponse = makeTalentTreeResponse(
        classNodes: [makeNode(1, 1, 1)],
    );

    $aggregator = new TalentAggregator;
    $result = aggregateTalents($aggregator, $specResponse, $treeResponse);

    expect($result['class_nodes'])->toHaveCount(1);
    $node = $result['class_nodes'][0];
    expect($node['selected_rank'])->toBe(0);
});

test('aggregate handles choice nodes correctly', function (): void {
    $choiceRank = [
        'rank' => 1,
        'choice_of_tooltips' => [
            [
                'talent' => ['name' => 'Option A', 'id' => 100],
                'spell_tooltip' => ['spell' => ['name' => 'Sort A', 'id' => 1000]],
            ],
            [
                'talent' => ['name' => 'Option B', 'id' => 200],
                'spell_tooltip' => ['spell' => ['name' => 'Sort B', 'id' => 2000]],
            ],
        ],
    ];

    $specResponse = makeSpecializationsResponse(
        selectedClassTalents: [
            ['id' => 5, 'rank' => 1, 'tooltip' => ['talent' => ['id' => 200]]],
        ],
    );

    $treeResponse = makeTalentTreeResponse(
        classNodes: [
            [
                'id' => 5,
                'node_type' => ['id' => 2, 'type' => 'CHOICE'],
                'ranks' => [$choiceRank],
                'display_row' => 3,
                'display_col' => 2,
                'raw_position_x' => 2000,
                'raw_position_y' => 3000,
            ],
        ],
    );

    $aggregator = new TalentAggregator;
    $result = aggregateTalents($aggregator, $specResponse, $treeResponse);

    $node = $result['class_nodes'][0];
    expect($node['type'])->toBe('choice')
        ->and($node['entries'])->toHaveCount(2)
        ->and($node['entries'][0]['selected'])->toBeFalse()
        ->and($node['entries'][1]['selected'])->toBeTrue()
        ->and($node['entries'][1]['name'])->toBe('Option B');
});

test('aggregate transforms hero talent trees', function (): void {
    $specResponse = makeSpecializationsResponse(
        selectedHeroTalents: [
            ['id' => 10, 'rank' => 1, 'tooltip' => ['talent' => ['id' => 100]]],
        ],
        heroTreeId: 60,
    );

    $treeResponse = makeTalentTreeResponse(
        heroTrees: [
            [
                'id' => 60,
                'name' => 'Colosse',
                'hero_talent_nodes' => [makeNode(10, 1, 1), makeNode(11, 2, 1)],
                'playable_specializations' => [['id' => 71], ['id' => 72]],
            ],
            [
                'id' => 61,
                'name' => 'Tonnerre',
                'hero_talent_nodes' => [makeNode(20, 1, 1)],
                'playable_specializations' => [['id' => 72], ['id' => 73]],
            ],
        ],
    );

    $aggregator = new TalentAggregator;
    $result = aggregateTalents($aggregator, $specResponse, $treeResponse);

    expect($result['hero_trees'])->toHaveCount(2);

    $colosse = collect($result['hero_trees'])->firstWhere('id', 60);
    expect($colosse['name'])->toBe('Colosse')
        ->and($colosse['active'])->toBeTrue()
        ->and($colosse['nodes'])->toHaveCount(2);

    $selectedNode = collect($colosse['nodes'])->firstWhere('id', 10);
    expect($selectedNode['selected_rank'])->toBe(1);

    $tonnerre = collect($result['hero_trees'])->firstWhere('id', 61);
    expect($tonnerre['active'])->toBeFalse()
        ->and($tonnerre['nodes'])->toHaveCount(1);
    expect($tonnerre['nodes'][0]['selected_rank'])->toBe(0);
});

test('aggregate filters hero trees by active spec', function (): void {
    // Fury is spec 72. Only trees with spec 72 in playable_specializations should be kept.
    $specResponse = makeSpecializationsResponse(heroTreeId: 60);

    $treeResponse = makeTalentTreeResponse(
        heroTrees: [
            [
                'id' => 60,
                'name' => 'Colosse',
                'hero_talent_nodes' => [makeNode(10, 1, 1)],
                'playable_specializations' => [['id' => 71], ['id' => 72]],
            ],
            [
                'id' => 61,
                'name' => 'Tonnerre',
                'hero_talent_nodes' => [makeNode(20, 1, 1)],
                'playable_specializations' => [['id' => 72], ['id' => 73]],
            ],
            [
                'id' => 62,
                'name' => 'Étranger',
                'hero_talent_nodes' => [makeNode(30, 1, 1)],
                'playable_specializations' => [['id' => 71], ['id' => 73]],
            ],
        ],
    );

    $aggregator = new TalentAggregator;
    $result = aggregateTalents($aggregator, $specResponse, $treeResponse);

    expect($result['hero_trees'])->toHaveCount(2);
    $names = array_column($result['hero_trees'], 'name');
    expect($names)->toContain('Colosse')
        ->and($names)->toContain('Tonnerre')
        ->and($names)->not->toContain('Étranger');
});

test('aggregate skips nodes with empty ranks', function (): void {
    $treeResponse = makeTalentTreeResponse(
        classNodes: [
            [
                'id' => 99,
                'node_type' => ['id' => 0, 'type' => 'ACTIVE'],
                'ranks' => [],
                'display_row' => 1,
                'display_col' => 1,
            ],
            [
                'id' => 100,
                'node_type' => ['id' => 2, 'type' => 'CHOICE'],
                'ranks' => [['rank' => 1]],
                'display_row' => 2,
                'display_col' => 1,
            ],
        ],
    );

    $specResponse = makeSpecializationsResponse();

    $aggregator = new TalentAggregator;
    $result = aggregateTalents($aggregator, $specResponse, $treeResponse);

    expect($result['class_nodes'])->toHaveCount(0);
});

test('aggregate renders a node with its position, ranks, links and entry', function (): void {
    $specResponse = makeSpecializationsResponse(selectedSpecTalents: [['id' => 7, 'rank' => 2, 'tooltip' => ['talent' => ['id' => 70]]]]);
    $treeResponse = makeTalentTreeResponse(specNodes: [
        makeNode(7, 4, 3, 'PASSIVE', extra: [
            'ranks' => [makeNode(7, 0, 0)['ranks'][0], ['rank' => 2]],
            'locked_by' => [5, 6],
            'unlocks' => [8],
        ]),
    ]);

    $result = aggregateTalents(new TalentAggregator, $specResponse, $treeResponse, [700 => 'https://render.test/sort-7.jpg']);

    expect($result['spec_nodes'])->toBe([[
        'id' => 7,
        'x' => 3,
        'y' => 4,
        'type' => 'passive',
        'max_rank' => 2,
        'selected_rank' => 2,
        'locked_by' => [5, 6],
        'unlocks' => [8],
        'entries' => [['talent_id' => 70, 'spell_id' => 700, 'name' => 'Talent 7', 'icon_url' => 'https://render.test/sort-7.jpg']],
    ]]);
});

test('aggregate falls back to neutral values when Blizzard omits a field', function (): void {
    $specResponse = makeSpecializationsResponse(selectedClassTalents: [['id' => 1]]);
    $treeResponse = [
        'class_talent_nodes' => [['id' => 1, 'ranks' => [['tooltip' => ['talent' => ['id' => 10]]]]]],
        'hero_talent_trees' => [['hero_talent_nodes' => [], 'playable_specializations' => [['id' => 72]]]],
    ];

    $result = aggregateTalents(new TalentAggregator, $specResponse, $treeResponse, [0 => 'https://render.test/inconnu.jpg']);

    expect($result['spec_name'])->toBe('')
        ->and($result['spec_id'])->toBe(0)
        ->and($result['class_name'])->toBe('')
        ->and($result['spec_nodes'])->toBe([])
        ->and($result['hero_trees'])->toBe([['id' => 0, 'name' => '', 'active' => false, 'nodes' => []]])
        ->and($result['class_nodes'])->toBe([[
            'id' => 1,
            'x' => 0,
            'y' => 0,
            'type' => 'active',
            'max_rank' => 1,
            'selected_rank' => 0,
            'locked_by' => [],
            'unlocks' => [],
            'entries' => [['talent_id' => 10, 'spell_id' => 0, 'name' => '', 'icon_url' => null]],
        ]]);
});

test('aggregate names an entry without talent id nor name neutrally', function (): void {
    $treeResponse = makeTalentTreeResponse(classNodes: [['id' => 1, 'ranks' => [['tooltip' => ['spell_tooltip' => ['spell' => ['id' => 100]]]]]]]);

    $entry = aggregateTalents(new TalentAggregator, makeSpecializationsResponse(), $treeResponse)['class_nodes'][0]['entries'][0];

    expect($entry)->toBe(['talent_id' => 0, 'spell_id' => 100, 'name' => '', 'icon_url' => null]);
});

test('aggregate keeps every hero tree when the specialization is unknown', function (): void {
    $treeResponse = makeTalentTreeResponse(heroTrees: [heroTree(60, 'Colosse', [], [71]), heroTree(61, 'Tonnerre', [], [73])]);
    unset($treeResponse['playable_specialization']);

    $result = aggregateTalents(new TalentAggregator, makeSpecializationsResponse(), $treeResponse);

    expect(array_column($result['hero_trees'], 'name'))->toBe(['Colosse', 'Tonnerre']);
});

test('aggregate drops from the class and spec trees the nodes duplicated from an available hero tree', function (): void {
    $treeResponse = makeTalentTreeResponse(
        classNodes: [makeNode(10, 1, 1), makeNode(1, 1, 2), makeNode(4, 1, 3), makeNode(30, 1, 4)],
        specNodes: [makeNode(11, 1, 1), makeNode(2, 1, 2)],
        heroTrees: [
            heroTree(60, 'Colosse', [makeNode(10, 1, 1), makeNode(11, 2, 1), makeNode(1, 3, 1), ['ranks' => []]]),
            heroTree(62, 'Étranger', [makeNode(30, 1, 1)], [71]),
        ],
    );

    $result = aggregateTalents(new TalentAggregator, makeSpecializationsResponse(), $treeResponse);

    expect(array_column($result['class_nodes'], 'id'))->toBe([4, 30])
        ->and(array_column($result['spec_nodes'], 'id'))->toBe([2])
        ->and(array_column($result['hero_trees'][0]['nodes'], 'id'))->toBe([10, 11, 1]);
});

test('aggregate skips a node without id and keeps the following ones', function (): void {
    $withoutId = makeNode(1, 1, 1);
    unset($withoutId['id']);

    $result = aggregateTalents(new TalentAggregator, makeSpecializationsResponse(), makeTalentTreeResponse(classNodes: [$withoutId, makeNode(2, 1, 2)]));

    expect(array_column($result['class_nodes'], 'id'))->toBe([2]);
});

test('aggregate skips a node whose rank has no tooltip', function (): void {
    $treeResponse = makeTalentTreeResponse(classNodes: [makeNode(1, 1, 1, extraRank: ['tooltip' => []]), makeNode(2, 1, 2)]);

    expect(array_column(aggregateTalents(new TalentAggregator, makeSpecializationsResponse(), $treeResponse)['class_nodes'], 'id'))->toBe([2]);
});

test('aggregate ignores a selected talent without node and reads the following ones', function (): void {
    $specResponse = makeSpecializationsResponse(selectedClassTalents: [
        ['rank' => 3, 'tooltip' => ['talent' => ['id' => 10]]],
        ['id' => 2, 'rank' => 1, 'tooltip' => ['talent' => ['id' => 20]]],
    ]);

    $result = aggregateTalents(new TalentAggregator, $specResponse, makeTalentTreeResponse(classNodes: [makeNode(1, 1, 1), makeNode(2, 1, 2)]));

    expect(array_column($result['class_nodes'], 'selected_rank'))->toBe([0, 1]);
});

test('aggregate reads a selected talent without rank as not taken', function (): void {
    $specResponse = makeSpecializationsResponse(selectedClassTalents: [['id' => 1, 'tooltip' => ['talent' => ['id' => 10]]]]);

    expect(aggregateTalents(new TalentAggregator, $specResponse, makeTalentTreeResponse(classNodes: [makeNode(1, 1, 1)]))['class_nodes'][0]['selected_rank'])->toBe(0);
});

test('aggregate selects no choice when the talent picked is unknown', function (): void {
    $specResponse = makeSpecializationsResponse(selectedClassTalents: [['id' => 5, 'rank' => 1]]);
    $choices = ['rank' => 1, 'choice_of_tooltips' => [
        ['talent' => ['name' => 'Sans identifiant']],
        ['talent' => ['name' => 'Option 1', 'id' => 1]],
        ['talent' => ['name' => 'Option -1', 'id' => -1]],
    ]];
    $treeResponse = makeTalentTreeResponse(classNodes: [['id' => 5, 'node_type' => ['type' => 'CHOICE'], 'ranks' => [$choices]]]);

    $entries = aggregateTalents(new TalentAggregator, $specResponse, $treeResponse)['class_nodes'][0]['entries'];

    expect(array_column($entries, 'selected'))->toBe([false, false, false]);
});

test('aggregate selects no choice on a node the loadout does not take', function (): void {
    $choices = ['rank' => 1, 'choice_of_tooltips' => [['talent' => ['name' => 'Option 1', 'id' => 1]], ['talent' => ['name' => 'Option 2', 'id' => 2]]]];
    $treeResponse = makeTalentTreeResponse(classNodes: [['id' => 5, 'node_type' => ['type' => 'CHOICE'], 'ranks' => [$choices]]]);

    $entries = aggregateTalents(new TalentAggregator, makeSpecializationsResponse(), $treeResponse)['class_nodes'][0]['entries'];

    expect(array_column($entries, 'selected'))->toBe([false, false]);
});

test('aggregate selects the choice picked when its talent id is one', function (): void {
    $specResponse = makeSpecializationsResponse(selectedClassTalents: [['id' => 5, 'rank' => 1, 'tooltip' => ['talent' => ['id' => 1]]]]);
    $choices = ['rank' => 1, 'choice_of_tooltips' => [['talent' => ['name' => 'Option 1', 'id' => 1]], ['talent' => ['name' => 'Option 2', 'id' => 2]]]];
    $treeResponse = makeTalentTreeResponse(classNodes: [['id' => 5, 'node_type' => ['type' => 'CHOICE'], 'ranks' => [$choices]]]);

    $entries = aggregateTalents(new TalentAggregator, $specResponse, $treeResponse)['class_nodes'][0]['entries'];

    expect(array_column($entries, 'selected'))->toBe([true, false]);
});

test('aggregate marks no hero tree active when the loadout has none', function (): void {
    $specResponse = makeSpecializationsResponse(selectedHeroTalents: [['id' => 10, 'rank' => 1]]);
    unset($specResponse['specializations'][0]['loadouts'][0]['selected_hero_talent_tree']);
    $treeResponse = makeTalentTreeResponse(heroTrees: [heroTree(1, 'Premier', [makeNode(10, 1, 1)]), heroTree(60, 'Colosse', [])]);

    $result = aggregateTalents(new TalentAggregator, $specResponse, $treeResponse);

    expect(array_column($result['hero_trees'], 'active'))->toBe([false, false])
        ->and($result['hero_trees'][0]['nodes'][0]['selected_rank'])->toBe(0);
});

test('aggregate marks no hero tree active when neither the loadout nor the tree is identified', function (): void {
    $specResponse = makeSpecializationsResponse();
    unset($specResponse['specializations'][0]['loadouts'][0]['selected_hero_talent_tree']);
    $treeResponse = makeTalentTreeResponse(heroTrees: [['name' => 'Sans identifiant', 'hero_talent_nodes' => [], 'playable_specializations' => [['id' => 72]]]]);

    expect(aggregateTalents(new TalentAggregator, $specResponse, $treeResponse)['hero_trees'][0]['active'])->toBeFalse();
});
