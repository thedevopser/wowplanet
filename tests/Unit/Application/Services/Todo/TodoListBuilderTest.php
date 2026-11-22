<?php

declare(strict_types=1);

use App\Application\Services\Todo\TodoDimension;
use App\Application\Services\Todo\TodoListBuilder;
use App\Application\Services\Todo\TodoScope;
use App\Http\Localization\AppLocale;

/**
 * @return array{id: int, name: string, is_completed: bool, source: string|null, category: string|null, wowhead_id: int|null, icon_url: string|null}
 */
function todoCollectible(int $id, bool $completed, ?string $source = 'Vendor', ?int $wowheadId = null): array
{
    return [
        'id' => $id,
        'name' => 'Collectible '.$id,
        'is_completed' => $completed,
        'source' => $source,
        'category' => null,
        'wowhead_id' => $wowheadId,
        'icon_url' => null,
    ];
}

/**
 * @return array{id: int, name: string, is_completed: bool, item_id: int|null, icon_url: string|null, category: string|null, source: string|null}
 */
function todoDecor(int $id, bool $completed, ?string $source = 'Vendor', ?int $itemId = null): array
{
    return [
        'id' => $id,
        'name' => 'Decor '.$id,
        'is_completed' => $completed,
        'item_id' => $itemId,
        'icon_url' => null,
        'category' => null,
        'source' => $source,
    ];
}

/**
 * @param  list<bool>  $completions
 * @return array{name: string, total: int, completed: int, items: list<array{id: int, name: string, icon_url: string|null, is_completed: bool}>}
 */
function todoAchievementCategory(string $name, array $completions, int $firstId = 1): array
{
    $items = [];

    foreach ($completions as $offset => $completed) {
        $items[] = ['id' => $firstId + $offset, 'name' => 'Achievement '.($firstId + $offset), 'icon_url' => null, 'is_completed' => $completed];
    }

    return ['name' => $name, 'total' => count($items), 'completed' => count(array_filter($completions)), 'items' => $items];
}

/**
 * The account profile with nothing in it, so that a test only spells out the part it is about.
 *
 * @param  array{mounts?: list<array{id: int, name: string, is_completed: bool, source: string|null, category: string|null, wowhead_id: int|null, icon_url: string|null}>, pets?: list<array{id: int, name: string, is_completed: bool, source: string|null, category: string|null, wowhead_id: int|null, icon_url: string|null}>, decor?: list<array{id: int, name: string, is_completed: bool, item_id: int|null, icon_url: string|null, category: string|null, source: string|null}>, professions?: list<array{profession_id: int, profession_name: string, type: string, is_archaeology: bool, global_skill_points: int, global_max_skill_points: int, expansions: array<int, array{total: int, completed: int, categories: list<array{name: string, total: int, completed: int, items: list<array{id: int, name: string, is_completed: bool, wowhead_spell_id: int|null}>}>, has_tier: bool, tier_exists: bool, skill_points: int, max_skill_points: int}>}>, collections?: array<int, array{quests: array{total: int, completed: int, zones: list<array{name: string, total: int, completed: int, items: list<array{id: int, name: string, is_completed: bool}>}>}, achievements: array{total: int, completed: int, categories: list<array{name: string, total: int, completed: int, items: list<array{id: int, name: string, icon_url: string|null, is_completed: bool}>}>}, reputations: array{completed: int, total: int}}>}  $overrides
 * @return array{collections: array<int, array{quests: array{total: int, completed: int, zones: list<array{name: string, total: int, completed: int, items: list<array{id: int, name: string, is_completed: bool}>}>}, achievements: array{total: int, completed: int, categories: list<array{name: string, total: int, completed: int, items: list<array{id: int, name: string, icon_url: string|null, is_completed: bool}>}>}, reputations: array{completed: int, total: int}}>, mounts: list<array{id: int, name: string, is_completed: bool, source: string|null, category: string|null, wowhead_id: int|null, icon_url: string|null}>, pets: list<array{id: int, name: string, is_completed: bool, source: string|null, category: string|null, wowhead_id: int|null, icon_url: string|null}>, decor: list<array{id: int, name: string, is_completed: bool, item_id: int|null, icon_url: string|null, category: string|null, source: string|null}>, professions: list<array{profession_id: int, profession_name: string, type: string, is_archaeology: bool, global_skill_points: int, global_max_skill_points: int, expansions: array<int, array{total: int, completed: int, categories: list<array{name: string, total: int, completed: int, items: list<array{id: int, name: string, is_completed: bool, wowhead_spell_id: int|null}>}>, has_tier: bool, tier_exists: bool, skill_points: int, max_skill_points: int}>}>, mountsCount: int, petsCount: int, decorCount: int, bestProfessionStats: null, appearances: list<never>, raids: null, characterCount: int, errors: list<string>, cachedAt: null}
 */
