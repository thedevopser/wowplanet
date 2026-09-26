<?php

declare(strict_types=1);

use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Infrastructure\Taxonomy\CollectionTaxonomyLoader;
use App\Infrastructure\Taxonomy\CollectionTaxonomySnapshot;
use App\Infrastructure\Taxonomy\Exceptions\TaxonomySourceUnavailableException;
use App\Infrastructure\Taxonomy\TaxonomyEntry;
use App\Models\WowCollectionTaxonomy;

beforeEach(function (): void {
    $this->loaderSnapshotPath = testTempPath('taxonomy-loader').'/collection_taxonomy.csv';
});

afterEach(function (): void {
    if (file_exists($this->loaderSnapshotPath)) {
        unlink($this->loaderSnapshotPath);
        rmdir(dirname($this->loaderSnapshotPath));
    }
});

/**
 * @param  array<string, array<int, TaxonomyEntry>>  $entries
 */
function writeTaxonomySnapshot(string $path, array $entries): void
{
    (new CollectionTaxonomySnapshot($path))->write($entries);
}

function taxonomyLoader(string $path): CollectionTaxonomyLoader
{
    return new CollectionTaxonomyLoader(new CollectionTaxonomySnapshot($path));
}

/**
 * @param  list<int>  $entryIds
 * @return array<int, TaxonomyEntry>
 */
function taxonomyEntries(array $entryIds, string $category = 'Classic', string $source = 'Vendeur'): array
{
    $entries = [];

    foreach ($entryIds as $entryId) {
        $entries[$entryId] = new TaxonomyEntry($category, $source);
    }

    return $entries;
}

test('it seeds an empty taxonomy from the versioned snapshot', function (): void {
    writeTaxonomySnapshot($this->loaderSnapshotPath, [
        'mount' => [6648 => new TaxonomyEntry('Classic', 'Reputation')],
    ]);

    taxonomyLoader($this->loaderSnapshotPath)->load(CollectionEntity::Mount);

    $wowCollectionTaxonomy = WowCollectionTaxonomy::query()->where('entity', CollectionEntity::Mount)->sole();

    expect($wowCollectionTaxonomy->entry_id)->toBe(6648)
        ->and($wowCollectionTaxonomy->category)->toBe('Classic')
        ->and($wowCollectionTaxonomy->source)->toBe('Reputation');
});

test('it reports what it read and what it actually inserted', function (): void {
    writeTaxonomySnapshot($this->loaderSnapshotPath, ['pet' => taxonomyEntries([1, 2])]);

    expect(taxonomyLoader($this->loaderSnapshotPath)->load(CollectionEntity::Pet))
        ->toBe(['read' => 2, 'inserted' => 2, 'skipped' => 0]);
});

test('it never overwrites a row already in the taxonomy', function (): void {
    WowCollectionTaxonomy::factory()->create([
        'entity' => CollectionEntity::Mount,
        'entry_id' => 6648,
        'category' => 'Ajustement maison',
        'source' => 'Source arbitrée à la main',
    ]);

    writeTaxonomySnapshot($this->loaderSnapshotPath, [
        'mount' => [6648 => new TaxonomyEntry('Classic', 'Reputation')],
    ]);

    taxonomyLoader($this->loaderSnapshotPath)->load(CollectionEntity::Mount);

    $wowCollectionTaxonomy = WowCollectionTaxonomy::query()->where('entry_id', 6648)->sole();

    expect($wowCollectionTaxonomy->category)->toBe('Ajustement maison')
        ->and($wowCollectionTaxonomy->source)->toBe('Source arbitrée à la main');
});

test('it counts a row it left alone as skipped rather than inserted', function (): void {
    WowCollectionTaxonomy::factory()->create(['entity' => CollectionEntity::Mount, 'entry_id' => 1]);

    writeTaxonomySnapshot($this->loaderSnapshotPath, ['mount' => taxonomyEntries([1, 2])]);

    expect(taxonomyLoader($this->loaderSnapshotPath)->load(CollectionEntity::Mount))
        ->toBe(['read' => 2, 'inserted' => 1, 'skipped' => 1]);
});

test('it is idempotent: a second identical run inserts nothing', function (): void {
    writeTaxonomySnapshot($this->loaderSnapshotPath, ['mount' => taxonomyEntries([1, 2])]);

    $collectionTaxonomyLoader = taxonomyLoader($this->loaderSnapshotPath);
    $collectionTaxonomyLoader->load(CollectionEntity::Mount);

    expect($collectionTaxonomyLoader->load(CollectionEntity::Mount))->toBe(['read' => 2, 'inserted' => 0, 'skipped' => 2]);
});

