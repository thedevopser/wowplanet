<?php

declare(strict_types=1);

use App\Infrastructure\Taxonomy\CollectionEntity;

test('it covers the three collections the taxonomy curates', function (): void {
    expect(CollectionEntity::cases())->toHaveCount(3);
});

test('it names the SimpleArmory file each collection is seeded from', function (CollectionEntity $collectionEntity, string $filename): void {
    expect($collectionEntity->simpleArmoryFile())->toBe($filename);
})->with([
    'mount' => [CollectionEntity::Mount, 'mounts.json'],
    'pet' => [CollectionEntity::Pet, 'pets.json'],
    'decor' => [CollectionEntity::Decor, 'decors.json'],
]);

test('it resolves an entity from the name a command option carries', function (): void {
    expect(CollectionEntity::fromOption('pet'))->toBe(CollectionEntity::Pet);
});

test('it ignores the casing and the padding of that name', function (): void {
    expect(CollectionEntity::fromOption('  Decor '))->toBe(CollectionEntity::Decor);
});

test('it refuses a name it does not know, listing the ones it does', function (): void {
    expect(fn (): CollectionEntity => CollectionEntity::fromOption('toy'))
        ->toThrow(InvalidArgumentException::class, 'mount, pet, decor');
});

test('it stores its own value as the discriminator of a taxonomy row', function (): void {
    expect(CollectionEntity::Mount->value)->toBe('mount');
});