function todoAccountProfile(array $overrides = []): array
{
    return [
        'collections' => [],
        'mounts' => [],
        'pets' => [],
        'decor' => [],
        'professions' => [],
        'mountsCount' => 0,
        'petsCount' => 0,
        'decorCount' => 0,
        'bestProfessionStats' => null,
        'appearances' => [],
        'raids' => null,
        'characterCount' => 1,
        'errors' => [],
        'cachedAt' => null,
        ...$overrides,
    ];
}

/**
 * @param  list<array{name: string, total: int, completed: int, items: list<array{id: int, name: string, icon_url: string|null, is_completed: bool}>}>  $categories
 * @param  list<array{name: string, total: int, completed: int, items: list<array{id: int, name: string, is_completed: bool}>}>  $zones
 * @return array{quests: array{total: int, completed: int, zones: list<array{name: string, total: int, completed: int, items: list<array{id: int, name: string, is_completed: bool}>}>}, achievements: array{total: int, completed: int, categories: list<array{name: string, total: int, completed: int, items: list<array{id: int, name: string, icon_url: string|null, is_completed: bool}>}>}, reputations: array{completed: int, total: int}}
 */
function todoExpansion(array $categories = [], array $zones = []): array
{
    return [
        'quests' => ['total' => 0, 'completed' => 0, 'zones' => $zones],
        'achievements' => ['total' => 0, 'completed' => 0, 'categories' => $categories],
        'reputations' => ['completed' => 0, 'total' => 0],
    ];
}

/**
 * @param  array{mounts?: list<array{id: int, name: string, is_completed: bool, source: string|null, category: string|null, wowhead_id: int|null, icon_url: string|null}>, pets?: list<array{id: int, name: string, is_completed: bool, source: string|null, category: string|null, wowhead_id: int|null, icon_url: string|null}>, decor?: list<array{id: int, name: string, is_completed: bool, item_id: int|null, icon_url: string|null, category: string|null, source: string|null}>, professions?: list<array{profession_id: int, profession_name: string, type: string, is_archaeology: bool, global_skill_points: int, global_max_skill_points: int, expansions: array<int, array{total: int, completed: int, categories: list<array{name: string, total: int, completed: int, items: list<array{id: int, name: string, is_completed: bool, wowhead_spell_id: int|null}>}>, has_tier: bool, tier_exists: bool, skill_points: int, max_skill_points: int}>}>, collections?: array<int, array{quests: array{total: int, completed: int, zones: list<array{name: string, total: int, completed: int, items: list<array{id: int, name: string, is_completed: bool}>}>}, achievements: array{total: int, completed: int, categories: list<array{name: string, total: int, completed: int, items: list<array{id: int, name: string, icon_url: string|null, is_completed: bool}>}>}, reputations: array{completed: int, total: int}}>}  $overrides
 * @param  array{characters?: list<array{name: string, faction: string}>, questFactions?: array<int, string>, trackedFactions?: list<array{id: int, name: string, expansion_id: int}>, bestFactionStandings?: array<int, array{character_name: string, tier: int, raw: int, renown_level: int, standing_name: string, completed: bool}>, skillPointOwners?: array<int, array<int, array{character_name: string, skill_points: int, max_skill_points: int}>>}  $context
 * @return list<array{key: string, name: string, dimension: string, expansionId: int|null, completed: int, total: int, missing: int, percent: float, missingItems: list<array{id: int, name: string, wowheadUrl: string, detail: string|null, doers: array{scope: string, characters: list<string>}}>, missingMore: int}>
 */
