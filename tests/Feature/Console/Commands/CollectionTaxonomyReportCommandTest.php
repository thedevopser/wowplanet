<?php

declare(strict_types=1);

use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Models\WowCollectionTaxonomy;
use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;
use Illuminate\Support\Facades\Artisan;

function reportTaxonomy(?string $entity = null, ?int $limit = null): string
{
    $options = [];
    if ($entity !== null) {
        $options['--entity'] = $entity;
    }

    if ($limit !== null) {
        $options['--limit'] = (string) $limit;
    }

    Artisan::call('app:collection-taxonomy-report', $options);

    return Artisan::output();
}

test('it lists the catalog entries no one has ranked yet', function (): void {
    WowMount::query()->create(['id' => 100, 'name_fr' => 'Monture rangée', 'is_active' => true]);
    WowMount::query()->create(['id' => 999, 'name_fr' => 'Monture à arbitrer', 'is_active' => true]);
    WowCollectionTaxonomy::factory()->create(['entity' => CollectionEntity::Mount, 'entry_id' => 100]);

    $output = reportTaxonomy();

    expect($output)->toContain('999')
        ->and($output)->toContain('Monture à arbitrer')
        ->and($output)->not->toContain('Monture rangée');
});

test('it counts the entries awaiting arbitration for each collection', function (): void {
    WowMount::query()->create(['id' => 1, 'name_fr' => 'M', 'is_active' => true]);
    WowPet::query()->create(['id' => 2, 'name_fr' => 'P', 'is_active' => true]);
    WowPet::query()->create(['id' => 3, 'name_fr' => 'P2', 'is_active' => true]);
    WowDecor::query()->create(['id' => 4, 'name_fr' => 'D', 'is_active' => true]);
    WowCollectionTaxonomy::factory()->create(['entity' => CollectionEntity::Decor, 'entry_id' => 4]);

    $output = reportTaxonomy();

    expect($output)->toMatch('/mount\s+1 à arbitrer/u')
        ->and($output)->toMatch('/pet\s+2 à arbitrer/u')
        ->and($output)->toMatch('/decor\s+Rien à arbitrer/u');
});

test('it reports a fully curated catalog as having nothing to arbitrate', function (): void {
    WowMount::query()->create(['id' => 100, 'name_fr' => 'Monture rangée', 'is_active' => true]);
    WowCollectionTaxonomy::factory()->create(['entity' => CollectionEntity::Mount, 'entry_id' => 100]);

    expect(reportTaxonomy('mount'))->toContain('Rien à arbitrer');
});

test('it does not treat an entry curated as ranked nowhere as awaiting arbitration', function (): void {
    WowMount::query()->create(['id' => 100, 'name_fr' => 'Monture sans rangement', 'is_active' => true]);
    WowCollectionTaxonomy::factory()->create([
        'entity' => CollectionEntity::Mount,
        'entry_id' => 100,
        'category' => null,
        'source' => null,
    ]);

    expect(reportTaxonomy('mount'))->toContain('Rien à arbitrer');
});

test('it reports a single collection when asked', function (): void {
    WowMount::query()->create(['id' => 1, 'name_fr' => 'Monture à arbitrer', 'is_active' => true]);
    WowPet::query()->create(['id' => 2, 'name_fr' => 'Mascotte à arbitrer', 'is_active' => true]);

    $output = reportTaxonomy('pet');

    expect($output)->toContain('Mascotte à arbitrer')
        ->and($output)->not->toContain('Monture à arbitrer');
});

test('it truncates a long list and says how many it left out', function (): void {
    foreach (range(1, 30) as $id) {
        WowMount::query()->create(['id' => $id, 'name_fr' => 'Monture '.$id, 'is_active' => true]);
    }

    $output = reportTaxonomy('mount', 5);

    expect($output)->toContain('25 autre');
});

test('it fails on a collection it does not know', function (): void {
    $this->artisan('app:collection-taxonomy-report', ['--entity' => 'toy'])->assertFailed();
});

test('it succeeds on an empty catalog', function (): void {
    $this->artisan('app:collection-taxonomy-report')->assertSuccessful();
});
