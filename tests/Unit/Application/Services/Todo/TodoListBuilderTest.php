<?php

declare(strict_types=1);

use App\Application\Services\Todo\TodoDimension;
use App\Application\Services\Todo\TodoListBuilder;
use App\Application\Services\Todo\TodoScope;

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
 * @param  array{mounts?: list<array{id: int, name: string, is_completed: bool, source: string|null, category: string|null, wowhead_id: int|null, icon_url: string|null}>, pets?: list<array{id: int, name: string, is_completed: bool, source: string|null, category: string|null, wowhead_id: int|null, icon_url: string|null}>, decor?: list<array{id: int, name: string, is_completed: bool, item_id: int|null, icon_url: string|null, category: string|null, source: string|null}>, collections?: array<int, array{quests: array{total: int, completed: int, zones: list<never>}, achievements: array{total: int, completed: int, categories: list<array{name: string, total: int, completed: int, items: list<array{id: int, name: string, icon_url: string|null, is_completed: bool}>}>}, reputations: array{completed: int, total: int}}>}  $overrides
 * @return array{collections: array<int, array{quests: array{total: int, completed: int, zones: list<never>}, achievements: array{total: int, completed: int, categories: list<array{name: string, total: int, completed: int, items: list<array{id: int, name: string, icon_url: string|null, is_completed: bool}>}>}, reputations: array{completed: int, total: int}}>, mounts: list<array{id: int, name: string, is_completed: bool, source: string|null, category: string|null, wowhead_id: int|null, icon_url: string|null}>, pets: list<array{id: int, name: string, is_completed: bool, source: string|null, category: string|null, wowhead_id: int|null, icon_url: string|null}>, decor: list<array{id: int, name: string, is_completed: bool, item_id: int|null, icon_url: string|null, category: string|null, source: string|null}>, professions: list<never>, mountsCount: int, petsCount: int, decorCount: int, bestProfessionStats: null, appearances: list<never>, raids: null, characterCount: int, errors: list<string>, cachedAt: null}
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
 * @return array{quests: array{total: int, completed: int, zones: list<never>}, achievements: array{total: int, completed: int, categories: list<array{name: string, total: int, completed: int, items: list<array{id: int, name: string, icon_url: string|null, is_completed: bool}>}>}, reputations: array{completed: int, total: int}}
 */
function todoExpansion(array $categories): array
{
    return [
        'quests' => ['total' => 0, 'completed' => 0, 'zones' => []],
        'achievements' => ['total' => 0, 'completed' => 0, 'categories' => $categories],
        'reputations' => ['completed' => 0, 'total' => 0],
    ];
}

/**
 * @param  array{mounts?: list<array{id: int, name: string, is_completed: bool, source: string|null, category: string|null, wowhead_id: int|null, icon_url: string|null}>, pets?: list<array{id: int, name: string, is_completed: bool, source: string|null, category: string|null, wowhead_id: int|null, icon_url: string|null}>, decor?: list<array{id: int, name: string, is_completed: bool, item_id: int|null, icon_url: string|null, category: string|null, source: string|null}>, collections?: array<int, array{quests: array{total: int, completed: int, zones: list<never>}, achievements: array{total: int, completed: int, categories: list<array{name: string, total: int, completed: int, items: list<array{id: int, name: string, icon_url: string|null, is_completed: bool}>}>}, reputations: array{completed: int, total: int}}>}  $overrides
 * @return list<array{key: string, name: string, dimension: string, completed: int, total: int, missing: int, percent: float, missingItems: list<array{id: int, name: string, wowheadUrl: string, doers: array{scope: string, characters: list<string>}}>, missingMore: int}>
 */
function buildTodo(array $overrides): array
{
    return (new TodoListBuilder)->build(todoAccountProfile($overrides));
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
        expect($category['missingItems'][0]['doers'])->toBe(['scope' => TodoScope::Account->value, 'characters' => []]);
    }
});
