<?php

declare(strict_types=1);

use App\Application\DTOs\CharacterProfileDTO;
use App\Application\DTOs\CrossCharacterProgress;
use App\Application\DTOs\FetchedCharacterProgress;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterProfessionsResponse;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterReputationsResponse;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

function makeReputationsPayload(int $factionId, int $renownLevel = 0, int $raw = 0, int $tier = 0, int $max = 1, string $standingName = ''): FetchedCharacterProgress
{
    return new FetchedCharacterProgress(
        questIds: [],
        achievementIds: [],
        reputations: CharacterReputationsResponse::fromPayload(ResponsePayload::forEndpoint('reputations', [
            'reputations' => [
                [
                    'faction' => ['id' => $factionId],
                    'standing' => [
                        'raw' => $raw,
                        'value' => 0,
                        'max' => $max,
                        'tier' => $tier,
                        'name' => $standingName,
                        'renown_level' => $renownLevel,
                    ],
                ],
            ],
        ])),
        professions: CharacterProfessionsResponse::fromPayload(ResponsePayload::forEndpoint('professions', [])),
    );
}

test('renown vs renown: higher renown wins regardless of merge order', function (): void {
    $progress1 = new CrossCharacterProgress;
    $progress1->mergeCharacter('CharA', makeReputationsPayload(factionId: 2503, renownLevel: 5, raw: 12500));
    $progress1->mergeCharacter('CharB', makeReputationsPayload(factionId: 2503, renownLevel: 9, raw: 22500));

    $progress2 = new CrossCharacterProgress;
    $progress2->mergeCharacter('CharB', makeReputationsPayload(factionId: 2503, renownLevel: 9, raw: 22500));
    $progress2->mergeCharacter('CharA', makeReputationsPayload(factionId: 2503, renownLevel: 5, raw: 12500));

    expect($progress1->bestFactionStandings[2503]['character_name'])->toBe('CharB')
        ->and($progress1->bestFactionStandings[2503]['renown_level'])->toBe(9)
        ->and($progress2->bestFactionStandings[2503]['character_name'])->toBe('CharB')
        ->and($progress2->bestFactionStandings[2503]['renown_level'])->toBe(9);
});

test('renown vs traditional with high raw: renown wins regardless of merge order', function (): void {
    $progress1 = new CrossCharacterProgress;
    $progress1->mergeCharacter('RenownChar', makeReputationsPayload(factionId: 2503, renownLevel: 9, raw: 10000));
    $progress1->mergeCharacter('LegacyChar', makeReputationsPayload(factionId: 2503, renownLevel: 0, raw: 42000, tier: 6));

    $progress2 = new CrossCharacterProgress;
    $progress2->mergeCharacter('LegacyChar', makeReputationsPayload(factionId: 2503, renownLevel: 0, raw: 42000, tier: 6));
    $progress2->mergeCharacter('RenownChar', makeReputationsPayload(factionId: 2503, renownLevel: 9, raw: 10000));

    expect($progress1->bestFactionStandings[2503]['character_name'])->toBe('RenownChar')
        ->and($progress1->bestFactionStandings[2503]['renown_level'])->toBe(9)
        ->and($progress2->bestFactionStandings[2503]['character_name'])->toBe('RenownChar')
        ->and($progress2->bestFactionStandings[2503]['renown_level'])->toBe(9);
});

test('traditional vs traditional: higher raw wins regardless of merge order', function (): void {
    $progress1 = new CrossCharacterProgress;
    $progress1->mergeCharacter('CharA', makeReputationsPayload(factionId: 72, raw: 30000, tier: 5));
    $progress1->mergeCharacter('CharB', makeReputationsPayload(factionId: 72, raw: 42000, tier: 7));

    $progress2 = new CrossCharacterProgress;
    $progress2->mergeCharacter('CharB', makeReputationsPayload(factionId: 72, raw: 42000, tier: 7));
    $progress2->mergeCharacter('CharA', makeReputationsPayload(factionId: 72, raw: 30000, tier: 5));

    expect($progress1->bestFactionStandings[72]['character_name'])->toBe('CharB')
        ->and($progress1->bestFactionStandings[72]['raw'])->toBe(42000)
        ->and($progress2->bestFactionStandings[72]['character_name'])->toBe('CharB')
        ->and($progress2->bestFactionStandings[72]['raw'])->toBe(42000);
});

