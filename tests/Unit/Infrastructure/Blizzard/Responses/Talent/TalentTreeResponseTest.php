<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\Exceptions\UnexpectedFieldTypeException;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;
use App\Infrastructure\Blizzard\Responses\Talent\TalentTreeResponse;

/**
 * @param  array<string, mixed>  $decoded
 */
function talentTree(array $decoded): TalentTreeResponse
{
    return TalentTreeResponse::fromPayload(
        ResponsePayload::forEndpoint('data/wow/talent-tree/786/playable-specialization/72', $decoded),
    );
}

/**
 * @return array<string, mixed>
 */
function talentRank(int $talentId, int $spellId): array
{
    return ['rank' => 1, 'tooltip' => ['talent' => ['id' => $talentId, 'name' => 'Talent '.$talentId], 'spell_tooltip' => ['spell' => ['id' => $spellId]]]];
}

test('a complete tree carries its class, specialization, nodes and hero trees', function (): void {
    $talentTreeResponse = talentTree([
        'playable_class' => ['name' => 'Guerrier'],
        'playable_specialization' => ['name' => 'Fureur', 'id' => 72],
        'class_talent_nodes' => [[
            'id' => 1,
            'node_type' => ['type' => 'ACTIVE'],
            'ranks' => [talentRank(10, 100), talentRank(10, 101)],
            'display_row' => 2,
            'display_col' => 5,
            'locked_by' => [7],
            'unlocks' => [2, 3],
        ]],
        'spec_talent_nodes' => [['id' => 50, 'ranks' => [talentRank(500, 5000)]]],
        'hero_talent_trees' => [[
            'id' => 60,
            'name' => 'Colosse',
            'playable_specializations' => [['id' => 71], ['id' => 72]],
            'hero_talent_nodes' => [['id' => 900, 'ranks' => [talentRank(9000, 90000)]]],
        ]],
    ]);

    $node = $talentTreeResponse->classNodes[0];
    $tooltip = $node->ranks[0]->tooltip;
    $heroTree = $talentTreeResponse->heroTrees[0];

    expect($talentTreeResponse->className)->toBe('Guerrier')
        ->and($talentTreeResponse->specName)->toBe('Fureur')
        ->and($talentTreeResponse->specId)->toBe(72)
        ->and($node->id)->toBe(1)
        ->and($node->type)->toBe('ACTIVE')
        ->and($node->ranks)->toHaveCount(2)
        ->and($node->displayRow)->toBe(2)
        ->and($node->displayCol)->toBe(5)
        ->and($node->lockedBy)->toBe([7])
        ->and($node->unlocks)->toBe([2, 3])
        ->and($tooltip?->talentId)->toBe(10)
        ->and($tooltip?->talentName)->toBe('Talent 10')
        ->and($tooltip?->spellId)->toBe(100)
        ->and($talentTreeResponse->specNodes[0]->id)->toBe(50)
        ->and($heroTree->id)->toBe(60)
        ->and($heroTree->name)->toBe('Colosse')
        ->and($heroTree->specializationIds)->toBe([71, 72])
        ->and($heroTree->nodes[0]->id)->toBe(900);
});

test('a choice rank carries one tooltip per option', function (): void {
    $rank = talentTree(['class_talent_nodes' => [[
        'id' => 3,
        'node_type' => ['type' => 'CHOICE'],
        'ranks' => [['choice_of_tooltips' => [
            ['talent' => ['id' => 30, 'name' => 'A'], 'spell_tooltip' => ['spell' => ['id' => 300]]],
            ['talent' => ['id' => 31, 'name' => 'B']],
        ]]],
    ]]])->classNodes[0]->ranks[0];

    expect($rank->tooltip)->toBeNull()
        ->and($rank->choices)->toHaveCount(2)
        ->and($rank->choices[0]->spellId)->toBe(300)
        ->and($rank->choices[1]->talentName)->toBe('B')
        ->and($rank->choices[1]->spellId)->toBeNull();
});

test('an empty tooltip counts as no tooltip', function (): void {
    expect(talentTree(['class_talent_nodes' => [['id' => 1, 'ranks' => [['tooltip' => []]]]]])->classNodes[0]->ranks[0]->tooltip)->toBeNull();
});

test('a sparse node leaves its missing fields null or empty', function (): void {
    $node = talentTree(['class_talent_nodes' => [[]]])->classNodes[0];

    expect($node->id)->toBeNull()
        ->and($node->type)->toBeNull()
        ->and($node->ranks)->toBe([])
        ->and($node->lockedBy)->toBe([])
        ->and($node->displayRow)->toBeNull();
});

test('an empty tree has no class, no specialization and no node', function (): void {
    $talentTreeResponse = talentTree([]);

    expect($talentTreeResponse->className)->toBeNull()
        ->and($talentTreeResponse->specId)->toBeNull()
        ->and($talentTreeResponse->classNodes)->toBe([])
        ->and($talentTreeResponse->specNodes)->toBe([])
        ->and($talentTreeResponse->heroTrees)->toBe([]);
});

test('spell ids cover every rank, choice and hero tree once, in reading order', function (): void {
    $talentTreeResponse = talentTree([
        'class_talent_nodes' => [
            ['id' => 1, 'ranks' => [talentRank(10, 100), talentRank(10, 101)]],
            ['id' => 3, 'ranks' => [['choice_of_tooltips' => [['spell_tooltip' => ['spell' => ['id' => 300]]], ['talent' => ['id' => 31]]]]]],
        ],
        'spec_talent_nodes' => [['id' => 50, 'ranks' => [talentRank(500, 100)]]],
        'hero_talent_trees' => [['id' => 60, 'hero_talent_nodes' => [['id' => 900, 'ranks' => [talentRank(9000, 90000)]]]]],
    ]);

    expect($talentTreeResponse->spellIds())->toBe([100, 101, 300, 90000]);
});

test('a dependency list holding something else than node ids breaks the contract', function (): void {
    talentTree(['class_talent_nodes' => [['id' => 1, 'unlocks' => ['2']]]]);
})->throws(UnexpectedFieldTypeException::class, 'class_talent_nodes.0.unlocks.0');
