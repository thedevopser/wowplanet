<?php

declare(strict_types=1);

use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Infrastructure\Taxonomy\CollectionTaxonomyReader;
use App\Infrastructure\Taxonomy\TaxonomyEntry;
use App\Models\WowCollectionTaxonomy;

function readTaxonomy(CollectionEntity $collectionEntity): array
{
    return resolve(CollectionTaxonomyReader::class)->for($collectionEntity);
}

test('it keys the ranking of a collection by Blizzard identifier', function (): void {
    WowCollectionTaxonomy::factory()->create([
        'entity' => CollectionEntity::Mount,
        'entry_id' => 6648,
        'category' => 'Classic',
        'source' => 'Reputation',
    ]);

    $taxonomy = readTaxonomy(CollectionEntity::Mount);

    expect($taxonomy)->toHaveKey(6648)
        ->and($taxonomy[6648])->toBeInstanceOf(TaxonomyEntry::class)
        ->and($taxonomy[6648]->category)->toBe('Classic')
        ->and($taxonomy[6648]->source)->toBe('Reputation');
});

test('it reads only the collection it was asked for', function (): void {
    WowCollectionTaxonomy::factory()->create(['entity' => CollectionEntity::Mount, 'entry_id' => 1]);
    WowCollectionTaxonomy::factory()->create(['entity' => CollectionEntity::Pet, 'entry_id' => 2]);

    expect(array_keys(readTaxonomy(CollectionEntity::Mount)))->toBe([1]);
});

test('it returns nothing for a collection that has never been curated', function (): void {
    expect(readTaxonomy(CollectionEntity::Decor))->toBe([]);
});

test('it keeps an entry curated as ranked nowhere, which an importer must not confuse with an absent one', function (): void {
    WowCollectionTaxonomy::factory()->create([
        'entity' => CollectionEntity::Pet,
        'entry_id' => 42,
        'category' => null,
        'source' => null,
    ]);

    $taxonomy = readTaxonomy(CollectionEntity::Pet);

    expect($taxonomy)->toHaveKey(42)
        ->and($taxonomy[42]->category)->toBeNull()
        ->and($taxonomy[42]->source)->toBeNull();
});

test('it reads a whole collection in one pass', function (): void {
    WowCollectionTaxonomy::factory()->count(50)->create(['entity' => CollectionEntity::Decor]);

    expect(readTaxonomy(CollectionEntity::Decor))->toHaveCount(50);
});

test('it reads an entry curated as no longer obtainable', function (): void {
    WowCollectionTaxonomy::factory()->create([
        'entity' => CollectionEntity::Decor,
        'entry_id' => 533,
        'obtainable' => false,
    ]);

    expect(readTaxonomy(CollectionEntity::Decor)[533]->obtainable)->toBeFalse();
});

test('it holds an entry obtainable unless the curation says otherwise', function (): void {
    WowCollectionTaxonomy::factory()->create(['entity' => CollectionEntity::Decor, 'entry_id' => 534]);

    expect(readTaxonomy(CollectionEntity::Decor)[534]->obtainable)->toBeTrue();
});
