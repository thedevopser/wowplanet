<?php

declare(strict_types=1);

use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Infrastructure\Taxonomy\CollectionTaxonomySnapshot;
use App\Infrastructure\Taxonomy\Exceptions\TaxonomySnapshotMalformedException;
use App\Infrastructure\Taxonomy\Exceptions\TaxonomySourceUnavailableException;
use App\Infrastructure\Taxonomy\TaxonomyEntry;

beforeEach(function (): void {
    $this->snapshotPath = testTempPath('taxonomy-snapshot').'/collection_taxonomy.csv';
});

afterEach(function (): void {
    if (file_exists($this->snapshotPath)) {
        unlink($this->snapshotPath);
        rmdir(dirname($this->snapshotPath));
    }
});

function snapshotWrite(string $path, string $contents): void
{
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0o777, true);
    }

    file_put_contents($path, $contents);
}

test('the default path is the file versioned with the repository', function (): void {
    expect((new CollectionTaxonomySnapshot)->path())
        ->toBe(database_path('data/collection_taxonomy.csv'));
});

test('it reads entries grouped by entity and indexed by entry id', function (): void {
    snapshotWrite($this->snapshotPath, <<<'CSV'
        entity,entry_id,category,source,obtainable
        mount,6,Classic,Vendeur,true
        mount,74,Classic,Butin,true
        pet,39,Classic,Vendeur,true
        decor,2113,Logis,Artisanat,false
        CSV);

    $entries = (new CollectionTaxonomySnapshot($this->snapshotPath))->read();

    expect(array_keys($entries))->toBe(['mount', 'pet', 'decor'])
        ->and(array_keys($entries['mount']))->toBe([6, 74])
        ->and($entries['mount'][6]->category)->toBe('Classic')
        ->and($entries['mount'][6]->source)->toBe('Vendeur')
        ->and($entries['mount'][6]->obtainable)->toBeTrue()
        ->and($entries['decor'][2113]->obtainable)->toBeFalse();
});

test('an empty field reads as a deliberately unplaced entry', function (): void {
    snapshotWrite($this->snapshotPath, <<<'CSV'
        entity,entry_id,category,source,obtainable
        pet,1234,,,true
        CSV);

    $entry = (new CollectionTaxonomySnapshot($this->snapshotPath))->read()['pet'][1234];

    expect($entry->category)->toBeNull()
        ->and($entry->source)->toBeNull();
});

test('a label carrying a comma survives the round trip', function (): void {
    $snapshot = new CollectionTaxonomySnapshot($this->snapshotPath);
    $snapshot->write(['mount' => [6 => new TaxonomyEntry('Legion, saison 2', 'Haut fait "Allons-y !"', true)]]);

    $entry = $snapshot->read()['mount'][6];

    expect($entry->category)->toBe('Legion, saison 2')
        ->and($entry->source)->toBe('Haut fait "Allons-y !"');
});

test('entriesFor returns the slice of a single entity', function (): void {
    snapshotWrite($this->snapshotPath, <<<'CSV'
        entity,entry_id,category,source,obtainable
        mount,6,Classic,Vendeur,true
        pet,39,Classic,Butin,true
        CSV);

    $snapshot = new CollectionTaxonomySnapshot($this->snapshotPath);

    expect(array_keys($snapshot->entriesFor(CollectionEntity::Mount)))->toBe([6])
        ->and(array_keys($snapshot->entriesFor(CollectionEntity::Pet)))->toBe([39])
        ->and($snapshot->entriesFor(CollectionEntity::Decor))->toBe([]);
});

test('a missing snapshot is reported rather than read as empty', function (): void {
    expect(fn (): array => (new CollectionTaxonomySnapshot($this->snapshotPath))->read())
        ->toThrow(TaxonomySourceUnavailableException::class);
});

test('a snapshot holding nothing but its header is reported too', function (): void {
    snapshotWrite($this->snapshotPath, "entity,entry_id,category,source,obtainable\n");

    expect(fn (): array => (new CollectionTaxonomySnapshot($this->snapshotPath))->read())
        ->toThrow(TaxonomySourceUnavailableException::class);
});

test('an unexpected header is refused', function (): void {
    snapshotWrite($this->snapshotPath, <<<'CSV'
        entity,entry_id,category,source
        mount,6,Classic,Vendeur
        CSV);

    expect(fn (): array => (new CollectionTaxonomySnapshot($this->snapshotPath))->read())
        ->toThrow(TaxonomySnapshotMalformedException::class, 'En-tête');
});

