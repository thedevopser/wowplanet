<?php

declare(strict_types=1);

use App\Application\Services\Progress\ProfessionProgressAggregator;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterProfessionsResponse;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;
use App\Models\WowProfession;
use App\Models\WowRecipe;

beforeEach(function (): void {
    $this->profession = WowProfession::factory()->create([
        'id' => 171,
        'name_fr' => 'Alchimie',
        'type' => 'primary',
        'max_skill_levels' => [0 => 300, 10 => 100],
    ]);
});

test('aggregate returns profession progress with expansion breakdown', function (): void {
    WowRecipe::factory()->create(['id' => 1, 'profession_id' => 171, 'expansion_id' => 0, 'category_name' => 'Potions', 'is_active' => true]);
    WowRecipe::factory()->create(['id' => 2, 'profession_id' => 171, 'expansion_id' => 0, 'category_name' => 'Potions', 'is_active' => true]);
    WowRecipe::factory()->create(['id' => 3, 'profession_id' => 171, 'expansion_id' => 10, 'category_name' => 'Flacons', 'is_active' => true]);

    $professionResponse = [
        'primaries' => [
            [
                'profession' => ['id' => 171, 'name' => 'Alchemy'],
                'skill_points' => 300,
                'max_skill_points' => 300,
                'tiers' => [
                    [
                        'tier' => ['name' => 'Classic'],
                        'skill_points' => 300,
                        'max_skill_points' => 300,
                        'known_recipes' => [['id' => 1]],
                    ],
                    [
                        'tier' => ['name' => 'Khaz Algar'],
                        'skill_points' => 50,
                        'max_skill_points' => 100,
                        'known_recipes' => [['id' => 3]],
                    ],
                ],
            ],
        ],
        'secondaries' => [],
    ];

    $aggregator = new ProfessionProgressAggregator;
    $result = professionProgress($professionResponse, '');

    expect($result)->toHaveCount(1);
    expect($result[0]['profession_id'])->toBe(171);
    expect($result[0]['profession_name'])->toBe('Alchimie');
    expect($result[0]['type'])->toBe('primary');

    // Classic expansion recipes
    expect($result[0]['expansions'][0]['total'])->toBe(2);
    expect($result[0]['expansions'][0]['completed'])->toBe(1);

    // TWW expansion recipes
    expect($result[0]['expansions'][10]['total'])->toBe(1);
    expect($result[0]['expansions'][10]['completed'])->toBe(1);
});

test('aggregate filters recipes by faction', function (): void {
    WowRecipe::factory()->create(['id' => 10, 'profession_id' => 171, 'expansion_id' => 0, 'category_name' => 'Potions', 'faction' => 'Alliance', 'is_active' => true]);
    WowRecipe::factory()->create(['id' => 11, 'profession_id' => 171, 'expansion_id' => 0, 'category_name' => 'Potions', 'faction' => 'Horde', 'is_active' => true]);
    WowRecipe::factory()->create(['id' => 12, 'profession_id' => 171, 'expansion_id' => 0, 'category_name' => 'Potions', 'faction' => null, 'is_active' => true]);

    $professionResponse = [
        'primaries' => [
            [
                'profession' => ['id' => 171, 'name' => 'Alchemy'],
                'skill_points' => 0,
                'max_skill_points' => 0,
                'tiers' => [],
            ],
        ],
        'secondaries' => [],
    ];

    $aggregator = new ProfessionProgressAggregator;
    $result = professionProgress($professionResponse, 'Alliance');

    // Alliance sees Alliance + null recipes only (2, not Horde)
    expect($result[0]['expansions'][0]['total'])->toBe(2);
});

