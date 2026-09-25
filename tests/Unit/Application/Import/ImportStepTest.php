<?php

declare(strict_types=1);

use App\Application\Import\ImportStage;
use App\Application\Import\ImportStep;
use App\Application\Import\ImportStepStatus;
use App\Application\Import\RowTally;

test('a step starts pending, with nothing done', function (): void {
    $importStep = ImportStep::pending(ImportStage::Quests);

    expect($importStep->status)->toBe(ImportStepStatus::Pending)
        ->and($importStep->rows->isEmpty())->toBeTrue()
        ->and($importStep->apiCalls)->toBe(0)
        ->and($importStep->fraction())->toBe(0.0)
        ->and($importStep->isTerminal())->toBeFalse();
});

test('a resumable step reports the fraction of its windows swept', function (): void {
    $importStep = ImportStep::pending(ImportStage::Appearances)
        ->advanced(RowTally::none(), apiCalls: 120, durationMs: 4000, offset: 143, total: 572);

    expect($importStep->status)->toBe(ImportStepStatus::Running)
        ->and($importStep->fraction())->toBe(0.25)
        ->and($importStep->isTerminal())->toBeFalse();
});

test('the passes of a resumable step add up', function (): void {
    $importStep = ImportStep::pending(ImportStage::Appearances)
        ->advanced(new RowTally(3, 1, 0), apiCalls: 120, durationMs: 4000, offset: 143, total: 572)
        ->advanced(new RowTally(2, 4, 1), apiCalls: 80, durationMs: 1000, offset: 286, total: 572);

    expect($importStep->rows->created)->toBe(5)
        ->and($importStep->rows->updated)->toBe(5)
        ->and($importStep->rows->deleted)->toBe(1)
        ->and($importStep->apiCalls)->toBe(200)
        ->and($importStep->durationMs)->toBe(5000);
});

test('a finished step is whole, whatever its windows said', function (): void {
    $importStep = ImportStep::pending(ImportStage::Mounts)
        ->finished(new RowTally(1, 2, 0), apiCalls: 5, durationMs: 1100);

    expect($importStep->status)->toBe(ImportStepStatus::Completed)
        ->and($importStep->fraction())->toBe(1.0)
        ->and($importStep->isTerminal())->toBeTrue();
});

test('a failed step carries the reason it failed', function (): void {
    $importStep = ImportStep::pending(ImportStage::Pets)->failed('mount index unavailable', durationMs: 300);

    expect($importStep->status)->toBe(ImportStepStatus::Failed)
        ->and($importStep->error)->toBe('mount index unavailable')
        ->and($importStep->isTerminal())->toBeTrue()
        ->and($importStep->fraction())->toBe(1.0);
});

test('a step skipped by the build gate is terminal without being a failure', function (): void {
    $importStep = ImportStep::pending(ImportStage::Decor)->skipped();

    expect($importStep->status)->toBe(ImportStepStatus::Skipped)
        ->and($importStep->isTerminal())->toBeTrue()
        ->and($importStep->error)->toBeNull();
});

test('a step survives a round trip through the tracking payload', function (): void {
    $importStep = ImportStep::pending(ImportStage::Appearances)
        ->advanced(new RowTally(3, 1, 0), apiCalls: 120, durationMs: 4000, offset: 143, total: 572);

    expect(ImportStep::fromArray($importStep->toArray()))->toEqual($importStep);
});

test('a failed step survives the round trip with its reason', function (): void {
    $importStep = ImportStep::pending(ImportStage::Quests)->failed('boom', durationMs: 12);

    expect(ImportStep::fromArray($importStep->toArray()))->toEqual($importStep);
});

test('a step is marked running before it has anything to report', function (): void {
    $importStep = ImportStep::pending(ImportStage::Quests)->started();

    expect($importStep->status)->toBe(ImportStepStatus::Running)
        ->and($importStep->rows->isEmpty())->toBeTrue()
        ->and($importStep->offset)->toBe(0);
});

test('starting a resumable step again keeps the offset it had', function (): void {
    $importStep = ImportStep::pending(ImportStage::Appearances)
        ->advanced(RowTally::none(), 1, 10, offset: 143, total: 572)
        ->started();

    expect($importStep->offset)->toBe(143)
        ->and($importStep->total)->toBe(572);
});
