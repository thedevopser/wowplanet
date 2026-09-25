<?php

declare(strict_types=1);

use App\Infrastructure\Reference\ReferenceStore;
use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Infrastructure\Taxonomy\Exceptions\TaxonomySourceUnavailableException;
use App\Infrastructure\Taxonomy\SimpleArmoryTaxonomyReader;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake(ReferenceStore::DISK);
});

/**
 * @param  list<array<string, mixed>>  $categories
 */
function writeUpstreamDump(CollectionEntity $collectionEntity, array $categories): void
{
    Storage::disk(ReferenceStore::DISK)->put(
        $collectionEntity->simpleArmoryFile(),
        json_encode($categories, JSON_THROW_ON_ERROR),
    );
}

/**
 * @param  list<array<string, mixed>>  $items
 * @return array<string, mixed>
 */
function upstreamCategory(string $category, string $source, array $items): array
{
    return ['name' => $category, 'subcats' => [['name' => $source, 'items' => $items]]];
}

test('it reads the curated ranking of each entry', function (): void {
    writeUpstreamDump(CollectionEntity::Mount, [
        upstreamCategory('Classic', 'Réputation', [['ID' => 6648, 'name' => 'Coursier en sucre']]),
    ]);

    $entries = (new SimpleArmoryTaxonomyReader)->entriesFor(CollectionEntity::Mount);

    expect(array_keys($entries))->toBe([6648])
        ->and($entries[6648]->category)->toBe('Classic')
        ->and($entries[6648]->source)->toBe('Réputation')
        ->and($entries[6648]->obtainable)->toBeTrue();
});

test('it reads the curated marker of an entry that can no longer be obtained', function (): void {
    writeUpstreamDump(CollectionEntity::Decor, [
        upstreamCategory('Quartiers', 'Promotion', [
            ['ID' => 533, 'name' => 'Pilier', 'notObtainable' => true],
            ['ID' => 534, 'name' => 'Lampe'],
        ]),
    ]);

    $entries = (new SimpleArmoryTaxonomyReader)->entriesFor(CollectionEntity::Decor);

    expect($entries[533]->obtainable)->toBeFalse()
        ->and($entries[534]->obtainable)->toBeTrue();
});

test('it keeps the last ranking of an identifier listed under several categories, as the catalog does today', function (): void {
    writeUpstreamDump(CollectionEntity::Mount, [
        upstreamCategory('Limited Time', 'Trading Post: September', [['ID' => 2628, 'name' => 'Rênes']]),
        upstreamCategory('Past Limited Time', 'Trading Post Originals', [['ID' => 2628, 'name' => 'Rênes']]),
    ]);

    $entries = (new SimpleArmoryTaxonomyReader)->entriesFor(CollectionEntity::Mount);

    expect($entries[2628]->category)->toBe('Past Limited Time')
        ->and($entries[2628]->source)->toBe('Trading Post Originals');
});

test('it skips an entry that has not been released yet', function (): void {
    writeUpstreamDump(CollectionEntity::Pet, [
        upstreamCategory('Midnight', 'Quête', [
            ['ID' => 1, 'name' => 'Sorti'],
            ['ID' => 2, 'name' => 'À venir', 'notReleased' => true],
        ]),
    ]);

    expect(array_keys((new SimpleArmoryTaxonomyReader)->entriesFor(CollectionEntity::Pet)))->toBe([1]);
});

test('it skips an entry without a usable identifier', function (): void {
    writeUpstreamDump(CollectionEntity::Pet, [
        upstreamCategory('Classic', 'Butin', [
            ['ID' => 0, 'name' => 'Sans identifiant'],
            ['name' => 'Sans clé'],
            ['ID' => 5, 'name' => 'Valide'],
        ]),
    ]);

    expect(array_keys((new SimpleArmoryTaxonomyReader)->entriesFor(CollectionEntity::Pet)))->toBe([5]);
});

test('an empty label reads as ranked nowhere rather than as an empty string', function (): void {
    writeUpstreamDump(CollectionEntity::Pet, [upstreamCategory('', '', [['ID' => 1, 'name' => 'Un']])]);

    $entry = (new SimpleArmoryTaxonomyReader)->entriesFor(CollectionEntity::Pet)[1];

    expect($entry->category)->toBeNull()
        ->and($entry->source)->toBeNull();
});

