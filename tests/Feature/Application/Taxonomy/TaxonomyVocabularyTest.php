<?php

declare(strict_types=1);

use App\Application\Taxonomy\TaxonomyVocabulary;
use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Models\WowCollectionTaxonomy;

function curatedAs(CollectionEntity $collectionEntity, int $entryId, ?string $category, ?string $source): void
{
    WowCollectionTaxonomy::query()->insert([
        'entity' => $collectionEntity->value,
        'entry_id' => $entryId,
        'category' => $category,
        'source' => $source,
        'obtainable' => true,
    ]);
}

function vocabularyFor(CollectionEntity $collectionEntity): array
{
    return resolve(TaxonomyVocabulary::class)->forEntity($collectionEntity);
}

test('it offers the categories and sources the collection really carries', function (): void {
    curatedAs(CollectionEntity::Mount, 1, 'Racial', 'Human');
    curatedAs(CollectionEntity::Mount, 2, 'Professions', 'Fishing');

    expect(vocabularyFor(CollectionEntity::Mount))->toBe([
        'categories' => ['Professions', 'Racial'],
        'sources' => ['Fishing', 'Human'],
    ]);
});

test('a value used twice is offered once', function (): void {
    curatedAs(CollectionEntity::Mount, 1, 'Racial', 'Human');
    curatedAs(CollectionEntity::Mount, 2, 'Racial', 'Human');

    expect(vocabularyFor(CollectionEntity::Mount)['categories'])->toBe(['Racial']);
});

test('each collection has its own vocabulary, they are not pooled', function (): void {
    curatedAs(CollectionEntity::Mount, 1, 'Racial', 'Human');
    curatedAs(CollectionEntity::Pet, 1, 'Classic', 'Vendor');

    expect(vocabularyFor(CollectionEntity::Mount)['categories'])->toBe(['Racial'])
        ->and(vocabularyFor(CollectionEntity::Pet)['categories'])->toBe(['Classic']);
});

test('an entry filed nowhere contributes no empty value to the vocabulary', function (): void {
    curatedAs(CollectionEntity::Mount, 1, 'Racial', 'Human');
    curatedAs(CollectionEntity::Mount, 2, null, null);

    expect(vocabularyFor(CollectionEntity::Mount))->toBe([
        'categories' => ['Racial'],
        'sources' => ['Human'],
    ]);
});

test('an empty taxonomy offers an empty vocabulary rather than failing', function (): void {
    expect(vocabularyFor(CollectionEntity::Mount))->toBe(['categories' => [], 'sources' => []]);
});

test('values come out sorted, so the same list reads the same way twice', function (): void {
    curatedAs(CollectionEntity::Mount, 1, 'Zandalari', 'Zul');
    curatedAs(CollectionEntity::Mount, 2, 'Argent', 'Achievement');

    expect(vocabularyFor(CollectionEntity::Mount)['categories'])->toBe(['Argent', 'Zandalari']);
});
