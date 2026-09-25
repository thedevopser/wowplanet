<?php

declare(strict_types=1);

use App\Application\DTOs\AccountScoreProgress;
use App\Application\DTOs\CharacterProfileDTO;

/**
 * @param  list<array{slot: string, category: string|null, total: int, completed: int}>  $appearances
 * @param  list<array<string, mixed>>|null  $raids
 */
function profileWith(string $name, array $appearances = [], ?array $raids = null): CharacterProfileDTO
{
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
        collections: [],
        mountsCount: 0,
        petsCount: 0,
        appearances: $appearances,
        raids: $raids,
    );
}

/**
 * @param  array<string, list<int>>  $killsByDifficulty
 * @return list<array<string, mixed>>
 */
function raidWith(int $instanceId, int $totalBosses, array $killsByDifficulty): array
{
    $modes = [];
    foreach ($killsByDifficulty as $difficulty => $bossIds) {
        $modes[] = [
            'difficulty_type' => $difficulty,
            'difficulty_label' => $difficulty,
            'completed_count' => count($bossIds),
            'total_count' => $totalBosses,
            'encounters' => array_map(fn (int $id): array => ['id' => $id, 'name' => 'Boss '.$id], $bossIds),
        ];
    }

    return [['instance_id' => $instanceId, 'instance_name' => 'Raid '.$instanceId, 'modes' => $modes]];
}

describe('apparences', function (): void {
    it("retient le meilleur nombre d'apparences par slot", function (): void {
        $progress = new AccountScoreProgress([]);
        $progress->mergeProfile(profileWith('Thrall', [
            ['slot' => 'HEAD', 'category' => 'armor', 'total' => 100, 'completed' => 40],
            ['slot' => 'CHEST', 'category' => 'armor', 'total' => 100, 'completed' => 10],
        ]));
        $progress->mergeProfile(profileWith('Jaina', [
            ['slot' => 'HEAD', 'category' => 'armor', 'total' => 100, 'completed' => 25],
            ['slot' => 'CHEST', 'category' => 'armor', 'total' => 100, 'completed' => 60],
        ]));

        expect($progress->buildResult()['appearances'])->toBe([
            ['slot' => 'HEAD', 'category' => 'armor', 'total' => 100, 'completed' => 40],
            ['slot' => 'CHEST', 'category' => 'armor', 'total' => 100, 'completed' => 60],
        ]);
    });

    it('ajoute un slot que seul un personnage connaît', function (): void {
        $progress = new AccountScoreProgress([]);
        $progress->mergeProfile(profileWith('Thrall', [
            ['slot' => 'HEAD', 'category' => 'armor', 'total' => 100, 'completed' => 40],
        ]));
        $progress->mergeProfile(profileWith('Jaina', [
            ['slot' => 'MAIN_HAND', 'category' => 'weapon', 'total' => 50, 'completed' => 5],
        ]));

        expect($progress->buildResult()['appearances'])->toHaveCount(2);
    });

    it('renvoie une liste vide sans personnage', function (): void {
        expect((new AccountScoreProgress([]))->buildResult()['appearances'])->toBe([]);
    });
});

describe('raids', function (): void {
    it('réunit les boss tués par les différents personnages', function (): void {
        $progress = new AccountScoreProgress([]);
        $progress->mergeProfile(profileWith('Thrall', [], raidWith(1, 8, ['MYTHIC' => [1, 2]])));
        $progress->mergeProfile(profileWith('Jaina', [], raidWith(1, 8, ['MYTHIC' => [2, 3]])));

        $raids = $progress->buildResult()['raids'];

        expect($raids)->toHaveCount(1)
            ->and($raids[0]['modes'])->toHaveCount(1)
            ->and(array_column($raids[0]['modes'][0]['encounters'], 'id'))->toBe([1, 2, 3])
            ->and($raids[0]['modes'][0]['completed_count'])->toBe(3)
            ->and($raids[0]['modes'][0]['total_count'])->toBe(8);
    });

    it('garde les paliers de difficulté séparés', function (): void {
        $progress = new AccountScoreProgress([]);
        $progress->mergeProfile(profileWith('Thrall', [], raidWith(1, 4, ['NORMAL' => [1, 2], 'HEROIC' => [1]])));
        $progress->mergeProfile(profileWith('Jaina', [], raidWith(1, 4, ['HEROIC' => [2]])));

        $modes = $progress->buildResult()['raids'][0]['modes'];

        expect(array_column($modes, 'difficulty_type'))->toBe(['NORMAL', 'HEROIC'])
            ->and(array_column($modes[1]['encounters'], 'id'))->toBe([1, 2]);
    });

    it('réunit des raids différents', function (): void {
        $progress = new AccountScoreProgress([]);
        $progress->mergeProfile(profileWith('Thrall', [], raidWith(1, 8, ['MYTHIC' => [1]])));
        $progress->mergeProfile(profileWith('Jaina', [], raidWith(2, 6, ['NORMAL' => [10]])));

        expect(array_column($progress->buildResult()['raids'], 'instance_id'))->toBe([1, 2]);
    });

    it("reste nul quand aucun personnage n'a de raid", function (): void {
        $progress = new AccountScoreProgress([]);
        $progress->mergeProfile(profileWith('Thrall'));

        expect($progress->buildResult()['raids'])->toBeNull();
    });
});