test('an unknown entity is refused', function (): void {
    snapshotWrite($this->snapshotPath, <<<'CSV'
        entity,entry_id,category,source,obtainable
        toy,6,Classic,Vendeur,true
        CSV);

    expect(fn (): array => (new CollectionTaxonomySnapshot($this->snapshotPath))->read())
        ->toThrow(TaxonomySnapshotMalformedException::class, 'toy');
});

test('a non-integer entry id is refused', function (): void {
    snapshotWrite($this->snapshotPath, <<<'CSV'
        entity,entry_id,category,source,obtainable
        mount,six,Classic,Vendeur,true
        CSV);

    expect(fn (): array => (new CollectionTaxonomySnapshot($this->snapshotPath))->read())
        ->toThrow(TaxonomySnapshotMalformedException::class, 'six');
});

test('an obtainable flag that is neither true nor false is refused', function (): void {
    snapshotWrite($this->snapshotPath, <<<'CSV'
        entity,entry_id,category,source,obtainable
        mount,6,Classic,Vendeur,peut-être
        CSV);

    expect(fn (): array => (new CollectionTaxonomySnapshot($this->snapshotPath))->read())
        ->toThrow(TaxonomySnapshotMalformedException::class, 'peut-être');
});

test('a row with a wrong column count is refused', function (): void {
    snapshotWrite($this->snapshotPath, <<<'CSV'
        entity,entry_id,category,source,obtainable
        mount,6,Classic,Vendeur
        CSV);

    expect(fn (): array => (new CollectionTaxonomySnapshot($this->snapshotPath))->read())
        ->toThrow(TaxonomySnapshotMalformedException::class);
});

test('it writes the header, one line per entry, and reports the count', function (): void {
    $written = (new CollectionTaxonomySnapshot($this->snapshotPath))->write([
        'mount' => [6 => new TaxonomyEntry('Classic', 'Vendeur', true)],
        'pet' => [39 => new TaxonomyEntry(null, null, true)],
        'decor' => [2113 => new TaxonomyEntry('Logis', 'Artisanat', false)],
    ]);

    expect($written)->toBe(3)
        ->and(file_get_contents($this->snapshotPath))->toBe(<<<'CSV'
            entity,entry_id,category,source,obtainable
            mount,6,Classic,Vendeur,true
            pet,39,,,true
            decor,2113,Logis,Artisanat,false

            CSV);
});

test('writing orders entities by their declaration and entries by identifier', function (): void {
    (new CollectionTaxonomySnapshot($this->snapshotPath))->write([
        'decor' => [90 => new TaxonomyEntry('Logis', null, true), 12 => new TaxonomyEntry('Logis', null, true)],
        'mount' => [200 => new TaxonomyEntry('Classic', null, true), 7 => new TaxonomyEntry('Classic', null, true)],
    ]);

    $lines = array_slice(file($this->snapshotPath, FILE_IGNORE_NEW_LINES), 1);

    expect($lines)->toBe([
        'mount,7,Classic,,true',
        'mount,200,Classic,,true',
        'decor,12,Logis,,true',
        'decor,90,Logis,,true',
    ]);
});

test('writing creates the directory when it does not exist yet', function (): void {
    (new CollectionTaxonomySnapshot($this->snapshotPath))->write([
        'mount' => [6 => new TaxonomyEntry('Classic', 'Vendeur', true)],
    ]);

    expect(file_exists($this->snapshotPath))->toBeTrue();
});

test('writing an unknown entity key is refused', function (): void {
    expect(fn (): int => (new CollectionTaxonomySnapshot($this->snapshotPath))->write([
        'toy' => [6 => new TaxonomyEntry('Classic', 'Vendeur', true)],
    ]))->toThrow(TaxonomySnapshotMalformedException::class, 'toy');
});

test('a blank line between two entries is passed over rather than refused', function (): void {
    snapshotWrite($this->snapshotPath, "entity,entry_id,category,source,obtainable\nmount,6,Classic,Vendeur,true\n\nmount,7,Classic,Vendeur,true\n");

    expect(array_keys((new CollectionTaxonomySnapshot($this->snapshotPath))->read()['mount']))->toBe([6, 7]);
});
