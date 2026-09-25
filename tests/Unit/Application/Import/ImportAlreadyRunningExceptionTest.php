<?php

declare(strict_types=1);

use App\Application\Import\ImportAlreadyRunningException;

test('it carries which import runs and since when', function (): void {
    $importAlreadyRunningException = ImportAlreadyRunningException::since('job-1', 1_000, 1_042);

    expect($importAlreadyRunningException->jobId)->toBe('job-1')
        ->and($importAlreadyRunningException->startedAt)->toBe(1_000);
});

test('an import running for less than a minute is counted in seconds', function (): void {
    expect(ImportAlreadyRunningException::since('job-1', 1_000, 1_042)->getMessage())
        ->toBe('Un import est déjà en cours depuis 42 s.');
});

test('an import running for more than a minute is counted in minutes', function (): void {
    expect(ImportAlreadyRunningException::since('job-1', 0, 252)->getMessage())
        ->toBe('Un import est déjà en cours depuis 4 min 12 s.');
});

test('an import running for hours is counted in hours', function (): void {
    expect(ImportAlreadyRunningException::since('job-1', 0, 7_925)->getMessage())
        ->toBe('Un import est déjà en cours depuis 2 h 12 min.');
});

test('a clock that went backwards does not produce a negative duration', function (): void {
    expect(ImportAlreadyRunningException::since('job-1', 1_000, 900)->getMessage())
        ->toBe('Un import est déjà en cours depuis 0 s.');
});