/**
 * @param  array<int, array<string, mixed>>  $collections
 * @param  list<array<string, mixed>>  $professions
 * @param  list<array<string, mixed>>  $mounts
 * @param  list<array<string, mixed>>  $pets
 * @param  list<array<string, mixed>>  $decor
 */
function accountProfile(array $collections = [], array $professions = [], array $mounts = [], array $pets = [], array $decor = []): CharacterProfileDTO
{
    return new CharacterProfileDTO(
        name: 'Thrall', realm: 'Hyjal', race: 'Orc', class: 'Chaman', classId: 7, level: 80, ilvl: 600,
        faction: 'Horde', avatarUrl: '', classIconUrl: '',
        collections: $collections, mountsCount: 0, petsCount: 0,
        mounts: $mounts, pets: $pets, professions: $professions, decor: $decor,
    );
}

/**
 * @return array<string, mixed>
 */
function accountExpansion(int $reputationsCompleted = 0, int $reputationsTotal = 4): array
{
    return [
        'quests' => ['total' => 0, 'completed' => 0, 'zones' => []],
        'achievements' => ['total' => 0, 'completed' => 0, 'categories' => []],
        'reputations' => ['total' => $reputationsTotal, 'completed' => $reputationsCompleted, 'factions' => []],
    ];
}

/**
 * @param  array<int, bool>  $recipes  recipe id => completed
 * @return array<string, mixed>
 */
function accountProfession(int $skillPoints, int $maxSkillPoints, array $recipes = [], int $expansionId = 0): array
{
    $items = [];
    foreach ($recipes as $id => $completed) {
        $items[] = ['id' => $id, 'name' => 'Recette '.$id, 'is_completed' => $completed, 'wowhead_spell_id' => null];
    }

    $completedCount = count(array_filter($recipes));

    return [
        'profession_id' => 171, 'profession_name' => 'Alchimie', 'type' => 'primary', 'is_archaeology' => false,
        'global_skill_points' => $skillPoints, 'global_max_skill_points' => $maxSkillPoints,
        'expansions' => [$expansionId => [
            'total' => count($recipes), 'completed' => $completedCount,
            'categories' => $items === [] ? [] : [['name' => 'Potions', 'total' => count($items), 'completed' => $completedCount, 'items' => $items]],
            'has_tier' => true, 'tier_exists' => true, 'skill_points' => $skillPoints, 'max_skill_points' => $maxSkillPoints,
        ]],
    ];
}

/**
 * @return array<string, mixed>
 */
function accountCollectible(int $id): array
{
    return ['id' => $id, 'name' => 'Objet '.$id, 'is_completed' => true, 'source' => null, 'category' => null, 'wowhead_id' => null, 'icon_url' => null];
}

describe('collections de compte', function (): void {
    it('prend les montures, mascottes et décorations du premier personnage, qui les partage avec le compte', function (): void {
        $progress = new AccountScoreProgress([]);
        $progress->mergeProfile(accountProfile(mounts: [accountCollectible(1)], pets: [accountCollectible(2)], decor: [accountCollectible(3)]));
        $progress->mergeProfile(accountProfile(mounts: [accountCollectible(10)], pets: [accountCollectible(20)], decor: [accountCollectible(30)]));

        $result = $progress->buildResult();

        expect(array_column($result['mounts'], 'id'))->toBe([1])
            ->and(array_column($result['pets'], 'id'))->toBe([2])
            ->and(array_column($result['decor'], 'id'))->toBe([3]);
    });

    it('compte chaque personnage fusionné', function (): void {
        $progress = new AccountScoreProgress([]);
        $progress->mergeProfile(accountProfile());
        $progress->mergeProfile(accountProfile());

        expect($progress->buildResult()['characterCount'])->toBe(2);
    });

    it("garde les extensions qu'un seul personnage connaît", function (): void {
        $progress = new AccountScoreProgress([]);
        $progress->mergeProfile(accountProfile(collections: [0 => accountExpansion()]));
        $progress->mergeProfile(accountProfile(collections: [10 => accountExpansion()]));

        expect(array_keys($progress->buildResult()['collections']))->toBe([0, 10]);
    });

    it('rend des collections vides sans personnage', function (): void {
        expect((new AccountScoreProgress([]))->buildResult()['collections'])->toBe([]);
    });
});