function buildTodo(array $overrides, array $context = []): array
{
    return (new TodoListBuilder)->build(
        todoAccountProfile($overrides),
        [
            'completedQuestIds' => [],
            'completedAchievementIds' => [],
            'completedRecipeIds' => [],
            'questOwners' => [],
            'achievementOwners' => [],
            'bestFactionStandings' => $context['bestFactionStandings'] ?? [],
            'recipeOwners' => [],
            'skillPointOwners' => $context['skillPointOwners'] ?? [],
        ],
        $context['characters'] ?? [['name' => 'Apery', 'faction' => 'Horde']],
        $context['questFactions'] ?? [],
        $context['trackedFactions'] ?? [],
    );
}

/**
 * @param  list<bool>  $completions
 * @return array{name: string, total: int, completed: int, items: list<array{id: int, name: string, is_completed: bool}>}
 */
function todoQuestZone(string $name, array $completions, int $firstId = 1): array
{
    $items = [];

    foreach ($completions as $offset => $completed) {
        $items[] = ['id' => $firstId + $offset, 'name' => 'Quest '.($firstId + $offset), 'is_completed' => $completed];
    }

    return ['name' => $name, 'total' => count($items), 'completed' => count(array_filter($completions)), 'items' => $items];
}

/**
 * @param  list<bool>  $completions
 * @return array{profession_id: int, profession_name: string, type: string, is_archaeology: bool, global_skill_points: int, global_max_skill_points: int, expansions: array<int, array{total: int, completed: int, categories: list<array{name: string, total: int, completed: int, items: list<array{id: int, name: string, is_completed: bool, wowhead_spell_id: int|null}>}>, has_tier: bool, tier_exists: bool, skill_points: int, max_skill_points: int}>}
 */
function todoProfession(int $professionId, string $professionName, int $expansionId, string $categoryName, array $completions, ?int $spellId = null): array
{
    $items = [];

    foreach ($completions as $offset => $completed) {
        $items[] = ['id' => 500 + $offset, 'name' => 'Recipe '.(500 + $offset), 'is_completed' => $completed, 'wowhead_spell_id' => $spellId];
    }

    $category = ['name' => $categoryName, 'total' => count($items), 'completed' => count(array_filter($completions)), 'items' => $items];

    return [
        'profession_id' => $professionId,
        'profession_name' => $professionName,
        'type' => 'primary',
        'is_archaeology' => false,
        'global_skill_points' => 0,
        'global_max_skill_points' => 0,
        'expansions' => [$expansionId => [
            'total' => $category['total'],
            'completed' => $category['completed'],
            'categories' => [$category],
            'has_tier' => true,
            'tier_exists' => true,
            'skill_points' => 0,
            'max_skill_points' => 0,
        ]],
    ];
}

/**
 * @return array{character_name: string, tier: int, raw: int, renown_level: int, standing_name: string, completed: bool}
 */
function todoStanding(string $characterName, bool $completed, string $standingName = 'Honoré'): array
{
    return ['character_name' => $characterName, 'tier' => 5, 'raw' => 0, 'renown_level' => 0, 'standing_name' => $standingName, 'completed' => $completed];
}

it('lists each source of a collection that is started and not complete', function (): void {
    $todo = buildTodo([
        'mounts' => [todoCollectible(1, true, 'Vendor'), todoCollectible(2, false, 'Vendor')],
    ]);

    expect($todo)->toHaveCount(1)
        ->and($todo[0]['key'])->toBe('mounts:Vendor')
        ->and($todo[0]['name'])->toBe('Vendor')
        ->and($todo[0]['dimension'])->toBe(TodoDimension::Mounts->value);
});

it('covers mounts, pets and decorations', function (): void {
    $todo = buildTodo([
        'mounts' => [todoCollectible(1, true, 'Raid'), todoCollectible(2, false, 'Raid')],
        'pets' => [todoCollectible(3, true, 'Pet Battle'), todoCollectible(4, false, 'Pet Battle')],
        'decor' => [todoDecor(5, true, 'Quest'), todoDecor(6, false, 'Quest')],
    ]);

    expect(array_column($todo, 'dimension'))->toEqualCanonicalizing([
        TodoDimension::Mounts->value, TodoDimension::Pets->value, TodoDimension::Decor->value,
    ]);
});

