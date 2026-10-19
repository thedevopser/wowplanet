<?php

declare(strict_types=1);

use App\Infrastructure\Reference\ReferenceStore;
use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Infrastructure\Taxonomy\CollectionTaxonomySnapshot;
use App\Infrastructure\Taxonomy\TaxonomyEntry;
use App\Models\WowCollectionTaxonomy;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Exception\InvalidOptionException;

beforeEach(function (): void {
    $this->syncSnapshotPath = testTempPath('taxonomy-sync').'/collection_taxonomy.csv';

    $this->app->bind(
        CollectionTaxonomySnapshot::class,
        fn (): CollectionTaxonomySnapshot => new CollectionTaxonomySnapshot($this->syncSnapshotPath),
    );

    Storage::fake(ReferenceStore::DISK);
});

afterEach(function (): void {
    if (file_exists($this->syncSnapshotPath)) {
        unlink($this->syncSnapshotPath);
        rmdir(dirname($this->syncSnapshotPath));
    }
});

/**
 * @param  array<string, int>  $entryCounts  entité => nombre d'entrées à écrire dans l'instantané
 */
function writeSyncSnapshot(string $path, array $entryCounts): void
{
    $entries = [];

    foreach ($entryCounts as $entity => $count) {
        foreach (range(1, $count) as $entryId) {
            $entries[$entity][$entryId] = new TaxonomyEntry('Classic', 'Vendeur');
        }
    }

    new CollectionTaxonomySnapshot($path)->write($entries);
}

function syncTaxonomyOutput(string ...$arguments): string
{
    Artisan::call('app:collection-taxonomy-sync', $arguments === [] ? [] : ['--entity' => $arguments[0]]);

    return Artisan::output();
}

test('it seeds the three collections in one run', function (): void {
    writeSyncSnapshot($this->syncSnapshotPath, ['mount' => 3, 'pet' => 2, 'decor' => 4]);

    $this->artisan('app:collection-taxonomy-sync')->assertSuccessful();

    expect(WowCollectionTaxonomy::query()->where('entity', CollectionEntity::Mount)->count())->toBe(3)
        ->and(WowCollectionTaxonomy::query()->where('entity', CollectionEntity::Pet)->count())->toBe(2)
        ->and(WowCollectionTaxonomy::query()->where('entity', CollectionEntity::Decor)->count())->toBe(4);
});

test('it reports how many entries each collection gained', function (): void {
    writeSyncSnapshot($this->syncSnapshotPath, ['mount' => 3, 'pet' => 2, 'decor' => 4]);

    $output = syncTaxonomyOutput();

    expect($output)->toContain('mount')
        ->and($output)->toContain('(+3)')
        ->and($output)->toContain('(+2)')
        ->and($output)->toContain('(+4)');
});

test('it reports an unchanged collection as such on a replay', function (): void {
    writeSyncSnapshot($this->syncSnapshotPath, ['mount' => 3, 'pet' => 2, 'decor' => 4]);
    syncTaxonomyOutput();

    expect(syncTaxonomyOutput())->toContain('(=)');
});

test('it adds nothing on a replay without a new entry', function (): void {
    writeSyncSnapshot($this->syncSnapshotPath, ['mount' => 3, 'pet' => 2, 'decor' => 4]);
    $this->artisan('app:collection-taxonomy-sync')->assertSuccessful();

    $this->artisan('app:collection-taxonomy-sync')->assertSuccessful();

    expect(WowCollectionTaxonomy::query()->count())->toBe(9);
});

test('it adds only the new entries of a later snapshot', function (): void {
    writeSyncSnapshot($this->syncSnapshotPath, ['mount' => 3, 'pet' => 2, 'decor' => 4]);
    $this->artisan('app:collection-taxonomy-sync')->assertSuccessful();

    writeSyncSnapshot($this->syncSnapshotPath, ['mount' => 5, 'pet' => 2, 'decor' => 4]);
    $this->artisan('app:collection-taxonomy-sync')->assertSuccessful();

    expect(WowCollectionTaxonomy::query()->where('entity', CollectionEntity::Mount)->count())->toBe(5);
});

test('it leaves a manual arbitration in place', function (): void {
    writeSyncSnapshot($this->syncSnapshotPath, ['mount' => 3, 'pet' => 2, 'decor' => 4]);
    $this->artisan('app:collection-taxonomy-sync')->assertSuccessful();

    WowCollectionTaxonomy::query()
        ->where('entity', CollectionEntity::Mount)
        ->where('entry_id', 1)
        ->update(['category' => 'Arbitré à la main', 'source' => 'Source arbitrée']);

    $this->artisan('app:collection-taxonomy-sync')->assertSuccessful();

    $wowCollectionTaxonomy = WowCollectionTaxonomy::query()
        ->where('entity', CollectionEntity::Mount)
        ->where('entry_id', 1)
        ->sole();

    expect($wowCollectionTaxonomy->category)->toBe('Arbitré à la main')
        ->and($wowCollectionTaxonomy->source)->toBe('Source arbitrée');
});

test('it synchronises a single collection when asked', function (): void {
    writeSyncSnapshot($this->syncSnapshotPath, ['mount' => 3, 'pet' => 2, 'decor' => 4]);

    $this->artisan('app:collection-taxonomy-sync', ['--entity' => 'pet'])->assertSuccessful();

    expect(WowCollectionTaxonomy::query()->where('entity', CollectionEntity::Pet)->count())->toBe(2)
        ->and(WowCollectionTaxonomy::query()->where('entity', CollectionEntity::Mount)->count())->toBe(0);
});

test('it fails on a collection it does not know, without touching the taxonomy', function (): void {
    writeSyncSnapshot($this->syncSnapshotPath, ['mount' => 3]);
    $this->artisan('app:collection-taxonomy-sync', ['--entity' => 'mount'])->assertSuccessful();

    $this->artisan('app:collection-taxonomy-sync', ['--entity' => 'toy'])->assertFailed();

    expect(WowCollectionTaxonomy::query()->count())->toBe(3);
});

test('it fails when the snapshot knows nothing of a collection', function (): void {
    writeSyncSnapshot($this->syncSnapshotPath, ['mount' => 3, 'pet' => 2]);

    $this->artisan('app:collection-taxonomy-sync')->assertFailed();
});

test('it loads nothing at all when the snapshot is incomplete', function (): void {
    writeSyncSnapshot($this->syncSnapshotPath, ['mount' => 3, 'pet' => 2]);

    $this->artisan('app:collection-taxonomy-sync')->assertFailed();

    expect(WowCollectionTaxonomy::query()->count())->toBe(0);
});

test('it fails when the snapshot is missing altogether', function (): void {
    $this->artisan('app:collection-taxonomy-sync')->assertFailed();

    expect(WowCollectionTaxonomy::query()->count())->toBe(0);
});

// --- Tirage amont ---

test('the command no longer pulls any curation from a third party', function (): void {
    $this->artisan('app:collection-taxonomy-sync', ['--upstream' => true]);
})->throws(InvalidOptionException::class);