describe('réputations', function (): void {
    it("retient le personnage qui a complété le plus de réputations dans l'extension", function (): void {
        $progress = new AccountScoreProgress([]);
        $progress->mergeProfile(accountProfile(collections: [0 => accountExpansion(3, 4)]));
        $progress->mergeProfile(accountProfile(collections: [0 => accountExpansion(1, 5)]));

        expect($progress->buildResult()['collections'][0]['reputations'])->toBe(['completed' => 3, 'total' => 4]);
    });

    it('garde le premier personnage à égalité', function (): void {
        $progress = new AccountScoreProgress([]);
        $progress->mergeProfile(accountProfile(collections: [0 => accountExpansion(2, 4)]));
        $progress->mergeProfile(accountProfile(collections: [0 => accountExpansion(2, 5)]));

        expect($progress->buildResult()['collections'][0]['reputations'])->toBe(['completed' => 2, 'total' => 4]);
    });
});

describe('apparences, égalité', function (): void {
    it("garde le premier personnage quand deux ont autant d'apparences dans un slot", function (): void {
        $progress = new AccountScoreProgress([]);
        $progress->mergeProfile(profileWith('Thrall', [['slot' => 'HEAD', 'category' => 'Tissu', 'total' => 100, 'completed' => 40]]));
        $progress->mergeProfile(profileWith('Jaina', [['slot' => 'HEAD', 'category' => 'Plaques', 'total' => 100, 'completed' => 40]]));

        expect($progress->buildResult()['appearances'][0]['category'])->toBe('Tissu');
    });
});

describe('raids, cas limites', function (): void {
    it('garde un raid sans mode, et un mode sans boss tué', function (): void {
        $progress = new AccountScoreProgress([]);
        $progress->mergeProfile(profileWith('Thrall', [], [['instance_id' => 1, 'instance_name' => 'Raid 1', 'modes' => []]]));
        $progress->mergeProfile(profileWith('Jaina', [], raidWith(2, 8, ['NORMAL' => []])));

        expect($progress->buildResult()['raids'])->toBe([
            ['instance_id' => 1, 'instance_name' => 'Raid 1', 'modes' => []],
            ['instance_id' => 2, 'instance_name' => 'Raid 2', 'modes' => [[
                'difficulty_type' => 'NORMAL', 'difficulty_label' => 'NORMAL', 'completed_count' => 0, 'total_count' => 8, 'encounters' => [],
            ]]],
        ]);
    });

    it('retient le plus grand nombre de boss annoncé par les personnages', function (): void {
        $progress = new AccountScoreProgress([]);
        $progress->mergeProfile(profileWith('Thrall', [], raidWith(1, 8, ['MYTHIC' => [1]])));
        $progress->mergeProfile(profileWith('Jaina', [], raidWith(1, 9, ['MYTHIC' => [2]])));
        $progress->mergeProfile(profileWith('Varian', [], raidWith(1, 7, ['MYTHIC' => [3]])));

        expect($progress->buildResult()['raids'][0]['modes'][0]['total_count'])->toBe(9);
    });

    it('garde la victoire du premier personnage qui a tué le boss', function (): void {
        $mode = fn (int $timestamp): array => [['instance_id' => 1, 'instance_name' => 'Raid 1', 'modes' => [[
            'difficulty_type' => 'MYTHIC', 'difficulty_label' => 'Mythique', 'completed_count' => 1, 'total_count' => 8,
            'encounters' => [['id' => 5, 'name' => 'Boss 5', 'last_kill_timestamp' => $timestamp]],
        ]]]];

        $progress = new AccountScoreProgress([]);
        $progress->mergeProfile(profileWith('Thrall', [], $mode(1000)));
        $progress->mergeProfile(profileWith('Jaina', [], $mode(2000)));

        expect($progress->buildResult()['raids'][0]['modes'][0]['encounters'][0]['last_kill_timestamp'])->toBe(1000);
    });
});