test('it trims the labels it reads', function (): void {
    writeUpstreamDump(CollectionEntity::Mount, [
        upstreamCategory("  Legion \r\n", '  Salle de classe  ', [['ID' => 1, 'name' => 'Un']]),
    ]);

    $entry = (new SimpleArmoryTaxonomyReader)->entriesFor(CollectionEntity::Mount)[1];

    expect($entry->category)->toBe('Legion')
        ->and($entry->source)->toBe('Salle de classe');
});

test('a missing dump is reported rather than read as empty', function (): void {
    expect(fn (): array => (new SimpleArmoryTaxonomyReader)->entriesFor(CollectionEntity::Mount))
        ->toThrow(TaxonomySourceUnavailableException::class, 'mounts.json');
});

test('an invalid json dump is reported', function (): void {
    Storage::disk(ReferenceStore::DISK)->put('pets.json', '{ not json');

    expect(fn (): array => (new SimpleArmoryTaxonomyReader)->entriesFor(CollectionEntity::Pet))
        ->toThrow(TaxonomySourceUnavailableException::class, 'pets.json');
});

test('a dump holding no usable entry is reported', function (): void {
    writeUpstreamDump(CollectionEntity::Pet, []);

    expect(fn (): array => (new SimpleArmoryTaxonomyReader)->entriesFor(CollectionEntity::Pet))
        ->toThrow(TaxonomySourceUnavailableException::class, 'pets.json');
});

test('the wrapped structure served by some upstream pages is refused', function (): void {
    Storage::disk(ReferenceStore::DISK)->put(
        'decors.json',
        json_encode(['supercats' => [['name' => 'Quartiers']]], JSON_THROW_ON_ERROR),
    );

    expect(fn (): array => (new SimpleArmoryTaxonomyReader)->entriesFor(CollectionEntity::Decor))
        ->toThrow(TaxonomySourceUnavailableException::class, 'decors.json');
});

test('a category that is not an object is passed over rather than refused', function (): void {
    Storage::disk(ReferenceStore::DISK)->put(
        'mounts.json',
        json_encode(['une chaîne', upstreamCategory('Classic', 'Butin', [['ID' => 1, 'name' => 'Un']])], JSON_THROW_ON_ERROR),
    );

    expect(array_keys((new SimpleArmoryTaxonomyReader)->entriesFor(CollectionEntity::Mount)))->toBe([1]);
});

test('a label served as something other than text reads as ranked nowhere', function (): void {
    writeUpstreamDump(CollectionEntity::Pet, [
        ['name' => 42, 'subcats' => [['name' => 'Butin', 'items' => [['ID' => 1, 'name' => 'Un']]]]],
    ]);

    expect((new SimpleArmoryTaxonomyReader)->entriesFor(CollectionEntity::Pet)[1]->category)->toBeNull();
});

test('a subcategory list that is not a list is refused, naming the file and the field', function (): void {
    writeUpstreamDump(CollectionEntity::Mount, [
        upstreamCategory('Classic', 'Butin', [['ID' => 1, 'name' => 'Un']]),
        ['name' => 'Legion', 'subcats' => 'aucune'],
    ]);

    expect(fn (): array => (new SimpleArmoryTaxonomyReader)->entriesFor(CollectionEntity::Mount))
        ->toThrow(TaxonomySourceUnavailableException::class, 'Field [subcats] in the response of [mounts.json, category 1]');
});

test('an obtainability marker that is not a boolean is refused, naming the file and the field', function (): void {
    writeUpstreamDump(CollectionEntity::Pet, [
        upstreamCategory('Classic', 'Butin', [['ID' => 1, 'name' => 'Un', 'notObtainable' => 'oui']]),
    ]);

    expect(fn (): array => (new SimpleArmoryTaxonomyReader)->entriesFor(CollectionEntity::Pet))
        ->toThrow(TaxonomySourceUnavailableException::class, 'Field [subcats.0.items.0.notObtainable] in the response of [pets.json, category 0]');
});
