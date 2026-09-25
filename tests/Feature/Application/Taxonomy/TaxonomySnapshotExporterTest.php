<?php

declare(strict_types=1);

use App\Application\Taxonomy\TaxonomySnapshotExporter;
use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Infrastructure\Taxonomy\CollectionTaxonomySnapshot;
use App\Infrastructure\Taxonomy\TaxonomyEntry;
use App\Models\WowCollectionTaxonomy;

beforeEach(function (): void {
    $this->snapshotPath = sys_get_temp_dir().'/taxonomy-export-'.uniqid().'/'.CollectionTaxonomySnapshot::FILENAME;
});

afterEach(function (): void {
    removeDirectory(dirname((string) $this->snapshotPath));
});

function exporter(string $path): TaxonomySnapshotExporter
{
    return new TaxonomySnapshotExporter(new CollectionTaxonomySnapshot($path));
}

function inBase(CollectionEntity $collectionEntity, int $entryId, ?string $category, ?string $source, bool $obtainable = true): void
{
    WowCollectionTaxonomy::query()->insert([
        'entity' => $collectionEntity->value,
        'entry_id' => $entryId,
        'category' => $category,
        'source' => $source,
        'obtainable' => $obtainable,
    ]);
}

test('it writes what the database holds into the snapshot', function (): void {
    inBase(CollectionEntity::Mount, 6, 'Racial', 'Human');
    inBase(CollectionEntity::Pet, 40, 'Classic', 'Alliance Vendor');

    expect(exporter($this->snapshotPath)->export())
        ->toBe(['written' => 2, 'path' => $this->snapshotPath]);

    expect((new CollectionTaxonomySnapshot($this->snapshotPath))->entriesFor(CollectionEntity::Mount))
        ->toHaveKey(6);
});

test('an entry filed nowhere survives the round trip as filed nowhere', function (): void {
    inBase(CollectionEntity::Mount, 6, null, null);

    exporter($this->snapshotPath)->export();

    $entry = (new CollectionTaxonomySnapshot($this->snapshotPath))->entriesFor(CollectionEntity::Mount)[6];

    expect($entry->category)->toBeNull()
        ->and($entry->source)->toBeNull();
});

/**
 * Une table vide au moment de l'export écraserait silencieusement le fichier curé du dépôt,
 * ce qui est exactement le geste qu'on ne veut pas.
 */
test('it refuses to overwrite the snapshot with an empty taxonomy', function (): void {
    exporter($this->snapshotPath)->export();
})->throws(RuntimeException::class);

test('a refused export leaves the file it was about to overwrite alone', function (): void {
    inBase(CollectionEntity::Mount, 6, 'Racial', 'Human');
    exporter($this->snapshotPath)->export();

    WowCollectionTaxonomy::query()->delete();

    try {
        exporter($this->snapshotPath)->export();
    } catch (RuntimeException) {
    }

    expect((new CollectionTaxonomySnapshot($this->snapshotPath))->entriesFor(CollectionEntity::Mount))->toHaveKey(6);
});

test('the snapshot is in step with the database right after an export', function (): void {
    inBase(CollectionEntity::Mount, 6, 'Racial', 'Human');
    exporter($this->snapshotPath)->export();

    expect(exporter($this->snapshotPath)->state())->toBe([
        'path' => $this->snapshotPath,
        'entries' => 1,
        'in_step' => true,
        'missing_in_base' => 0,
        'missing_in_file' => 0,
        'differing' => 0,
    ]);
});

test('an arbitration that has not been exported shows the snapshot out of step', function (): void {
    inBase(CollectionEntity::Mount, 6, 'Racial', 'Human');
    exporter($this->snapshotPath)->export();

    inBase(CollectionEntity::Mount, 7, 'Racial', 'Orc');

    expect(exporter($this->snapshotPath)->state()['in_step'])->toBeFalse();
});

/**
 * Un même nombre de lignes ne prouve pas un même contenu : c'est le cas d'une correction,
 * qui change un libellé sans rien ajouter.
 */
test('a corrected label shows the snapshot out of step, though the count has not moved', function (): void {
    inBase(CollectionEntity::Mount, 6, 'Racial', 'Human');
    exporter($this->snapshotPath)->export();

    WowCollectionTaxonomy::query()->where('entry_id', 6)->update(['source' => 'Dwarf']);

    expect(exporter($this->snapshotPath)->state()['in_step'])->toBeFalse();
});

test('a snapshot that does not exist yet is out of step rather than an error', function (): void {
    inBase(CollectionEntity::Mount, 6, 'Racial', 'Human');

    expect(exporter($this->snapshotPath)->state())->toBe([
        'path' => $this->snapshotPath,
        'entries' => 0,
        'in_step' => false,
        'missing_in_base' => 0,
        'missing_in_file' => 1,
        'differing' => 0,
    ]);
});

test('an empty database and a missing snapshot are in step, there is nothing to commit', function (): void {
    expect(exporter($this->snapshotPath)->state()['in_step'])->toBeTrue();
});

test('the drift says which side lacks what, and what both sides hold differently', function (): void {
    (new CollectionTaxonomySnapshot($this->snapshotPath))->write([
        'mount' => [6 => new TaxonomyEntry('Racial', 'Human'), 7 => new TaxonomyEntry('Racial', 'Orc'), 8 => new TaxonomyEntry('PVP', 'Arena')],
        'pet' => [9 => new TaxonomyEntry('Wild', 'Battle')],
    ]);
    inBase(CollectionEntity::Mount, 6, 'Racial', 'Human');
    inBase(CollectionEntity::Mount, 7, 'Racial', 'Dwarf');
    inBase(CollectionEntity::Decor, 10, 'Housing', 'Vendor');

    expect(exporter($this->snapshotPath)->state())->toMatchArray([
        'in_step' => false,
        'missing_in_base' => 2,
        'missing_in_file' => 1,
        'differing' => 1,
    ]);
});

test('a snapshot rendered from the database is offered for download', function (): void {
    inBase(CollectionEntity::Mount, 6, 'Racial', 'Human');

    $download = exporter($this->snapshotPath)->download();

    expect($download['filename'])->toBe(CollectionTaxonomySnapshot::FILENAME)
        ->and($download['contents'])->toContain('mount,6,Racial,Human,true')
        ->and(is_file($this->snapshotPath))->toBeFalse();
});

test('an empty taxonomy is not offered for download', function (): void {
    exporter($this->snapshotPath)->download();
})->throws(RuntimeException::class, 'Taxonomie vide en base');