describe('métiers', function (): void {
    it('réunit sur le compte les recettes connues de chaque personnage', function (): void {
        $progress = new AccountScoreProgress([]);
        $progress->mergeProfile(accountProfile(professions: [accountProfession(50, 100, [1 => true, 2 => false, 3 => false])]));
        $progress->mergeProfile(accountProfile(professions: [accountProfession(40, 100, [1 => false, 2 => true, 3 => false])]));

        $expansion = $progress->buildResult()['professions'][0]['expansions'][0];

        expect(array_column($expansion['categories'][0]['items'], 'is_completed'))->toBe([true, true, false])
            ->and($expansion['categories'][0]['completed'])->toBe(2)
            ->and($expansion['completed'])->toBe(2)
            ->and($expansion['total'])->toBe(3);
    });

    it('retient les meilleurs points, et le plus haut plafond vu, même porté par un autre personnage', function (): void {
        $progress = new AccountScoreProgress([]);
        $progress->mergeProfile(accountProfile(professions: [accountProfession(30, 175)]));
        $progress->mergeProfile(accountProfile(professions: [accountProfession(50, 100)]));
        $progress->mergeProfile(accountProfile(professions: [accountProfession(40, 200)]));

        $expansion = $progress->buildResult()['professions'][0]['expansions'][0];

        expect($expansion['skill_points'])->toBe(50)
            ->and($expansion['max_skill_points'])->toBe(200);
    });

    it('garde le meilleur ratio de recettes parmi les personnages', function (): void {
        $progress = new AccountScoreProgress([]);
        $progress->mergeProfile(accountProfile(professions: [accountProfession(0, 0, [1 => true, 2 => false])]));
        $progress->mergeProfile(accountProfile(professions: [accountProfession(0, 0, [1 => true, 2 => true, 3 => false])]));
        $progress->mergeProfile(accountProfile(professions: [accountProfession(0, 0, array_fill_keys(range(1, 10), false) + [11 => true, 12 => true, 13 => true])]));

        expect($progress->buildResult()['bestProfessionStats'])->toBe(['completed' => 2, 'total' => 3]);
    });

    it('garde le premier personnage à ratio égal', function (): void {
        $progress = new AccountScoreProgress([]);
        $progress->mergeProfile(accountProfile(professions: [accountProfession(0, 0, [1 => true, 2 => false])]));
        $progress->mergeProfile(accountProfile(professions: [accountProfession(0, 0, [1 => true, 2 => true, 3 => false, 4 => false])]));

        expect($progress->buildResult()['bestProfessionStats'])->toBe(['completed' => 1, 'total' => 2]);
    });

    it('ne se laisse pas dépasser par un ratio inférieur après un métier complet', function (): void {
        $progress = new AccountScoreProgress([]);
        $progress->mergeProfile(accountProfile(professions: [accountProfession(0, 0, [1 => true])]));
        $progress->mergeProfile(accountProfile(professions: [accountProfession(0, 0, [1 => true, 2 => true, 3 => false])]));

        expect($progress->buildResult()['bestProfessionStats'])->toBe(['completed' => 1, 'total' => 1]);
    });

    it('se replie sur les points de compétence quand aucune recette n\'est connue', function (): void {
        $progress = new AccountScoreProgress([]);
        $progress->mergeProfile(accountProfile(professions: [accountProfession(30, 100), accountProfession(20, 75, [], 1)]));

        expect($progress->buildResult()['bestProfessionStats'])->toBe(['completed' => 50, 'total' => 175]);
    });

    it('compte les recettes dès la première connue', function (): void {
        $progress = new AccountScoreProgress([]);
        $progress->mergeProfile(accountProfile(professions: [accountProfession(30, 100, [1 => true])]));

        expect($progress->buildResult()['bestProfessionStats'])->toBe(['completed' => 1, 'total' => 1]);
    });

    it("n'a pas de statistique de métier sans recette ni point", function (): void {
        $progress = new AccountScoreProgress([]);
        $progress->mergeProfile(accountProfile(professions: [accountProfession(0, 0)]));

        expect($progress->buildResult()['bestProfessionStats'])->toBeNull();
    });
});

it('garde le plafond du personnage dépassé en points quand il était plus haut', function (): void {
    $progress = new AccountScoreProgress([]);
    $progress->mergeProfile(accountProfile(professions: [accountProfession(30, 175)]));
    $progress->mergeProfile(accountProfile(professions: [accountProfession(50, 100)]));

    $expansion = $progress->buildResult()['professions'][0]['expansions'][0];

    expect($expansion['skill_points'])->toBe(50)
        ->and($expansion['max_skill_points'])->toBe(175);
});