test('unstarted character does not overwrite renown standing', function (): void {
    $progress = new CrossCharacterProgress;
    $progress->mergeCharacter('MainChar', makeReputationsPayload(factionId: 2503, renownLevel: 9, raw: 22500));
    $progress->mergeCharacter('AltChar', makeReputationsPayload(factionId: 2503, renownLevel: 0, raw: 0));

    expect($progress->bestFactionStandings[2503]['character_name'])->toBe('MainChar')
        ->and($progress->bestFactionStandings[2503]['renown_level'])->toBe(9);
});

test('equal renown does not overwrite existing entry', function (): void {
    $progress = new CrossCharacterProgress;
    $progress->mergeCharacter('FirstChar', makeReputationsPayload(factionId: 2503, renownLevel: 9, raw: 22500));
    $progress->mergeCharacter('SecondChar', makeReputationsPayload(factionId: 2503, renownLevel: 9, raw: 22500));

    expect($progress->bestFactionStandings[2503]['character_name'])->toBe('FirstChar');
});

/**
 * @param  list<array<string, mixed>>  $reputations
 * @param  list<array<string, mixed>>  $primaries
 */
function fetchedProgress(array $questIds = [], array $achievementIds = [], array $reputations = [], array $primaries = []): FetchedCharacterProgress
{
    return new FetchedCharacterProgress(
        questIds: $questIds,
        achievementIds: $achievementIds,
        reputations: CharacterReputationsResponse::fromPayload(ResponsePayload::forEndpoint('reputations', ['reputations' => $reputations])),
        professions: CharacterProfessionsResponse::fromPayload(ResponsePayload::forEndpoint('professions', ['primaries' => $primaries])),
    );
}

/**
 * @param  array<string, int|string>  $standing
 * @return array<string, mixed>
 */
function reputationEntry(?int $factionId, array $standing): array
{
    return [
        'faction' => $factionId === null ? ['name' => 'Sans identifiant'] : ['id' => $factionId],
        'standing' => $standing,
    ];
}

/**
 * @param  list<array<string, mixed>>  $tiers
 * @return array<string, mixed>
 */
function professionEntry(int $professionId, array $tiers): array
{
    return ['profession' => ['id' => $professionId, 'name' => 'Couture'], 'tiers' => $tiers];
}

/**
 * @param  list<int>  $recipeIds
 * @return array<string, mixed>
 */
function professionTierEntry(?int $tierId, int $skillPoints, int $maxSkillPoints, array $recipeIds = []): array
{
    return array_filter([
        'tier' => $tierId === null ? null : ['id' => $tierId, 'name' => 'Couture de Khaz Algar'],
        'skill_points' => $skillPoints,
        'max_skill_points' => $maxSkillPoints,
        'known_recipes' => array_map(fn (int $id): array => ['id' => $id], $recipeIds),
    ], fn (int|array|null $value): bool => $value !== null);
}

/**
 * @param  list<array<string, mixed>>  $factions
 * @param  list<array<string, mixed>>  $professions
 * @param  list<int>  $questIds
 * @param  list<int>  $achievementIds
 */
function crossProfileWith(array $factions = [], array $professions = [], array $questIds = [], array $achievementIds = []): CharacterProfileDTO
{
    return new CharacterProfileDTO(
        name: 'Thrall', realm: 'hyjal', race: 'Orc', class: 'Chaman', classId: 7, level: 80, ilvl: 600,
        faction: 'HORDE', avatarUrl: '', classIconUrl: '',
        collections: [10 => [
            'quests' => ['total' => 0, 'completed' => 0, 'zones' => []],
            'achievements' => ['total' => 0, 'completed' => 0, 'categories' => []],
            'reputations' => ['total' => count($factions), 'completed' => 0, 'factions' => $factions],
        ]],
        mountsCount: 0, petsCount: 0,
        professions: $professions,
        completedQuestIds: $questIds,
        completedAchievementIds: $achievementIds,
    );
}

/**
 * @return array<string, mixed>
 */
function profileFaction(int $id, int $raw, bool $started = true, int $renownLevel = 0): array
{
    return [
        'id' => $id, 'name' => 'Faction', 'standing_name' => 'Honoré', 'tier' => 5, 'value' => 0, 'max' => 1,
        'raw' => $raw, 'renown_level' => $renownLevel, 'completed' => false, 'started' => $started, 'account_wide' => false,
    ];
}

/**
 * @param  list<array{id: int, is_completed: bool}>  $recipes
 * @return array<string, mixed>
 */
