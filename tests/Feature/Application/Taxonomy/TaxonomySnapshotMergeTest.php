<?php

declare(strict_types=1);

use App\Application\Services\DatabaseQueryService;
use App\Application\Taxonomy\TaxonomySnapshotMerge;
use App\Infrastructure\Taxonomy\TaxonomyEntry;
use App\Models\WowCollectionTaxonomy;
use App\Models\WowDecor;
use App\Models\WowMount;

test('it loads every collection of the versioned snapshot into the base', function (): void {
    writeTestTaxonomySnapshot([
        'mount' => [6 => new TaxonomyEntry('Racial', 'Human')],
        'pet' => [9 => new TaxonomyEntry('Wild', 'Battle')],
        'decor' => [10 => new TaxonomyEntry('Housing', 'Vendor')],
    ]);

    expect(resolve(TaxonomySnapshotMerge::class)->merge('12345'))->toBe(['inserted' => 3])
        ->and(WowCollectionTaxonomy::query()->count())->toBe(3);
});

test('it never rewrites an arbitration the base already holds', function (): void {
    WowCollectionTaxonomy::query()->create(['entity' => 'mount', 'entry_id' => 6, 'category' => 'Arbitré', 'source' => 'Prod', 'obtainable' => true]);
    writeTestTaxonomySnapshot(['mount' => [6 => new TaxonomyEntry('Racial', 'Human')]]);

    expect(resolve(TaxonomySnapshotMerge::class)->merge('12345'))->toBe(['inserted' => 0])
        ->and(WowCollectionTaxonomy::query()->sole()->category)->toBe('Arbitré');
});

test('a collection missing from the snapshot does not stop the others', function (): void {
    writeTestTaxonomySnapshot(['pet' => [9 => new TaxonomyEntry('Wild', 'Battle')]]);

    expect(resolve(TaxonomySnapshotMerge::class)->merge('12345'))->toBe(['inserted' => 1]);
});

test('who reloaded the snapshot is written to the audit trail', function (): void {
    writeTestTaxonomySnapshot(['pet' => [9 => new TaxonomyEntry('Wild', 'Battle')]]);

    resolve(TaxonomySnapshotMerge::class)->merge('12345');

    expect(auditTrail())->toBe([[
        'message' => 'Collection taxonomy loaded from the versioned snapshot',
        'context' => ['inserted' => 1, 'actor' => '12345'],
    ]]);
});

test('an entry the snapshot brings ranks its catalogue row at once, without waiting for an import', function (): void {
    WowMount::query()->create(['id' => 6, 'name_fr' => 'Cheval bai', 'is_active' => true]);
    writeTestTaxonomySnapshot(['mount' => [6 => new TaxonomyEntry('Racial', 'Human')]]);

    resolve(TaxonomySnapshotMerge::class)->merge('12345');

    $wowMount = WowMount::query()->findOrFail(6);

    expect($wowMount->category)->toBe('Racial')
        ->and($wowMount->source)->toBe('Human');
});

test('an entry the snapshot marks out of reach leaves the catalogue inactive', function (): void {
    WowMount::query()->create(['id' => 6, 'name_fr' => 'Cheval bai', 'is_active' => true]);
    writeTestTaxonomySnapshot(['mount' => [6 => new TaxonomyEntry('Promotion', 'Blizzard Store', obtainable: false)]]);

    resolve(TaxonomySnapshotMerge::class)->merge('12345');

    expect(WowMount::query()->findOrFail(6)->is_active)->toBeFalse();
});

test('an arbitration already in the base keeps its catalogue row as it is', function (): void {
    WowMount::query()->create(['id' => 6, 'name_fr' => 'Cheval bai', 'category' => 'Arbitré', 'source' => 'Prod', 'is_active' => true]);
    WowCollectionTaxonomy::query()->create(['entity' => 'mount', 'entry_id' => 6, 'category' => 'Arbitré', 'source' => 'Prod', 'obtainable' => true]);
    writeTestTaxonomySnapshot(['mount' => [6 => new TaxonomyEntry('Racial', 'Human')]]);

    resolve(TaxonomySnapshotMerge::class)->merge('12345');

    expect(WowMount::query()->findOrFail(6)->category)->toBe('Arbitré');
});

test('a decor the snapshot ranks drops the suggestion it was waiting with', function (): void {
    WowDecor::query()->create(['id' => 10, 'name_fr' => 'Parchemin enroulé', 'is_active' => true, 'suggested_category' => 'Warlords of Draenor']);
    writeTestTaxonomySnapshot(['decor' => [10 => new TaxonomyEntry('Warlords of Draenor', 'Alliance Garrison Vendor')]]);

    resolve(TaxonomySnapshotMerge::class)->merge('12345');

    $wowDecor = WowDecor::query()->findOrFail(10);

    expect($wowDecor->category)->toBe('Warlords of Draenor')
        ->and($wowDecor->suggested_category)->toBeNull();
});

test('an entry the catalogue does not hold yet is only written to the taxonomy', function (): void {
    writeTestTaxonomySnapshot(['mount' => [6 => new TaxonomyEntry('Racial', 'Human')]]);

    expect(resolve(TaxonomySnapshotMerge::class)->merge('12345'))->toBe(['inserted' => 1])
        ->and(WowMount::query()->count())->toBe(0);
});

test('the database sidebar shows the ranking the snapshot brought', function (): void {
    WowMount::query()->create(['id' => 6, 'name_fr' => 'Cheval bai', 'is_active' => true]);
    $databaseQueryService = resolve(DatabaseQueryService::class);
    expect($databaseQueryService->cachedSubcategories()['mounts'])->toBe([]);
    writeTestTaxonomySnapshot(['mount' => [6 => new TaxonomyEntry('Legion', 'Class Hall')]]);

    resolve(TaxonomySnapshotMerge::class)->merge('12345');

    expect($databaseQueryService->cachedSubcategories()['mounts'])->toBe([['name' => 'Legion', 'slug' => 'legion', 'count' => 1]]);
});