test('deduplicateRankedRecipes keeps completed version of same-name recipes', function (): void {
    WowRecipe::factory()->create(['id' => 100, 'name_fr' => 'Potion de soin', 'profession_id' => 171, 'expansion_id' => 0, 'category_name' => 'Potions', 'is_active' => true]);
    WowRecipe::factory()->create(['id' => 101, 'name_fr' => 'Potion de soin', 'profession_id' => 171, 'expansion_id' => 0, 'category_name' => 'Potions', 'is_active' => true]);

    $professionResponse = [
        'primaries' => [
            [
                'profession' => ['id' => 171, 'name' => 'Alchemy'],
                'skill_points' => 0,
                'max_skill_points' => 0,
                'tiers' => [
                    [
                        'tier' => ['name' => 'Classic'],
                        'skill_points' => 0,
                        'max_skill_points' => 300,
                        'known_recipes' => [['id' => 100]], // Only older rank completed
                    ],
                ],
            ],
        ],
        'secondaries' => [],
    ];

    $aggregator = new ProfessionProgressAggregator;
    $result = professionProgress($professionResponse, '');

    // Deduplication: 2 recipes same name → 1 entry, completed (since ID 100 is completed)
    expect($result[0]['expansions'][0]['total'])->toBe(1);
    expect($result[0]['expansions'][0]['completed'])->toBe(1);
});

test('aggregate handles secondary professions', function (): void {
    $cooking = WowProfession::factory()->create([
        'id' => 185,
        'name_fr' => 'Cuisine',
        'type' => 'secondary',
        'max_skill_levels' => [0 => 300],
    ]);

    WowRecipe::factory()->create(['id' => 200, 'profession_id' => 185, 'expansion_id' => 0, 'category_name' => 'Plats', 'is_active' => true]);

    $professionResponse = [
        'primaries' => [],
        'secondaries' => [
            [
                'profession' => ['id' => 185, 'name' => 'Cooking'],
                'skill_points' => 100,
                'max_skill_points' => 300,
                'tiers' => [],
            ],
        ],
    ];

    $aggregator = new ProfessionProgressAggregator;
    $result = professionProgress($professionResponse, '');

    expect($result)->toHaveCount(1);
    expect($result[0]['profession_id'])->toBe(185);
    expect($result[0]['type'])->toBe('secondary');
});

/**
 * @param  array<string, mixed>  $professionsResponse
 * @return list<array<string, mixed>>
 */
function professionProgress(array $professionsResponse, string $characterFaction): array
{
    return (new ProfessionProgressAggregator)->aggregate(
        CharacterProfessionsResponse::fromPayload(ResponsePayload::forEndpoint('profile/wow/character/hyjal/thrall/professions', $professionsResponse)),
        $characterFaction,
    );
}

test('the profession payload is unchanged', function (): void {
    WowRecipe::factory()->create(['id' => 1, 'name_fr' => 'Potion', 'profession_id' => 171, 'expansion_id' => 0, 'category_name' => 'Potions', 'is_active' => true, 'faction' => null, 'wowhead_spell_id' => 111]);
    WowRecipe::factory()->create(['id' => 2, 'name_fr' => 'Potion', 'profession_id' => 171, 'expansion_id' => 0, 'category_name' => 'Potions', 'is_active' => true, 'faction' => null, 'wowhead_spell_id' => 112]);
    WowRecipe::factory()->create(['id' => 4, 'name_fr' => 'Élixir', 'profession_id' => 171, 'expansion_id' => 0, 'category_name' => 'Potions', 'is_active' => true, 'faction' => 'Horde', 'wowhead_spell_id' => null]);
    WowRecipe::factory()->create(['id' => 3, 'name_fr' => 'Flacon', 'profession_id' => 171, 'expansion_id' => 10, 'category_name' => 'Flacons', 'is_active' => true, 'faction' => null, 'wowhead_spell_id' => 113]);
    WowRecipe::factory()->create(['id' => 5, 'name_fr' => 'Sans catégorie', 'profession_id' => 171, 'expansion_id' => 10, 'category_name' => '', 'is_active' => true, 'faction' => null, 'wowhead_spell_id' => 114]);

    $result = professionProgress([
        'primaries' => [
            [
                'profession' => ['id' => 171, 'name' => 'Alchemy'],
                'skill_points' => 300,
                'max_skill_points' => 300,
                'tiers' => [
                    ['tier' => ['name' => 'Classic Alchemy'], 'skill_points' => 300, 'max_skill_points' => 300, 'known_recipes' => [['id' => 2]]],
                    ['tier' => ['name' => 'Khaz Algar Alchemy'], 'skill_points' => 50, 'max_skill_points' => 100, 'known_recipes' => [['id' => 3]]],
                    ['tier' => ['name' => 'Unknown tier']],
                ],
            ],
            ['profession' => ['id' => 202, 'name' => 'Engineering']],
        ],
        'secondaries' => [
            ['profession' => ['id' => 356, 'name' => 'Fishing'], 'skill_points' => 10, 'max_skill_points' => 300],
            ['profession' => ['id' => 794, 'name' => 'Archaeology'], 'tiers' => [['skill_points' => 5, 'max_skill_points' => 950]]],
        ],
    ], 'Alliance');

    expect(json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))->toMatchSnapshot();
});

