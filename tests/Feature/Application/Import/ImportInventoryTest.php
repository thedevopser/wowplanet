<?php

declare(strict_types=1);

use App\Application\Import\ImportInventory;
use App\Application\Import\ImportStage;
use App\Models\WowImportState;
use App\Models\WowMount;
use App\Models\WowPet;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->inventory = resolve(ImportInventory::class);
});

function entryFor(array $entries, ImportStage $importStage): array
{
    return collect($entries)->firstWhere('stage', $importStage->value);
}

test('it lists the catalogue entities, and not the reference socle', function (): void {
    $entries = $this->inventory->entries();

    expect($entries)->toHaveCount(7)
        ->and(collect($entries)->pluck('stage'))->not->toContain('reference');
});

test('it names each entity as the panel shows it', function (): void {
    expect(entryFor($this->inventory->entries(), ImportStage::Mounts)['label'])->toBe('Montures');
});

test('it counts the rows an entity holds in the database', function (): void {
    WowMount::query()->insert([
        ['id' => 1, 'name_fr' => 'Cheval'],
        ['id' => 2, 'name_fr' => 'Griffon'],
    ]);

    expect(entryFor($this->inventory->entries(), ImportStage::Mounts)['rows'])->toBe(2);
});

test('an entity spread over two tables adds them up', function (): void {
    expect(entryFor($this->inventory->entries(), ImportStage::Professions))
        ->toHaveKey('rows');
});

test('an entity never imported says so rather than showing a date', function (): void {
    $entry = entryFor($this->inventory->entries(), ImportStage::Pets);

    expect($entry['imported_at'])->toBeNull()
        ->and($entry['build'])->toBeNull();
});

test('it reports the build and the date of the last successful import', function (): void {
    WowImportState::query()->create([
        'entity' => 'pets',
        'build' => '12.1.0_68914',
        'imported_at' => now()->subHours(3),
    ]);

    $entry = entryFor($this->inventory->entries(), ImportStage::Pets);

    expect($entry['build'])->toBe('12.1.0_68914')
        ->and($entry['imported_at'])->not->toBeNull();
});

test('it carries the order of magnitude of what a forced import would cost', function (): void {
    expect(entryFor($this->inventory->entries(), ImportStage::Achievements)['estimated_api_calls'])
        ->toBe(ImportStage::Achievements->estimatedApiCalls());
});

test('it reads the state of every entity in a single query rather than one each', function (): void {
    WowPet::query()->insert([['id' => 1, 'name_fr' => 'Souris']]);

    DB::enableQueryLog();
    $this->inventory->entries();
    $stateQueries = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains((string) $query['query'], 'wow_import_states'));
    DB::disableQueryLog();

    expect($stateQueries)->toHaveCount(1);
});