function profileProfession(int $professionId, int $expansionId, int $skillPoints, int $maxSkillPoints, array $recipes = []): array
{
    return [
        'profession_id' => $professionId, 'profession_name' => 'Couture', 'type' => 'primary', 'is_archaeology' => false,
        'global_skill_points' => 0, 'global_max_skill_points' => 0,
        'expansions' => [$expansionId => [
            'total' => count($recipes), 'completed' => 0, 'has_tier' => true, 'tier_exists' => true,
            'skill_points' => $skillPoints, 'max_skill_points' => $maxSkillPoints,
            'categories' => [[
                'name' => 'Tissus', 'total' => count($recipes), 'completed' => 0,
                'items' => array_map(fn (array $recipe): array => [...$recipe, 'name' => 'Recette', 'wowhead_spell_id' => null], $recipes),
            ]],
        ]],
    ];
}

test('a quest or an achievement belongs to the first character that completed it', function (): void {
    $progress = new CrossCharacterProgress;
    $progress->mergeCharacter('Thrall', fetchedProgress(questIds: [1, 2], achievementIds: [10]));
    $progress->mergeCharacter('Jaina', fetchedProgress(questIds: [2, 3], achievementIds: [10, 11]));

    $result = $progress->buildResult();

    expect($result['completedQuestIds'])->toBe([1, 2, 3])
        ->and($result['questOwners'])->toBe([1 => 'Thrall', 2 => 'Thrall', 3 => 'Jaina'])
        ->and($result['completedAchievementIds'])->toBe([10, 11])
        ->and($result['achievementOwners'])->toBe([10 => 'Thrall', 11 => 'Jaina']);
});

test('a reputation keeps the tier, raw value and standing name of the best character', function (): void {
    $progress = new CrossCharacterProgress;
    $progress->mergeCharacter('Thrall', fetchedProgress(reputations: [
        reputationEntry(72, ['raw' => 30000, 'tier' => 5, 'max' => 12000, 'name' => 'Révéré']),
    ]));

    expect($progress->buildResult()['bestFactionStandings'][72])->toBe([
        'character_name' => 'Thrall',
        'tier' => 5,
        'raw' => 30000,
        'renown_level' => 0,
        'standing_name' => 'Révéré',
        'completed' => false,
    ]);
});

test('a reputation without faction id is ignored', function (): void {
    $progress = new CrossCharacterProgress;
    $progress->mergeCharacter('Thrall', fetchedProgress(reputations: [
        reputationEntry(null, ['raw' => 42000, 'tier' => 7]),
    ]));

    expect($progress->bestFactionStandings)->toBe([]);
});

test('a reputation missing its standing values counts from zero, unnamed and incomplete', function (): void {
    $progress = new CrossCharacterProgress;
    $progress->mergeCharacter('Thrall', fetchedProgress(reputations: [reputationEntry(72, [])]));

    expect($progress->bestFactionStandings[72])->toBe([
        'character_name' => 'Thrall',
        'tier' => 0,
        'raw' => 0,
        'renown_level' => 0,
        'standing_name' => '',
        'completed' => false,
    ]);
});

test('a reputation is completed at exalted, and only from exalted', function (int $tier, bool $completed): void {
    $progress = new CrossCharacterProgress;
    $progress->mergeCharacter('Thrall', fetchedProgress(reputations: [
        reputationEntry(72, ['raw' => 42000, 'tier' => $tier, 'max' => 1000]),
    ]));

    expect($progress->bestFactionStandings[72]['completed'])->toBe($completed);
})->with([
    'honored' => [6, false],
    'exalted' => [7, true],
    'paragon beyond exalted' => [8, true],
]);

test('a renown is completed only at its cap, which Blizzard marks with a zero max', function (int $renownLevel, ?int $max, bool $completed): void {
    $standing = array_filter(['raw' => 2500, 'tier' => 0, 'renown_level' => $renownLevel, 'max' => $max], fn (?int $value): bool => $value !== null);

    $progress = new CrossCharacterProgress;
    $progress->mergeCharacter('Thrall', fetchedProgress(reputations: [reputationEntry(2590, $standing)]));

    expect($progress->bestFactionStandings[2590]['completed'])->toBe($completed);
})->with([
    'capped renown' => [25, 0, true],
    'renown still progressing' => [12, 2500, false],
    'renown with unknown max' => [25, null, false],
    'first renown level at cap' => [1, 0, true],
    'no renown and zero max' => [0, 0, false],
]);

