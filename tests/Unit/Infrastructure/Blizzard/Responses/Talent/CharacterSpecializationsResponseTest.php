<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\ResponsePayload;
use App\Infrastructure\Blizzard\Responses\Talent\CharacterSpecializationsResponse;

/**
 * @param  array<string, mixed>  $decoded
 */
function characterSpecializations(array $decoded): CharacterSpecializationsResponse
{
    return CharacterSpecializationsResponse::fromPayload(
        ResponsePayload::forEndpoint('profile/wow/character/hyjal/thrall/specializations', $decoded),
    );
}

test('the active loadout is the active one of the active specialization', function (): void {
    $characterSpecializationsResponse = characterSpecializations([
        'active_specialization' => ['id' => 72],
        'specializations' => [
            ['specialization' => ['id' => 71], 'loadouts' => [['is_active' => true, 'selected_class_talents' => [['id' => 1]]]]],
            ['specialization' => ['id' => 72], 'loadouts' => [
                ['is_active' => false, 'selected_class_talents' => [['id' => 2]]],
                [
                    'is_active' => true,
                    'selected_class_talents' => [['id' => 3, 'rank' => 2, 'tooltip' => ['talent' => ['id' => 30]]]],
                    'selected_spec_talents' => [['id' => 4, 'rank' => 1]],
                    'selected_hero_talents' => [['id' => 5]],
                    'selected_hero_talent_tree' => ['id' => 60],
                ],
            ]],
        ],
    ]);

    $loadout = $characterSpecializationsResponse->activeLoadout;

    expect($characterSpecializationsResponse->activeSpecializationId)->toBe(72)
        ->and($loadout?->classTalents[0]->nodeId)->toBe(3)
        ->and($loadout?->classTalents[0]->rank)->toBe(2)
        ->and($loadout?->classTalents[0]->talentId)->toBe(30)
        ->and($loadout?->specTalents[0]->nodeId)->toBe(4)
        ->and($loadout?->specTalents[0]->talentId)->toBeNull()
        ->and($loadout?->heroTalents[0]->rank)->toBeNull()
        ->and($loadout?->heroTreeId)->toBe(60);
});

test('a later entry of the same specialization is searched when the first has no active loadout', function (): void {
    $characterSpecializationsResponse = characterSpecializations([
        'active_specialization' => ['id' => 72],
        'specializations' => [
            ['specialization' => ['id' => 72], 'loadouts' => [['is_active' => false]]],
            ['specialization' => ['id' => 72], 'loadouts' => [['is_active' => true, 'selected_hero_talent_tree' => ['id' => 61]]]],
        ],
    ]);

    expect($characterSpecializationsResponse->activeLoadout?->heroTreeId)->toBe(61);
});

test('a character without active loadout has none', function (): void {
    $characterSpecializationsResponse = characterSpecializations([
        'active_specialization' => ['id' => 72],
        'specializations' => [['specialization' => ['id' => 72], 'loadouts' => [['is_active' => false]]]],
    ]);

    expect($characterSpecializationsResponse->activeLoadout)->toBeNull();
});

test('a character without active specialization is a normal case', function (): void {
    $characterSpecializationsResponse = characterSpecializations(['active_specialization' => [], 'specializations' => []]);

    expect($characterSpecializationsResponse->activeSpecializationId)->toBeNull()
        ->and($characterSpecializationsResponse->activeLoadout)->toBeNull();
});

test('an empty loadout selects nothing', function (): void {
    $loadout = characterSpecializations([
        'active_specialization' => ['id' => 72],
        'specializations' => [['specialization' => ['id' => 72], 'loadouts' => [['is_active' => true]]]],
    ])->activeLoadout;

    expect($loadout?->classTalents)->toBe([])
        ->and($loadout?->specTalents)->toBe([])
        ->and($loadout?->heroTalents)->toBe([])
        ->and($loadout?->heroTreeId)->toBeNull();
});