it('leaves out a category with nothing earned yet', function (): void {
    $todo = buildTodo([
        'mounts' => [todoCollectible(1, false), todoCollectible(2, false)],
    ]);

    expect($todo)->toBe([]);
});

it('leaves out a category with nothing left to earn', function (): void {
    $todo = buildTodo([
        'mounts' => [todoCollectible(1, true), todoCollectible(2, true)],
    ]);

    expect($todo)->toBe([]);
});

it('leaves out the collectibles whose source is unknown', function (): void {
    $todo = buildTodo([
        'mounts' => [todoCollectible(1, true, null), todoCollectible(2, false, null)],
    ]);

    expect($todo)->toBe([]);
});

it('lists the achievement categories of every expansion', function (): void {
    $todo = buildTodo([
        'collections' => [
            10 => todoExpansion([todoAchievementCategory('Exploration', [true, false])]),
            11 => todoExpansion([todoAchievementCategory('Donjons', [true, false], 100)]),
        ],
    ]);

    expect(array_column($todo, 'key'))->toEqualCanonicalizing(['achievements:10:Exploration', 'achievements:11:Donjons'])
        ->and(array_unique(array_column($todo, 'dimension')))->toBe([TodoDimension::Achievements->value]);
});

it('ignores an achievement category that holds no achievement', function (): void {
    $todo = buildTodo([
        'collections' => [10 => todoExpansion([todoAchievementCategory('Vide', []), todoAchievementCategory('Exploration', [true, false])])],
    ]);

    expect(array_column($todo, 'key'))->toBe(['achievements:10:Exploration']);
});

it('carries the progress of each category', function (): void {
    $todo = buildTodo([
        'mounts' => [todoCollectible(1, true), todoCollectible(2, true), todoCollectible(3, true), todoCollectible(4, false)],
    ]);

    expect($todo[0])->toMatchArray(['completed' => 3, 'total' => 4, 'missing' => 1, 'percent' => 75.0]);
});

/**
 * A category of mounts from one source, with the given number of mounts earned and left.
 *
 * @return list<array{id: int, name: string, is_completed: bool, source: string|null, category: string|null, wowhead_id: int|null, icon_url: string|null}>
 */
function todoMountSource(string $source, int $earned, int $left, int $firstId): array
{
    $mounts = [];

    foreach (range(0, $earned + $left - 1) as $offset) {
        $mounts[] = todoCollectible($firstId + $offset, $offset < $earned, $source);
    }

    return $mounts;
}

it('puts first the categories with the fewest items left, even when another one is further along', function (): void {
    $todo = buildTodo([
        'mounts' => [
            ...todoMountSource('Raid', 9, 2, 100),
            ...todoMountSource('Vendor', 1, 1, 200),
            ...todoMountSource('Quest', 1, 3, 300),
        ],
    ]);

    expect(array_column($todo, 'key'))->toBe(['mounts:Vendor', 'mounts:Raid', 'mounts:Quest']);
});

it('puts first the most advanced category when as many items are left', function (): void {
    $todo = buildTodo([
        'mounts' => [
            ...todoMountSource('Raid', 1, 1, 100),
            ...todoMountSource('Vendor', 2, 1, 200),
            ...todoMountSource('Quest', 3, 1, 300),
        ],
    ]);

    expect(array_column($todo, 'key'))->toBe(['mounts:Quest', 'mounts:Vendor', 'mounts:Raid']);
});

it('names a source that reads like a number as it is written', function (): void {
    $todo = buildTodo(['mounts' => todoMountSource('10', 1, 1, 100)]);

    expect($todo[0]['name'])->toBe('10')
        ->and($todo[0]['key'])->toBe('mounts:10');
});

