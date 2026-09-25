<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\ImportBuildGate;
use App\Models\WowImportState;

test('an entity never imported is out of date', function (): void {
    expect((new ImportBuildGate)->isUpToDate('quests', '12.1.0_68914'))->toBeFalse();
});

test('an entity imported on the same build is up to date', function (): void {
    (new ImportBuildGate)->remember('quests', '12.1.0_68914');

    expect((new ImportBuildGate)->isUpToDate('quests', '12.1.0_68914'))->toBeTrue();
});

test('an entity imported on an older build is out of date', function (): void {
    (new ImportBuildGate)->remember('quests', '12.1.0_68000');

    expect((new ImportBuildGate)->isUpToDate('quests', '12.1.0_68914'))->toBeFalse();
});

test('an unknown current build never blocks an import', function (): void {
    (new ImportBuildGate)->remember('quests', '12.1.0_68914');

    expect((new ImportBuildGate)->isUpToDate('quests', null))->toBeFalse();
});

test('the state is held per entity, not globally', function (): void {
    $gate = new ImportBuildGate;
    $gate->remember('quests', '12.1.0_68914');

    expect($gate->isUpToDate('quests', '12.1.0_68914'))->toBeTrue()
        ->and($gate->isUpToDate('recipes', '12.1.0_68914'))->toBeFalse();
});

test('remembering an entity twice keeps one row and the latest build', function (): void {
    $gate = new ImportBuildGate;
    $gate->remember('quests', '12.1.0_68000');
    $gate->remember('quests', '12.1.0_68914');

    expect(WowImportState::query()->where('entity', 'quests')->count())->toBe(1)
        ->and($gate->isUpToDate('quests', '12.1.0_68914'))->toBeTrue();
});

test('it keeps the last modified header alongside the build', function (): void {
    $gate = new ImportBuildGate;
    $gate->remember('quests', '12.1.0_68914', 'Wed, 10 Sep 2026 08:00:00 GMT');

    expect($gate->lastModifiedFor('quests'))->toBe('Wed, 10 Sep 2026 08:00:00 GMT')
        ->and($gate->lastModifiedFor('recipes'))->toBeNull();
});
