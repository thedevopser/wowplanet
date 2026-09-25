<?php

declare(strict_types=1);

use App\Application\Import\ImportStage;
use App\Application\Import\RowTallyCounter;
use App\Models\WowMount;
use App\Models\WowProfession;
use App\Models\WowRecipe;
use Illuminate\Support\Facades\Date;

afterEach(function (): void {
    Date::setTestNow();
});

function upsertMounts(int ...$ids): void
{
    WowMount::query()->upsert(
        array_map(static fn (int $id): array => ['id' => $id, 'name_fr' => 'Monture '.$id, 'is_active' => true], $ids),
        uniqueBy: ['id'],
        update: ['name_fr', 'is_active'],
    );
}

test('it counts the rows of every table of a stage', function (): void {
    upsertMounts(1, 2, 3);

    WowProfession::query()->insert(['id' => 1, 'name_fr' => 'Alchimie', 'type' => 'primary', 'is_active' => true]);
    WowRecipe::query()->insert([
        ['id' => 1, 'profession_id' => 1, 'name_fr' => 'Fiole', 'expansion_id' => 0, 'is_active' => true],
        ['id' => 2, 'profession_id' => 1, 'name_fr' => 'Flacon', 'expansion_id' => 0, 'is_active' => true],
    ]);

    $counter = new RowTallyCounter;

    expect($counter->count(ImportStage::Mounts->tables()))->toBe(3)
        ->and($counter->count(ImportStage::Professions->tables()))->toBe(3);
});

test('a stage that counts no table counts no row', function (): void {
    expect((new RowTallyCounter)->count(ImportStage::Reference->tables()))->toBe(0);
});

test('it tells rows created, updated and deleted apart', function (): void {
    Date::setTestNow('2026-09-13 10:00:00');
    upsertMounts(1, 2, 3);

    $counter = new RowTallyCounter;
    $tables = ImportStage::Mounts->tables();
    $rowsBefore = $counter->count($tables);

    Date::setTestNow('2026-09-13 10:05:00');
    $startedAt = now();

    upsertMounts(4);
    WowMount::query()->whereKey(2)->update(['name_fr' => 'Renommée', 'updated_at' => now()]);
    WowMount::query()->whereKey(3)->delete();

    $tally = $counter->tally($tables, $startedAt, $rowsBefore);

    expect($tally->created)->toBe(1)
        ->and($tally->updated)->toBe(1)
        ->and($tally->deleted)->toBe(1);
});

test('a pass that rewrites nothing tallies nothing', function (): void {
    Date::setTestNow('2026-09-13 10:00:00');
    upsertMounts(1, 2);

    $counter = new RowTallyCounter;
    $tables = ImportStage::Mounts->tables();
    $rowsBefore = $counter->count($tables);

    Date::setTestNow('2026-09-13 10:05:00');

    expect($counter->tally($tables, now(), $rowsBefore)->isEmpty())->toBeTrue();
});

test('the tally spans every table of the stage', function (): void {
    Date::setTestNow('2026-09-13 10:00:00');
    WowProfession::query()->insert(['id' => 1, 'name_fr' => 'Alchimie', 'type' => 'primary', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

    $counter = new RowTallyCounter;
    $tables = ImportStage::Professions->tables();
    $rowsBefore = $counter->count($tables);

    Date::setTestNow('2026-09-13 10:05:00');
    $startedAt = now();

    WowRecipe::query()->insert(['id' => 1, 'profession_id' => 1, 'name_fr' => 'Fiole', 'expansion_id' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

    $tally = $counter->tally($tables, $startedAt, $rowsBefore);

    expect($tally->created)->toBe(1)
        ->and($tally->updated)->toBe(0);
});