it('returns every category, without the cap of the former block', function (): void {
    $mounts = [];

    foreach (range(1, 15) as $source) {
        $mounts[] = todoCollectible($source * 2, true, 'Source '.$source);
        $mounts[] = todoCollectible($source * 2 + 1, false, 'Source '.$source);
    }

    expect(buildTodo(['mounts' => $mounts]))->toHaveCount(15);
});

it('shows at most twenty missing items of a category and counts the others', function (): void {
    $mounts = [todoCollectible(1, true)];

    foreach (range(2, 26) as $id) {
        $mounts[] = todoCollectible($id, false);
    }

    $todo = buildTodo(['mounts' => $mounts]);

    expect($todo[0]['missingItems'])->toHaveCount(TodoListBuilder::MAX_ITEMS_SHOWN)
        ->and($todo[0]['missingItems'][0]['id'])->toBe(2)
        ->and($todo[0]['missingMore'])->toBe(5);
});

it('counts no other missing item when they all fit', function (): void {
    $todo = buildTodo([
        'mounts' => [todoCollectible(1, true), todoCollectible(2, false)],
    ]);

    expect($todo[0]['missingMore'])->toBe(0)
        ->and($todo[0]['missingItems'][0])->toMatchArray(['id' => 2, 'name' => 'Collectible 2']);
});

it('links each missing item to its Wowhead page', function (array $profile, string $url): void {
    expect(buildTodo($profile)[0]['missingItems'][0]['wowheadUrl'])->toBe($url);
})->with([
    'a mount, by its spell' => [['mounts' => [todoCollectible(1, true), todoCollectible(2, false, 'Vendor', 5000)]], 'https://www.wowhead.com/fr/spell=5000'],
    'a pet, by its creature' => [['pets' => [todoCollectible(1, true), todoCollectible(2, false, 'Vendor', 6000)]], 'https://www.wowhead.com/fr/npc=6000'],
    'a decoration, by its item' => [['decor' => [todoDecor(1, true), todoDecor(2, false, 'Vendor', 7000)]], 'https://www.wowhead.com/fr/item=7000'],
    'an achievement, by its id' => [['collections' => [10 => todoExpansion([todoAchievementCategory('Exploration', [true, false], 40)])]], 'https://www.wowhead.com/fr/achievement=41'],
    'a mount without a known spell, by a search on its name' => [['mounts' => [todoCollectible(1, true), todoCollectible(2, false)]], 'https://www.wowhead.com/fr/search?q=Collectible%202'],
    'a decoration without a known item, by a search on its name' => [['decor' => [todoDecor(1, true), todoDecor(2, false)]], 'https://www.wowhead.com/fr/search?q=Decor%202'],
]);

it('lets the whole account earn a collectible or an achievement', function (): void {
    $todo = buildTodo([
        'mounts' => [todoCollectible(1, true), todoCollectible(2, false)],
        'collections' => [10 => todoExpansion([todoAchievementCategory('Exploration', [true, false])])],
    ]);

    foreach ($todo as $category) {
        expect($category['missingItems'][0]['doers'])->toBe(['scope' => TodoScope::Account->value, 'characters' => []])
            ->and($category['missingItems'][0]['detail'])->toBeNull();
    }
});

it('places a collection outside any expansion and an achievement category in its own', function (): void {
    $todo = buildTodo([
        'mounts' => [todoCollectible(1, true), todoCollectible(2, false)],
        'collections' => [10 => todoExpansion([todoAchievementCategory('Exploration', [true, false])])],
    ]);

    expect(array_column($todo, 'expansionId', 'key'))->toBe(['mounts:Vendor' => null, 'achievements:10:Exploration' => 10]);
});

it('lists the quest zones of every expansion that are started and not complete', function (): void {
    $todo = buildTodo(['collections' => [10 => todoExpansion(zones: [todoQuestZone('Comté-du-Nord', [true, false])])]]);

    expect($todo[0])->toMatchArray([
        'key' => 'quests:10:Comté-du-Nord',
        'name' => 'Comté-du-Nord',
        'dimension' => TodoDimension::Quests->value,
        'expansionId' => 10,
    ])->and($todo[0]['missingItems'][0]['wowheadUrl'])->toBe('https://www.wowhead.com/fr/quest=2');
});

