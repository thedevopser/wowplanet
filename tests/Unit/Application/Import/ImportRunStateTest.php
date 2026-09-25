<?php

declare(strict_types=1);

use App\Application\Import\ImportRunState;

test('a run that has stopped for good is terminal', function (ImportRunState $importRunState): void {
    expect($importRunState->isTerminal())->toBeTrue();
})->with([
    ImportRunState::Completed,
    ImportRunState::Failed,
    ImportRunState::Cancelled,
]);

test('a run that can still move is not terminal', function (ImportRunState $importRunState): void {
    expect($importRunState->isTerminal())->toBeFalse();
})->with([
    ImportRunState::Pending,
    ImportRunState::Running,
    ImportRunState::Paused,
]);

test('an interruption is a state an administrator asked for', function (ImportRunState $importRunState): void {
    expect($importRunState->isInterruption())->toBeTrue();
})->with([
    ImportRunState::Paused,
    ImportRunState::Cancelled,
]);

test('a state the stages reached on their own is no interruption', function (ImportRunState $importRunState): void {
    expect($importRunState->isInterruption())->toBeFalse();
})->with([
    ImportRunState::Pending,
    ImportRunState::Running,
    ImportRunState::Completed,
    ImportRunState::Failed,
]);
