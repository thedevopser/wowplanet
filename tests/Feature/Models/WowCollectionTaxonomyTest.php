<?php

declare(strict_types=1);

use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Models\WowCollectionTaxonomy;
use Illuminate\Support\Facades\Schema;

test('it carries the discriminator, the Blizzard identifier and the curated ranking', function (): void {
    expect(Schema::hasColumns('wow_collection_taxonomy', ['entity', 'entry_id', 'category', 'source', 'obtainable']))->toBeTrue();
});

test('it holds no column beyond those five', function (): void {
    expect(Schema::getColumnListing('wow_collection_taxonomy'))
        ->toEqualCanonicalizing(['entity', 'entry_id', 'category', 'source', 'obtainable']);
});

test('it lets two collections curate the same Blizzard identifier', function (): void {
    WowCollectionTaxonomy::factory()->create(['entity' => CollectionEntity::Mount, 'entry_id' => 42]);
    WowCollectionTaxonomy::factory()->create(['entity' => CollectionEntity::Pet, 'entry_id' => 42]);

    expect(WowCollectionTaxonomy::query()->where('entry_id', 42)->count())->toBe(2);
});

test('it refuses a second row for the same collection and identifier', function (): void {
    WowCollectionTaxonomy::factory()->create(['entity' => CollectionEntity::Mount, 'entry_id' => 42]);

    expect(fn (): WowCollectionTaxonomy => WowCollectionTaxonomy::factory()->create([
        'entity' => CollectionEntity::Mount,
        'entry_id' => 42,
    ]))->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});

test('it reads back its discriminator as an entity', function (): void {
    WowCollectionTaxonomy::factory()->create(['entity' => CollectionEntity::Decor, 'entry_id' => 7]);

    $wowCollectionTaxonomy = WowCollectionTaxonomy::query()->where('entry_id', 7)->sole();

    expect($wowCollectionTaxonomy->entity)->toBe(CollectionEntity::Decor);
});

test('it accepts a curated entry ranked nowhere, which is not the same as an absent entry', function (): void {
    WowCollectionTaxonomy::factory()->create([
        'entity' => CollectionEntity::Pet,
        'entry_id' => 9,
        'category' => null,
        'source' => null,
    ]);

    $wowCollectionTaxonomy = WowCollectionTaxonomy::query()->where('entry_id', 9)->sole();

    expect($wowCollectionTaxonomy->category)->toBeNull()
        ->and($wowCollectionTaxonomy->source)->toBeNull();
});