it('lets every character do a missing neutral quest', function (): void {
    $todo = buildTodo(
        ['collections' => [10 => todoExpansion(zones: [todoQuestZone('Comté-du-Nord', [true, false])])]],
        ['characters' => [['name' => 'Apery', 'faction' => 'Horde'], ['name' => 'Jaina', 'faction' => 'Alliance']]],
    );

    expect($todo[0]['missingItems'][0]['doers'])->toBe(['scope' => TodoScope::Characters->value, 'characters' => ['Apery', 'Jaina']]);
});

it('lets only the characters of its faction do a faction quest', function (): void {
    $todo = buildTodo(
        ['collections' => [10 => todoExpansion(zones: [todoQuestZone('Comté-du-Nord', [true, false])])]],
        [
            'characters' => [['name' => 'Apery', 'faction' => 'Horde'], ['name' => 'Jaina', 'faction' => 'Alliance']],
            'questFactions' => [2 => 'Alliance'],
        ],
    );

    expect($todo[0]['missingItems'][0]['doers']['characters'])->toBe(['Jaina']);
});

it('leaves out a quest that no character of the account can do', function (): void {
    $todo = buildTodo(
        ['collections' => [10 => todoExpansion(zones: [todoQuestZone('Comté-du-Nord', [true, false, false])])]],
        ['characters' => [['name' => 'Apery', 'faction' => 'Horde']], 'questFactions' => [2 => 'Alliance']],
    );

    expect($todo[0]['missingItems'])->toHaveCount(1)
        ->and($todo[0]['missingItems'][0]['id'])->toBe(3)
        ->and($todo[0])->toMatchArray(['total' => 2, 'missing' => 1]);
});

it('lists the tracked reputations of an expansion that are started and not complete', function (): void {
    $todo = buildTodo([], [
        'trackedFactions' => [
            ['id' => 2590, 'name' => 'Conseil de Dornogal', 'expansion_id' => 10],
            ['id' => 2594, 'name' => "L'Assemblée des abysses", 'expansion_id' => 10],
        ],
        'bestFactionStandings' => [2590 => todoStanding('Apery', true), 2594 => todoStanding('Jaina', false, 'Renom 12')],
    ]);

    expect($todo[0])->toMatchArray([
        'key' => 'reputations:10',
        'name' => 'The War Within',
        'dimension' => TodoDimension::Reputations->value,
        'expansionId' => 10,
        'completed' => 1,
        'total' => 2,
    ])->and($todo[0]['missingItems'][0])->toMatchArray([
        'id' => 2594,
        'name' => "L'Assemblée des abysses",
        'wowheadUrl' => 'https://www.wowhead.com/fr/faction=2594',
        'detail' => 'Renom 12',
        'doers' => ['scope' => TodoScope::Characters->value, 'characters' => ['Jaina']],
    ]);
});

it('lets the whole account start a tracked reputation that no character has met', function (): void {
    $todo = buildTodo([], [
        'trackedFactions' => [
            ['id' => 2590, 'name' => 'Conseil de Dornogal', 'expansion_id' => 10],
            ['id' => 2594, 'name' => "L'Assemblée des abysses", 'expansion_id' => 10],
        ],
        'bestFactionStandings' => [2590 => todoStanding('Apery', true)],
    ]);

    expect($todo[0]['missingItems'][0])->toMatchArray([
        'id' => 2594,
        'detail' => null,
        'doers' => ['scope' => TodoScope::Account->value, 'characters' => []],
    ]);
});

it('lists the recipe categories of a profession and gives them to its best character for that expansion', function (): void {
    $todo = buildTodo(
        ['professions' => [todoProfession(171, 'Alchimie', 10, 'Potions', [true, false], 3000)]],
        ['skillPointOwners' => [171 => [10 => ['character_name' => 'Gothdroid', 'skill_points' => 95, 'max_skill_points' => 100]]]],
    );

    expect($todo[0])->toMatchArray([
        'key' => 'recipes:171:10:Potions',
        'name' => 'Alchimie · Potions',
        'dimension' => TodoDimension::Recipes->value,
        'expansionId' => 10,
    ])->and($todo[0]['missingItems'][0])->toMatchArray([
        'wowheadUrl' => 'https://www.wowhead.com/fr/spell=3000',
        'detail' => '95 / 100',
        'doers' => ['scope' => TodoScope::Characters->value, 'characters' => ['Gothdroid']],
    ]);
});