test('a higher renown wins even when the current best only has renown one', function (): void {
    $progress = new CrossCharacterProgress;
    $progress->mergeCharacter('Thrall', fetchedProgress(reputations: [reputationEntry(2590, ['raw' => 900, 'renown_level' => 1])]));
    $progress->mergeCharacter('Jaina', fetchedProgress(reputations: [reputationEntry(2590, ['raw' => 100, 'renown_level' => 2])]));
    $progress->mergeCharacter('Varian', fetchedProgress(reputations: [reputationEntry(2590, ['raw' => 99999, 'renown_level' => 0])]));

    expect($progress->bestFactionStandings[2590]['character_name'])->toBe('Jaina');
});

test('equal raw reputation keeps the first character', function (): void {
    $progress = new CrossCharacterProgress;
    $progress->mergeCharacter('Thrall', fetchedProgress(reputations: [reputationEntry(72, ['raw' => 30000, 'tier' => 5])]));
    $progress->mergeCharacter('Jaina', fetchedProgress(reputations: [reputationEntry(72, ['raw' => 30000, 'tier' => 5])]));

    expect($progress->bestFactionStandings[72]['character_name'])->toBe('Thrall');
});

test('the character with the most skill points in a profession tier owns it, with the highest cap seen', function (): void {
    $progress = new CrossCharacterProgress;
    $progress->mergeCharacter('Thrall', fetchedProgress(primaries: [professionEntry(197, [professionTierEntry(2883, 50, 100)])]));
    $progress->mergeCharacter('Jaina', fetchedProgress(primaries: [professionEntry(197, [professionTierEntry(2883, 80, 90)])]));
    $progress->mergeCharacter('Varian', fetchedProgress(primaries: [professionEntry(197, [professionTierEntry(2883, 60, 175)])]));

    expect($progress->buildResult()['skillPointOwners'])->toBe([197 => [2883 => [
        'character_name' => 'Jaina',
        'skill_points' => 80,
        'max_skill_points' => 100,
    ]]]);
});

test('a profession tier without skill points has no owner', function (): void {
    $progress = new CrossCharacterProgress;
    $progress->mergeCharacter('Thrall', fetchedProgress(primaries: [professionEntry(197, [professionTierEntry(2883, 0, 100)])]));

    expect($progress->skillPointOwners)->toBe([]);
});

test('equal skill points keep the first owner', function (): void {
    $progress = new CrossCharacterProgress;
    $progress->mergeCharacter('Thrall', fetchedProgress(primaries: [professionEntry(197, [professionTierEntry(2883, 50, 100)])]));
    $progress->mergeCharacter('Jaina', fetchedProgress(primaries: [professionEntry(197, [professionTierEntry(2883, 50, 100)])]));

    expect($progress->skillPointOwners[197][2883]['character_name'])->toBe('Thrall');
});

test('a profession tier without tier id is filed under expansion zero', function (): void {
    $progress = new CrossCharacterProgress;
    $progress->mergeCharacter('Thrall', fetchedProgress(primaries: [professionEntry(197, [professionTierEntry(null, 30, 75)])]));

    expect(array_keys($progress->skillPointOwners[197]))->toBe([0]);
});

test('every tier of every profession is merged', function (): void {
    $progress = new CrossCharacterProgress;
    $progress->mergeCharacter('Thrall', fetchedProgress(primaries: [
        professionEntry(197, [professionTierEntry(2883, 50, 100), professionTierEntry(2822, 20, 100)]),
        professionEntry(164, [professionTierEntry(2872, 10, 100)]),
    ]));

    expect(array_keys($progress->skillPointOwners))->toBe([197, 164])
        ->and(array_keys($progress->skillPointOwners[197]))->toBe([2883, 2822]);
});

test('a known recipe is completed and owned by the first character that knows it', function (): void {
    $progress = new CrossCharacterProgress;
    $progress->mergeCharacter('Thrall', fetchedProgress(primaries: [professionEntry(197, [professionTierEntry(2883, 50, 100, [501, 502])])]));
    $progress->mergeCharacter('Jaina', fetchedProgress(primaries: [professionEntry(197, [professionTierEntry(2883, 60, 100, [502, 503])])]));

    $result = $progress->buildResult();

    expect($result['completedRecipeIds'])->toBe([501, 502, 503])
        ->and($result['recipeOwners'])->toBe([501 => 'Thrall', 502 => 'Thrall', 503 => 'Jaina']);
});

