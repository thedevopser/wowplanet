<?php

declare(strict_types=1);

use App\Application\Import\ImportRequest;
use App\Application\Import\ImportSignal;

test('it carries who asked for what, and when', function (): void {
    $importRequest = new ImportRequest(ImportSignal::Pause, '12345', 1_700_000_000);

    expect($importRequest->signal)->toBe(ImportSignal::Pause)
        ->and($importRequest->actor)->toBe('12345')
        ->and($importRequest->requestedAt)->toBe(1_700_000_000);
});

test('it counts how long the request has been waiting', function (): void {
    $importRequest = new ImportRequest(ImportSignal::Pause, '12345', 1_700_000_000);

    expect($importRequest->waitedSeconds(1_700_000_090))->toBe(90);
});

test('a clock running backwards never yields a negative wait', function (): void {
    $importRequest = new ImportRequest(ImportSignal::Pause, '12345', 1_700_000_000);

    expect($importRequest->waitedSeconds(1_699_999_000))->toBe(0);
});

test('a pause is abandoned once it has been waiting longer than the delay', function (): void {
    $importRequest = new ImportRequest(ImportSignal::Pause, '12345', 1_700_000_000);

    expect($importRequest->isAbandoned(1_700_000_000 + ImportRequest::ABANDON_AFTER_S + 1))->toBeTrue();
});

test('a pause taken to read the log is not abandoned', function (): void {
    $importRequest = new ImportRequest(ImportSignal::Pause, '12345', 1_700_000_000);

    expect($importRequest->isAbandoned(1_700_000_000 + 60))->toBeFalse();
});

test('a cancellation is never abandoned, being acted on at the next boundary', function (): void {
    $importRequest = new ImportRequest(ImportSignal::Cancel, '12345', 1_700_000_000);

    expect($importRequest->isAbandoned(1_700_000_000 + ImportRequest::ABANDON_AFTER_S * 2))->toBeFalse();
});

test('it survives a round trip through its stored form', function (): void {
    $importRequest = new ImportRequest(ImportSignal::Cancel, '12345', 1_700_000_000);

    expect(ImportRequest::fromArray($importRequest->toArray()))->toEqual($importRequest);
});

test('a stored form naming an unknown signal is read as no request at all', function (): void {
    expect(ImportRequest::fromArray(['signal' => 'reboot', 'actor' => '12345', 'requested_at' => 1]))->toBeNull();
});
