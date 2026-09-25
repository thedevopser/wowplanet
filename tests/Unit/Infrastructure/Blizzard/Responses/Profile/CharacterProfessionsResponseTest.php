<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\Exceptions\MissingFieldException;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterProfessionsResponse;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * @param  array<string, mixed>  $decoded
 */
function characterProfessions(array $decoded): CharacterProfessionsResponse
{
    return CharacterProfessionsResponse::fromPayload(ResponsePayload::forEndpoint('profile/wow/character/hyjal/thrall/professions', $decoded));
}

test('primary professions come first, then secondary ones', function (): void {
    $professions = characterProfessions([
        'secondaries' => [['profession' => ['id' => 356, 'name' => 'Fishing']]],
        'primaries' => [['profession' => ['id' => 171, 'name' => 'Alchemy']], ['profession' => ['id' => 202]]],
    ])->professions;

    expect(array_map(fn (\App\Infrastructure\Blizzard\Responses\Profile\CharacterProfession $characterProfession): int => $characterProfession->professionId, $professions))->toBe([171, 202, 356])
        ->and($professions[1]->professionName)->toBeNull();
});

test('a profession carries its global skill and its tiers with their known recipes', function (): void {
    $profession = characterProfessions(['primaries' => [[
        'profession' => ['id' => 171, 'name' => 'Alchemy'],
        'skill_points' => 300,
        'max_skill_points' => 300,
        'tiers' => [
            ['tier' => ['id' => 2871, 'name' => 'Khaz Algar Alchemy'], 'skill_points' => 50, 'max_skill_points' => 100, 'known_recipes' => [['id' => 3], ['id' => 4]]],
            ['skill_points' => 5],
        ],
    ]]])->professions[0];

    expect($profession->professionName)->toBe('Alchemy')
        ->and($profession->skillPoints)->toBe(300)
        ->and($profession->maxSkillPoints)->toBe(300)
        ->and($profession->tiers[0]->id)->toBe(2871)
        ->and($profession->tiers[0]->name)->toBe('Khaz Algar Alchemy')
        ->and($profession->tiers[0]->skillPoints)->toBe(50)
        ->and($profession->tiers[0]->maxSkillPoints)->toBe(100)
        ->and($profession->tiers[0]->knownRecipeIds)->toBe([3, 4])
        ->and($profession->tiers[1]->id)->toBeNull()
        ->and($profession->tiers[1]->name)->toBeNull()
        ->and($profession->tiers[1]->maxSkillPoints)->toBeNull()
        ->and($profession->tiers[1]->knownRecipeIds)->toBe([]);
});

test('a profession without its reference breaks the contract', function (): void {
    characterProfessions(['primaries' => [['skill_points' => 1]]]);
})->throws(MissingFieldException::class, 'primaries.0.profession');

test('a known recipe without id breaks the contract', function (): void {
    characterProfessions(['primaries' => [['profession' => ['id' => 171], 'tiers' => [['known_recipes' => [[]]]]]]]);
})->throws(MissingFieldException::class, 'primaries.0.tiers.0.known_recipes.0.id');

test('a character without profession has none', function (): void {
    expect(characterProfessions([])->professions)->toBe([]);
});