test('a profession or a recipe with a zero id is ignored', function (): void {
    $progress = new CrossCharacterProgress;
    $progress->mergeCharacter('Thrall', fetchedProgress(primaries: [
        professionEntry(0, [professionTierEntry(2883, 50, 100, [700])]),
        professionEntry(197, [professionTierEntry(2883, 50, 100, [0, 501])]),
    ]));

    expect(array_keys($progress->completedRecipeIds))->toBe([501])
        ->and(array_keys($progress->skillPointOwners))->toBe([197]);
});

test('a profile merge keeps the first owner of quests and achievements', function (): void {
    $progress = new CrossCharacterProgress;
    $progress->mergeCharacter('Jaina', fetchedProgress(questIds: [1], achievementIds: [10]));
    $progress->mergeFromProfile('Thrall', crossProfileWith(questIds: [1, 2], achievementIds: [10, 11]));

    expect($progress->completedQuestIds)->toBe([1 => 'Jaina', 2 => 'Thrall'])
        ->and($progress->completedAchievementIds)->toBe([10 => 'Jaina', 11 => 'Thrall']);
});

test('a profile merge keeps the best standing of every started faction', function (): void {
    $progress = new CrossCharacterProgress;
    $progress->mergeCharacter('Jaina', fetchedProgress(reputations: [reputationEntry(72, ['raw' => 20000, 'tier' => 4])]));
    $progress->mergeFromProfile('Thrall', crossProfileWith(factions: [profileFaction(72, 30000), profileFaction(1134, 500)]));

    expect($progress->bestFactionStandings[72])->toBe([
        'character_name' => 'Thrall',
        'tier' => 5,
        'raw' => 30000,
        'renown_level' => 0,
        'standing_name' => 'Honoré',
        'completed' => false,
    ])->and($progress->bestFactionStandings[1134]['character_name'])->toBe('Thrall');
});

test('a profile merge ignores unstarted factions and factions without id', function (): void {
    $progress = new CrossCharacterProgress;
    $progress->mergeFromProfile('Thrall', crossProfileWith(factions: [profileFaction(72, 0, started: false), profileFaction(0, 30000)]));

    expect($progress->bestFactionStandings)->toBe([]);
});

test('a profile merge takes skill points and completed recipes, not unknown ones', function (): void {
    $progress = new CrossCharacterProgress;
    $progress->mergeCharacter('Jaina', fetchedProgress(primaries: [professionEntry(197, [professionTierEntry(2883, 40, 100, [501])])]));
    $progress->mergeFromProfile('Thrall', crossProfileWith(professions: [
        profileProfession(197, 2883, 90, 100, [['id' => 501, 'is_completed' => true], ['id' => 502, 'is_completed' => true], ['id' => 503, 'is_completed' => false]]),
        profileProfession(0, 2883, 90, 100, [['id' => 700, 'is_completed' => true]]),
    ]));

    expect($progress->skillPointOwners)->toBe([197 => [2883 => ['character_name' => 'Thrall', 'skill_points' => 90, 'max_skill_points' => 100]]])
        ->and($progress->recipeOwners)->toBe([501 => 'Jaina', 502 => 'Thrall'])
        ->and(array_keys($progress->completedRecipeIds))->toBe([501, 502]);
});

test('stored progress is read back with its owners', function (): void {
    $crossCharacterProgress = CrossCharacterProgress::fromStored(ResponsePayload::forEndpoint('cross_character_data', [
        'questOwners' => ['1' => 'Thrall'],
        'achievementOwners' => ['10' => 'Jaina'],
        'completedRecipeIds' => [501],
        'recipeOwners' => ['501' => 'Thrall'],
        'bestFactionStandings' => ['72' => [
            'character_name' => 'Thrall', 'tier' => 7, 'raw' => 42000, 'renown_level' => 0, 'standing_name' => 'Exalté', 'completed' => true,
        ]],
        'skillPointOwners' => ['197' => ['2883' => ['character_name' => 'Jaina', 'skill_points' => 80, 'max_skill_points' => 100]]],
    ]));

    expect($crossCharacterProgress->buildResult())->toBe([
        'completedQuestIds' => [1],
        'completedAchievementIds' => [10],
        'completedRecipeIds' => [501],
        'questOwners' => [1 => 'Thrall'],
        'achievementOwners' => [10 => 'Jaina'],
        'bestFactionStandings' => [72 => [
            'character_name' => 'Thrall', 'tier' => 7, 'raw' => 42000, 'renown_level' => 0, 'standing_name' => 'Exalté', 'completed' => true,
        ]],
        'recipeOwners' => [501 => 'Thrall'],
        'skillPointOwners' => [197 => [2883 => ['character_name' => 'Jaina', 'skill_points' => 80, 'max_skill_points' => 100]]],
    ]);
});