test('it curates an entry whose category is empty as ranked nowhere, not as absent', function (): void {
    writeTaxonomySnapshot($this->loaderSnapshotPath, [
        'pet' => [1 => new TaxonomyEntry(null, null)],
    ]);

    taxonomyLoader($this->loaderSnapshotPath)->load(CollectionEntity::Pet);

    $wowCollectionTaxonomy = WowCollectionTaxonomy::query()->sole();

    expect($wowCollectionTaxonomy->category)->toBeNull()
        ->and($wowCollectionTaxonomy->source)->toBeNull();
});

test('it loads each collection into its own bucket', function (): void {
    writeTaxonomySnapshot($this->loaderSnapshotPath, [
        'mount' => [1 => new TaxonomyEntry('Classic', 'Drop')],
        'pet' => [1 => new TaxonomyEntry('Legion', 'Quest')],
    ]);

    $collectionTaxonomyLoader = taxonomyLoader($this->loaderSnapshotPath);
    $collectionTaxonomyLoader->load(CollectionEntity::Mount);
    $collectionTaxonomyLoader->load(CollectionEntity::Pet);

    expect(WowCollectionTaxonomy::query()->where('entity', CollectionEntity::Mount)->sole()->category)->toBe('Classic')
        ->and(WowCollectionTaxonomy::query()->where('entity', CollectionEntity::Pet)->sole()->category)->toBe('Legion');
});

test('it loads a volume larger than one insert chunk', function (): void {
    writeTaxonomySnapshot($this->loaderSnapshotPath, ['decor' => taxonomyEntries(range(1, 1200))]);

    expect(taxonomyLoader($this->loaderSnapshotPath)->load(CollectionEntity::Decor))
        ->toBe(['read' => 1200, 'inserted' => 1200, 'skipped' => 0])
        ->and(WowCollectionTaxonomy::query()->count())->toBe(1200);
});

test('it seeds the curated marker of an entry that can no longer be obtained', function (): void {
    writeTaxonomySnapshot($this->loaderSnapshotPath, [
        'decor' => [
            533 => new TaxonomyEntry('Quartiers', 'Promotion', false),
            534 => new TaxonomyEntry('Quartiers', 'Promotion', true),
        ],
    ]);

    taxonomyLoader($this->loaderSnapshotPath)->load(CollectionEntity::Decor);

    $rows = WowCollectionTaxonomy::query()->where('entity', CollectionEntity::Decor)->pluck('obtainable', 'entry_id');

    expect($rows[533])->toBeFalse()
        ->and($rows[534])->toBeTrue();
});

test('it fails loudly when the snapshot is missing, rather than emptying nothing in silence', function (): void {
    expect(fn (): array => taxonomyLoader($this->loaderSnapshotPath)->load(CollectionEntity::Mount))
        ->toThrow(TaxonomySourceUnavailableException::class, 'collection_taxonomy.csv');
});

test('it leaves the taxonomy untouched when the snapshot is missing', function (): void {
    WowCollectionTaxonomy::factory()->create(['entity' => CollectionEntity::Mount, 'entry_id' => 1]);

    try {
        taxonomyLoader($this->loaderSnapshotPath)->load(CollectionEntity::Mount);
    } catch (TaxonomySourceUnavailableException) {
        // L'état de la table est ce qui est vérifié ici.
    }

    expect(WowCollectionTaxonomy::query()->count())->toBe(1);
});

test('it fails loudly when the snapshot holds no entry for the requested collection', function (): void {
    writeTaxonomySnapshot($this->loaderSnapshotPath, ['mount' => taxonomyEntries([1])]);

    expect(fn (): array => taxonomyLoader($this->loaderSnapshotPath)->load(CollectionEntity::Pet))
        ->toThrow(TaxonomySourceUnavailableException::class, 'pet');
});

test('loadEntries inserts entries handed to it without reading the snapshot', function (): void {
    expect(taxonomyLoader($this->loaderSnapshotPath)->loadEntries(CollectionEntity::Mount, taxonomyEntries([7, 8])))
        ->toBe(['read' => 2, 'inserted' => 2, 'skipped' => 0])
        ->and(WowCollectionTaxonomy::query()->count())->toBe(2);
});

test('loadEntries handed nothing inserts nothing and reports it', function (): void {
    expect(taxonomyLoader($this->loaderSnapshotPath)->loadEntries(CollectionEntity::Mount, []))
        ->toBe(['read' => 0, 'inserted' => 0, 'skipped' => 0])
        ->and(WowCollectionTaxonomy::query()->count())->toBe(0);
});
