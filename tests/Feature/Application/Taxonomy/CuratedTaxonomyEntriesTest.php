<?php

declare(strict_types=1);

use App\Application\Taxonomy\CuratedTaxonomyEntries;
use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Models\WowCollectionTaxonomy;
use App\Models\WowMount;
use App\Models\WowPet;

function shelvedMount(int $id, string $name, ?string $category, ?string $source): void
{
    WowMount::query()->create(['id' => $id, 'name_fr' => $name, 'category' => $category, 'source' => $source, 'is_active' => true]);
    WowCollectionTaxonomy::query()->insert([
        'entity' => CollectionEntity::Mount->value,
        'entry_id' => $id,
        'category' => $category,
        'source' => $source,
        'obtainable' => true,
    ]);
}

function curatedEntries(): CuratedTaxonomyEntries
{
    return resolve(CuratedTaxonomyEntries::class);
}

test('it lists the catalogue entries the taxonomy ranks, with their current ranking', function (): void {
    shelvedMount(7, 'Loup gris', 'Racial', 'Human');

    expect(curatedEntries()->forEntity(CollectionEntity::Mount))
        ->toBe([['id' => 7, 'name' => 'Loup gris', 'category' => 'Racial', 'source' => 'Human']]);
});

test('a pending entry is not listed, since it has no ranking to correct yet', function (): void {
    WowMount::query()->create(['id' => 7, 'name_fr' => 'Loup gris', 'is_active' => true]);

    expect(curatedEntries()->forEntity(CollectionEntity::Mount))->toBe([]);
});

test('a taxonomy line whose entry left the catalogue is not listed, since nothing on the site shows it', function (): void {
    WowCollectionTaxonomy::query()->insert([
        'entity' => CollectionEntity::Mount->value, 'entry_id' => 999, 'category' => 'Other', 'source' => null, 'obtainable' => true,
    ]);

    expect(curatedEntries()->forEntity(CollectionEntity::Mount))->toBe([]);
});

test('the ranking shown is the taxonomy one, even where the catalogue has not caught up yet', function (): void {
    shelvedMount(7, 'Loup gris', 'Racial', 'Human');
    WowMount::query()->whereKey(7)->update(['category' => 'Other', 'source' => null]);

    expect(curatedEntries()->forEntity(CollectionEntity::Mount)[0])->toMatchArray(['category' => 'Racial', 'source' => 'Human']);
});

test('it only lists the collection asked for', function (): void {
    shelvedMount(7, 'Loup gris', 'Racial', 'Human');
    WowPet::query()->create(['id' => 7, 'name_fr' => 'Chouette blanche', 'is_active' => true]);
    WowCollectionTaxonomy::query()->insert([
        'entity' => CollectionEntity::Pet->value, 'entry_id' => 7, 'category' => 'Classic', 'source' => 'Vendor', 'obtainable' => true,
    ]);

    expect(array_column(curatedEntries()->forEntity(CollectionEntity::Pet), 'name'))->toBe(['Chouette blanche']);
});

test('entries are sorted by name, then by identifier', function (): void {
    shelvedMount(9, 'Zèbre', 'Other', null);
    shelvedMount(8, 'Loup gris', 'Other', null);
    shelvedMount(3, 'Loup gris', 'Other', null);

    expect(array_column(curatedEntries()->forEntity(CollectionEntity::Mount), 'id'))->toBe([3, 8, 9]);
});

test('a search matches the French name whatever its case', function (): void {
    shelvedMount(7, 'Loup gris', 'Racial', 'Human');
    shelvedMount(8, 'Étalon blanc', 'Racial', 'Human');

    expect(array_column(curatedEntries()->forEntity(CollectionEntity::Mount, 'LOUP'), 'id'))->toBe([7]);
});

test('a numeric search also matches the exact identifier', function (): void {
    shelvedMount(7, 'Loup gris', 'Racial', 'Human');
    shelvedMount(70, 'Étalon blanc', 'Racial', 'Human');

    expect(array_column(curatedEntries()->forEntity(CollectionEntity::Mount, '7'), 'id'))->toBe([7]);
});

test('a category filter keeps the entries ranked exactly under it', function (): void {
    shelvedMount(7, 'Loup gris', 'Other', null);
    shelvedMount(8, 'Étalon blanc', 'Legion', 'Class Hall');
    shelvedMount(9, 'Raptor', 'Other raids', null);

    expect(array_column(curatedEntries()->forEntity(CollectionEntity::Mount, category: 'Other'), 'id'))->toBe([7]);
});

test('the uncategorised filter keeps the entries ranked nowhere on purpose', function (): void {
    shelvedMount(7, 'Loup gris', null, null);
    shelvedMount(8, 'Étalon blanc', 'Legion', 'Class Hall');

    expect(array_column(curatedEntries()->forEntity(CollectionEntity::Mount, category: CuratedTaxonomyEntries::UNCATEGORISED), 'id'))->toBe([7]);
});

test('search and category filter combine', function (): void {
    shelvedMount(7, 'Loup gris', 'Other', null);
    shelvedMount(8, 'Loup noir', 'Legion', null);
    shelvedMount(9, 'Raptor', 'Other', null);

    expect(array_column(curatedEntries()->forEntity(CollectionEntity::Mount, 'loup', 'Other'), 'id'))->toBe([7]);
});

test('it tells the categories of the collection with their size, the uncategorised ones last', function (): void {
    shelvedMount(7, 'Loup gris', 'Other', null);
    shelvedMount(8, 'Étalon blanc', 'Legion', null);
    shelvedMount(9, 'Raptor', 'Other', null);
    shelvedMount(10, 'Tortue', null, null);

    expect(curatedEntries()->categories(CollectionEntity::Mount))->toBe([
        ['value' => 'Legion', 'category' => 'Legion', 'entries' => 1],
        ['value' => 'Other', 'category' => 'Other', 'entries' => 2],
        ['value' => CuratedTaxonomyEntries::UNCATEGORISED, 'category' => null, 'entries' => 1],
    ]);
});

test('the categories only count entries still in the catalogue', function (): void {
    shelvedMount(7, 'Loup gris', 'Other', null);
    WowCollectionTaxonomy::query()->insert([
        'entity' => CollectionEntity::Mount->value, 'entry_id' => 999, 'category' => 'Other', 'source' => null, 'obtainable' => true,
    ]);

    expect(curatedEntries()->categories(CollectionEntity::Mount))->toBe([['value' => 'Other', 'category' => 'Other', 'entries' => 1]]);
});

test('it counts the ranked entries of every collection', function (): void {
    shelvedMount(7, 'Loup gris', 'Other', null);
    shelvedMount(8, 'Étalon blanc', null, null);
    WowMount::query()->create(['id' => 9, 'name_fr' => 'Raptor', 'is_active' => true]);

    expect(curatedEntries()->counts())->toBe(['mount' => 2, 'pet' => 0, 'decor' => 0]);
});
