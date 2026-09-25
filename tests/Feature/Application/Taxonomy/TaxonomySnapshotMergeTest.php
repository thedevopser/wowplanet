<?php

declare(strict_types=1);

use App\Application\Taxonomy\TaxonomySnapshotMerge;
use App\Infrastructure\Taxonomy\TaxonomyEntry;
use App\Models\WowCollectionTaxonomy;

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