it('proposes no recipe of a profession that no character knows for that expansion', function (): void {
    $todo = buildTodo(
        ['professions' => [todoProfession(171, 'Alchimie', 10, 'Potions', [true, false])]],
        ['skillPointOwners' => [171 => [9 => ['character_name' => 'Gothdroid', 'skill_points' => 95, 'max_skill_points' => 100]]]],
    );

    expect($todo)->toBe([]);
});

it('gathers the tracked reputations of an expansion into a single category, apart from the other expansions', function (): void {
    $todo = buildTodo([], [
        'trackedFactions' => [
            ['id' => 2590, 'name' => 'Conseil de Dornogal', 'expansion_id' => 10],
            ['id' => 2594, 'name' => "L'Assemblée des abysses", 'expansion_id' => 10],
            ['id' => 2507, 'name' => 'Expédition dragonnante', 'expansion_id' => 9],
            ['id' => 2510, 'name' => 'Valdrakken', 'expansion_id' => 9],
        ],
        'bestFactionStandings' => [
            2590 => todoStanding('Apery', true),
            2594 => todoStanding('Apery', false),
            2507 => todoStanding('Apery', true),
            2510 => todoStanding('Apery', false),
        ],
    ]);

    expect(array_column($todo, 'key'))->toEqualCanonicalizing(['reputations:10', 'reputations:9'])
        ->and(array_column($todo, 'total'))->toBe([2, 2]);
});

it('lists the recipes of every profession and every expansion its best characters know', function (): void {
    $alchemy = todoProfession(171, 'Alchimie', 10, 'Potions', [true, false]);
    $alchemy['expansions'] += todoProfession(171, 'Alchimie', 9, 'Élixirs', [true, false])['expansions'];

    $todo = buildTodo(
        ['professions' => [$alchemy, todoProfession(164, 'Forge', 10, 'Armes', [true, false])]],
        ['skillPointOwners' => [
            171 => [10 => ['character_name' => 'Gothdroid', 'skill_points' => 95, 'max_skill_points' => 100], 9 => ['character_name' => 'Apery', 'skill_points' => 50, 'max_skill_points' => 100]],
            164 => [10 => ['character_name' => 'Apery', 'skill_points' => 10, 'max_skill_points' => 100]],
        ]],
    );

    expect(array_column($todo, 'key'))->toEqualCanonicalizing(['recipes:171:10:Potions', 'recipes:171:9:Élixirs', 'recipes:164:10:Armes']);
});

it('hands the page each missing item in the shape of its contract, and nothing more', function (): void {
    $todo = buildTodo(['mounts' => [todoCollectible(1, true), todoCollectible(2, false, 'Vendor', 5000)]]);

    expect($todo[0]['missingItems'])->toBe([[
        'id' => 2,
        'name' => 'Collectible 2',
        'wowheadUrl' => 'https://www.wowhead.com/fr/spell=5000',
        'detail' => null,
        'doers' => ['scope' => TodoScope::Account->value, 'characters' => []],
    ]]);
});

it('links each missing item to the Wowhead page in the language of the page', function (): void {
    $todo = new TodoListBuilder(AppLocale::English)->build(
        todoAccountProfile(['mounts' => [todoCollectible(1, true), todoCollectible(2, false, 'Vendor', 5000)]]),
        ['completedQuestIds' => [], 'completedAchievementIds' => [], 'completedRecipeIds' => [], 'questOwners' => [], 'achievementOwners' => [], 'bestFactionStandings' => [], 'recipeOwners' => [], 'skillPointOwners' => []],
        [['name' => 'Apery', 'faction' => 'Horde']],
        [],
        [],
    );

    expect($todo[0]['missingItems'][0]['wowheadUrl'])->toBe('https://www.wowhead.com/spell=5000');
});