/**
 * @param  list<int>  $knownRecipeIds
 * @return array<string, mixed>
 */
function alchemyResponse(array $knownRecipeIds = [], ?array $tiers = null): array
{
    return [
        'primaries' => [[
            'profession' => ['id' => 171, 'name' => 'Alchemy'],
            'tiers' => $tiers ?? [[
                'tier' => ['name' => 'Classic'],
                'skill_points' => 0,
                'max_skill_points' => 300,
                'known_recipes' => array_map(fn (int $id): array => ['id' => $id], $knownRecipeIds),
            ]],
        ]],
        'secondaries' => [],
    ];
}

function healingPotionRanks(int ...$ids): void
{
    foreach ($ids as $id) {
        WowRecipe::factory()->create(['id' => $id, 'name_fr' => 'Potion de soin', 'profession_id' => 171, 'expansion_id' => 0, 'category_name' => 'Potions', 'is_active' => true]);
    }
}

test('deduplicateRankedRecipes shows the highest rank when none is known', function (): void {
    healingPotionRanks(100, 102, 101);

    $items = professionProgress(alchemyResponse(), '')[0]['expansions'][0]['categories'][0]['items'];

    expect(array_column($items, 'id'))->toBe([102]);
});

test('deduplicateRankedRecipes shows the highest known rank', function (): void {
    healingPotionRanks(100, 101, 102);

    $items = professionProgress(alchemyResponse([100, 101]), '')[0]['expansions'][0]['categories'][0]['items'];

    expect(array_column($items, 'id'))->toBe([101]);
});

test('aggregate counts no category for a recipe without one and keeps the following categories', function (): void {
    WowRecipe::factory()->create(['id' => 1, 'profession_id' => 171, 'expansion_id' => 0, 'category_name' => null, 'is_active' => true]);
    WowRecipe::factory()->create(['id' => 2, 'profession_id' => 171, 'expansion_id' => 0, 'category_name' => 'Potions', 'is_active' => true]);

    $expansion = professionProgress(alchemyResponse(), '')[0]['expansions'][0];

    expect(array_column($expansion['categories'], 'name'))->toBe(['Potions'])
        ->and($expansion['total'])->toBe(1);
});

test('aggregate reads the in-game ceiling of an expansion the character has no tier for', function (): void {
    $expansions = professionProgress(alchemyResponse(tiers: []), '')[0]['expansions'];

    expect($expansions[10]['max_skill_points'])->toBe(100)
        ->and($expansions[10]['has_tier'])->toBeFalse()
        ->and($expansions[10]['tier_exists'])->toBeTrue()
        ->and($expansions[9]['max_skill_points'])->toBe(0);
});

test('aggregate names a profession missing from the catalogue', function (int $id, ?string $apiName, string $name, string $type): void {
    $profession = $apiName === null ? ['id' => $id] : ['id' => $id, 'name' => $apiName];
    $response = ['primaries' => [['profession' => $profession, 'tiers' => []]], 'secondaries' => []];

    $progress = professionProgress($response, '')[0];

    expect($progress['profession_name'])->toBe($name)
        ->and($progress['type'])->toBe($type);
})->with([
    'nom français connu' => [164, 'Blacksmithing', 'Forge', 'primary'],
    'métier secondaire' => [356, 'Fishing', 'Pêche', 'secondary'],
    'nom de l\'API à défaut' => [999, 'Tinkering', 'Tinkering', 'primary'],
    'aucun nom' => [998, null, '', 'primary'],
]);