test('stored progress of the old format, without owners, reads back with anonymous owners', function (): void {
    $crossCharacterProgress = CrossCharacterProgress::fromStored(ResponsePayload::forEndpoint('cross_character_data', [
        'completedQuestIds' => [1, 2],
        'completedAchievementIds' => [10],
    ]));

    expect($crossCharacterProgress->completedQuestIds)->toBe([1 => '', 2 => ''])
        ->and($crossCharacterProgress->completedAchievementIds)->toBe([10 => '']);
});

test('a stored standing without completion flag reads back as incomplete', function (): void {
    $crossCharacterProgress = CrossCharacterProgress::fromStored(ResponsePayload::forEndpoint('cross_character_data', [
        'bestFactionStandings' => ['72' => [
            'character_name' => 'Thrall', 'tier' => 5, 'raw' => 30000, 'renown_level' => 0, 'standing_name' => 'Honoré',
        ]],
    ]));

    expect($crossCharacterProgress->bestFactionStandings[72]['completed'])->toBeFalse();
});

test('a first renown level beats a traditional standing, however high its raw value', function (): void {
    $progress = new CrossCharacterProgress;
    $progress->mergeCharacter('Varian', fetchedProgress(reputations: [reputationEntry(2590, ['raw' => 99999, 'renown_level' => 0])]));
    $progress->mergeCharacter('Jaina', fetchedProgress(reputations: [reputationEntry(2590, ['raw' => 100, 'renown_level' => 1])]));

    expect($progress->bestFactionStandings[2590]['character_name'])->toBe('Jaina');
});

test('a traditional standing never replaces a first renown level, however high its raw value', function (): void {
    $progress = new CrossCharacterProgress;
    $progress->mergeCharacter('Jaina', fetchedProgress(reputations: [reputationEntry(2590, ['raw' => 100, 'renown_level' => 1])]));
    $progress->mergeCharacter('Varian', fetchedProgress(reputations: [reputationEntry(2590, ['raw' => 99999, 'renown_level' => 0])]));

    expect($progress->bestFactionStandings[2590]['character_name'])->toBe('Jaina');
});

test('an ignored reputation does not stop the merge of the following ones', function (): void {
    $progress = new CrossCharacterProgress;
    $progress->mergeCharacter('Thrall', fetchedProgress(reputations: [
        reputationEntry(null, ['raw' => 500]),
        reputationEntry(72, ['raw' => 30000, 'tier' => 5]),
    ]));
    $progress->mergeFromProfile('Thrall', crossProfileWith(factions: [
        profileFaction(1134, 0, started: false),
        profileFaction(0, 500),
        profileFaction(1135, 800),
    ]));

    expect(array_keys($progress->bestFactionStandings))->toBe([72, 1135]);
});

test('an ignored profession of a profile does not stop the merge of the following ones', function (): void {
    $progress = new CrossCharacterProgress;
    $progress->mergeFromProfile('Thrall', crossProfileWith(professions: [
        profileProfession(0, 2883, 90, 100),
        profileProfession(197, 2883, 90, 100),
    ]));

    expect(array_keys($progress->skillPointOwners))->toBe([197]);
});

test('a single skill point is enough to own a profession tier', function (): void {
    $progress = new CrossCharacterProgress;
    $progress->mergeCharacter('Thrall', fetchedProgress(primaries: [professionEntry(197, [professionTierEntry(2883, 1, 100)])]));

    expect($progress->skillPointOwners[197][2883]['skill_points'])->toBe(1);
});

test('a profession tier without skill values has no owner, and an unknown cap counts as zero', function (): void {
    $progress = new CrossCharacterProgress;
    $progress->mergeCharacter('Thrall', fetchedProgress(primaries: [professionEntry(197, [
        ['tier' => ['id' => 2883, 'name' => 'Couture de Khaz Algar']],
        ['tier' => ['id' => 2822, 'name' => 'Couture des Îles'], 'skill_points' => 5],
    ])]));

    expect($progress->skillPointOwners)->toBe([197 => [2822 => [
        'character_name' => 'Thrall',
        'skill_points' => 5,
        'max_skill_points' => 0,
    ]]]);
});
