<?php

declare(strict_types=1);

use App\Application\Taxonomy\PendingTaxonomyEntries;
use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Models\WowCollectionTaxonomy;
use App\Models\WowMount;
use App\Models\WowPet;

function catalogued(int $id, string $name, ?string $source = null): WowMount
{
    return WowMount::query()->create([
        'id' => $id,
        'name_fr' => $name,
        'source' => $source,
        'category' => null,
        'is_active' => true,
    ]);
}

function curated(CollectionEntity $collectionEntity, int $entryId, ?string $category, ?string $source): void
{
    WowCollectionTaxonomy::query()->insert([
        'entity' => $collectionEntity->value,
        'entry_id' => $entryId,
        'category' => $category,
        'source' => $source,
        'obtainable' => true,
    ]);
}

function pending(): PendingTaxonomyEntries
{
    return resolve(PendingTaxonomyEntries::class);
}

test('a catalogue entry the taxonomy does not carry is pending', function (): void {
    catalogued(7, 'Loup gris');

    expect(pending()->forEntity(CollectionEntity::Mount))
        ->toBe([['id' => 7, 'name' => 'Loup gris', 'pending_source' => null]]);
});

test('a curated entry is not pending', function (): void {
    catalogued(7, 'Loup gris');
    curated(CollectionEntity::Mount, 7, 'Racial', 'Human');

    expect(pending()->forEntity(CollectionEntity::Mount))->toBe([]);
});

/**
 * C'est la distinction que porte le modèle : une ligne aux deux libellés nuls est une entrée
 * rangée nulle part en connaissance de cause, donc curée. L'absence de ligne est l'arbitrage
 * qui reste à faire.
 */
test('an entry deliberately filed nowhere is curated, so it is not pending', function (): void {
    catalogued(7, 'Loup gris');
    curated(CollectionEntity::Mount, 7, null, null);

    expect(pending()->forEntity(CollectionEntity::Mount))->toBe([]);
});

test('a pending entry carries the waiting value the importer left on it', function (): void {
    catalogued(7, 'Loup gris', 'Trading Post');

    expect(pending()->forEntity(CollectionEntity::Mount)[0]['pending_source'])->toBe('Trading Post');
});

test('a taxonomy row for another collection does not curate this one', function (): void {
    catalogued(7, 'Loup gris');
    curated(CollectionEntity::Pet, 7, 'Classic', 'Vendor');

    expect(pending()->forEntity(CollectionEntity::Mount))->toHaveCount(1);
});

test('pending entries come out by ascending identifier', function (): void {
    catalogued(12, 'Loup noir');
    catalogued(7, 'Loup gris');

    expect(array_column(pending()->forEntity(CollectionEntity::Mount), 'id'))->toBe([7, 12]);
});

test('it counts what is pending against what the catalogue holds, collection by collection', function (): void {
    catalogued(7, 'Loup gris');
    catalogued(12, 'Loup noir');
    curated(CollectionEntity::Mount, 12, 'Racial', 'Human');
    WowPet::query()->create(['id' => 69, 'name_fr' => 'Chouette blanche', 'is_active' => true]);

    expect(pending()->counts())->toBe([
        'mount' => ['pending' => 1, 'catalogue' => 2],
        'pet' => ['pending' => 1, 'catalogue' => 1],
        'decor' => ['pending' => 0, 'catalogue' => 0],
    ]);
});

test('an empty catalogue counts zero rather than failing', function (): void {
    expect(pending()->counts()['mount'])->toBe(['pending' => 0, 'catalogue' => 0]);
});

test('it totals what is left to arbitrate across the three collections', function (): void {
    catalogued(7, 'Loup gris');
    WowPet::query()->create(['id' => 69, 'name_fr' => 'Chouette blanche', 'is_active' => true]);

    expect(pending()->total())->toBe(2);
});

test('a search narrows the pending list by name', function (): void {
    catalogued(7, 'Loup gris');
    catalogued(12, 'Étalon blanc');

    expect(array_column(pending()->forEntity(CollectionEntity::Mount, 'loup'), 'id'))->toBe([7]);
});

test('a search matches an identifier too, which is how one finds a known entry', function (): void {
    catalogued(7, 'Loup gris');
    catalogued(12, 'Étalon blanc');

    expect(array_column(pending()->forEntity(CollectionEntity::Mount, '12'), 'id'))->toBe([12]);
});

test('a search that matches nothing yields nothing rather than everything', function (): void {
    catalogued(7, 'Loup gris');

    expect(pending()->forEntity(CollectionEntity::Mount, 'griffon'))->toBe([]);
});
