<?php

declare(strict_types=1);

use App\Application\DTOs\CharacterProfileDTO;

test('it creates with all fields', function (): void {
    $characterProfileDTO = new CharacterProfileDTO(
        name: 'Thrall',
        realm: 'Hyjal',
        race: 'Orc',
        class: 'Chaman',
        classId: 7,
        level: 80,
        ilvl: 620,
        faction: 'Horde',
        avatarUrl: 'https://example.com/avatar.jpg',
        classIconUrl: 'https://example.com/class-icon.jpg',
        collections: [
            0 => [
                'quests' => ['total' => 100, 'completed' => 50, 'zones' => []],
                'achievements' => ['total' => 200, 'completed' => 100, 'categories' => []],
            ],
        ],
        mountsCount: 150,
        petsCount: 80,
        mounts: [['id' => 1, 'name' => 'Loup noir', 'is_completed' => true]],
        pets: [['id' => 1, 'name' => 'Dragonnet', 'is_completed' => false]],
        decorCount: 50,
        decor: [['id' => 500, 'name' => 'Foyer orné', 'is_completed' => true, 'item_id' => 245000, 'icon_url' => 'https://example.com/icon.jpg']],
        exaltedCount: 42,
    );

    expect($characterProfileDTO->name)->toBe('Thrall')
        ->and($characterProfileDTO->realm)->toBe('Hyjal')
        ->and($characterProfileDTO->race)->toBe('Orc')
        ->and($characterProfileDTO->class)->toBe('Chaman')
        ->and($characterProfileDTO->classId)->toBe(7)
        ->and($characterProfileDTO->level)->toBe(80)
        ->and($characterProfileDTO->ilvl)->toBe(620)
        ->and($characterProfileDTO->faction)->toBe('Horde')
        ->and($characterProfileDTO->mountsCount)->toBe(150)
        ->and($characterProfileDTO->petsCount)->toBe(80)
        ->and($characterProfileDTO->mounts)->toHaveCount(1)
        ->and($characterProfileDTO->pets)->toHaveCount(1)
        ->and($characterProfileDTO->decorCount)->toBe(50)
        ->and($characterProfileDTO->decor)->toHaveCount(1)
        ->and($characterProfileDTO->exaltedCount)->toBe(42);
});

test('it is readonly', function (): void {
    $characterProfileDTO = new CharacterProfileDTO(
        name: 'Test',
        realm: 'Realm',
        race: 'Human',
        class: 'Warrior',
        classId: 1,
        level: 1,
        ilvl: 1,
        faction: 'Alliance',
        avatarUrl: '',
        classIconUrl: '',
        collections: [],
        mountsCount: 0,
        petsCount: 0,
    );

    $reflectionClass = new ReflectionClass($characterProfileDTO);
    expect($reflectionClass->isReadOnly())->toBeTrue();
});

test('it defaults mounts and pets to empty arrays', function (): void {
    $characterProfileDTO = new CharacterProfileDTO(
        name: 'Test',
        realm: 'Realm',
        race: 'Human',
        class: 'Warrior',
        classId: 1,
        level: 1,
        ilvl: 1,
        faction: 'Alliance',
        avatarUrl: '',
        classIconUrl: '',
        collections: [],
        mountsCount: 0,
        petsCount: 0,
    );

    expect($characterProfileDTO->mounts)->toBe([])
        ->and($characterProfileDTO->pets)->toBe([])
        ->and($characterProfileDTO->decorCount)->toBe(0)
        ->and($characterProfileDTO->decor)->toBe([])
        ->and($characterProfileDTO->exaltedCount)->toBe(0);
});

test('it defaults equipment to empty array', function (): void {
    $characterProfileDTO = new CharacterProfileDTO(
        name: 'Test',
        realm: 'Realm',
        race: 'Human',
        class: 'Warrior',
        classId: 1,
        level: 1,
        ilvl: 1,
        faction: 'Alliance',
        avatarUrl: '',
        classIconUrl: '',
        collections: [],
        mountsCount: 0,
        petsCount: 0,
    );

    expect($characterProfileDTO->equipment)->toBe([]);
});

test('it accepts equipment array', function (): void {
    $equipment = [
        ['slot' => 'HEAD', 'slot_name' => 'Tête', 'item_id' => 123, 'name' => 'Casque', 'item_level' => 639, 'quality' => 'EPIC', 'icon_url' => null],
    ];

    $characterProfileDTO = new CharacterProfileDTO(
        name: 'Test',
        realm: 'Realm',
        race: 'Human',
        class: 'Warrior',
        classId: 1,
        level: 1,
        ilvl: 1,
        faction: 'Alliance',
        avatarUrl: '',
        classIconUrl: '',
        collections: [],
        mountsCount: 0,
        petsCount: 0,
        equipment: $equipment,
    );

    expect($characterProfileDTO->equipment)->toHaveCount(1)
        ->and($characterProfileDTO->equipment[0]['slot'])->toBe('HEAD');
});
