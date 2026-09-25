<?php

declare(strict_types=1);

use App\Application\DTOs\AccountScoreProgress;
use App\Application\DTOs\CharacterProfileDTO;

/**
 * @param  list<int>  $questsDone
 * @param  list<int>  $recipesDone
 * @param  array<string, list<int>>  $raidKills
 */
function richAccountProfile(string $name, array $questsDone, array $recipesDone, int $exalted, int $skillPoints, array $raidKills, bool $hasMount): CharacterProfileDTO
{
    $quest = fn (int $id): array => ['id' => $id, 'name' => 'Quête '.$id, 'is_completed' => in_array($id, $questsDone, true)];
    $recipe = fn (int $id): array => ['id' => $id, 'name' => 'Recette '.$id, 'is_completed' => in_array($id, $recipesDone, true), 'wowhead_spell_id' => $id * 10];
    $emptyExpansion = ['total' => 0, 'completed' => 0, 'categories' => [], 'has_tier' => false, 'tier_exists' => false, 'skill_points' => 0, 'max_skill_points' => 0];

    return new CharacterProfileDTO(
        name: $name,
        realm: 'Hyjal',
        race: 'Orc',
        class: 'Chaman',
        classId: 7,
        level: 80,
        ilvl: 600,
        faction: 'Horde',
        avatarUrl: '',
        classIconUrl: '',
        collections: [
            0 => [
                'quests' => ['total' => 3, 'completed' => count($questsDone), 'zones' => [['name' => 'Elwynn', 'total' => 3, 'completed' => count($questsDone), 'items' => [$quest(1), $quest(2), $quest(3)]]]],
                'achievements' => ['total' => 1, 'completed' => 0, 'categories' => [['name' => 'Général', 'total' => 1, 'completed' => 0, 'items' => [['id' => 6, 'name' => 'HF', 'icon_url' => null, 'is_completed' => $name === 'Jaina']]]]],
                'reputations' => ['total' => 4, 'completed' => $exalted, 'factions' => []],
            ],
            10 => [
                'quests' => ['total' => 0, 'completed' => 0, 'zones' => []],
                'achievements' => ['total' => 0, 'completed' => 0, 'categories' => []],
                'reputations' => ['total' => 2, 'completed' => 1, 'factions' => []],
            ],
        ],
        mountsCount: $hasMount ? 1 : 0,
        petsCount: 0,
        mounts: [['id' => 200, 'name' => 'Loup', 'is_completed' => $hasMount, 'source' => null, 'category' => null, 'wowhead_id' => null, 'icon_url' => null]],
        pets: [['id' => 300, 'name' => 'Chat', 'is_completed' => true, 'source' => null, 'category' => null, 'wowhead_id' => null, 'icon_url' => null]],
        professions: [[
            'profession_id' => 171,
            'profession_name' => 'Alchimie',
            'type' => 'primary',
            'is_archaeology' => false,
            'global_skill_points' => $skillPoints,
            'global_max_skill_points' => 100,
            'expansions' => [
                0 => ['total' => 2, 'completed' => count($recipesDone), 'categories' => [['name' => 'Potions', 'total' => 2, 'completed' => count($recipesDone), 'items' => [$recipe(1), $recipe(2)]]], 'has_tier' => true, 'tier_exists' => true, 'skill_points' => $skillPoints, 'max_skill_points' => 100],
                1 => $emptyExpansion,
            ],
        ]],
        decor: [['id' => 500, 'name' => 'Foyer', 'is_completed' => false, 'item_id' => null, 'icon_url' => null, 'category' => null, 'source' => null]],
        appearances: [['slot' => 'HEAD', 'category' => 'Armure', 'total' => 3, 'completed' => count($questsDone)]],
        raids: [[
            'instance_id' => 1307,
            'instance_name' => 'La Flèche',
            'modes' => array_map(fn (string $difficulty): array => [
                'difficulty_type' => $difficulty,
                'difficulty_label' => $difficulty,
                'completed_count' => count($raidKills[$difficulty]),
                'total_count' => 6,
                'encounters' => array_map(fn (int $id): array => ['id' => $id, 'name' => 'Boss '.$id, 'last_kill_timestamp' => $id * 1000], $raidKills[$difficulty]),
            ], array_keys($raidKills)),
        ]],
    );
}

test('the account result is unchanged', function (): void {
    \Illuminate\Support\Facades\Date::setTestNow('2026-09-23T12:00:00Z');

    $progress = new AccountScoreProgress([['realmSlug' => 'hyjal', 'name' => 'Thrall'], ['realmSlug' => 'hyjal', 'name' => 'Jaina']]);
    $progress->mergeProfile(richAccountProfile('Thrall', [1], [2], 1, 40, ['NORMAL' => [3, 1], 'HEROIC' => [1]], true));
    $progress->mergeProfile(richAccountProfile('Jaina', [2, 3], [], 3, 75, ['HEROIC' => [2], 'MYTHIC' => [1]], false));
    $progress->errors[] = 'Arthas';

    expect(json_encode($progress->buildResult(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))->toMatchSnapshot();
});
