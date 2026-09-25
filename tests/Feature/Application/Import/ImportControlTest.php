<?php

declare(strict_types=1);

use App\Application\Import\ImportControl;
use App\Application\Import\ImportRequest;
use App\Application\Import\ImportSignal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

beforeEach(function (): void {
    $this->importControl = new ImportControl;
});

test('an import nobody has touched carries no request', function (): void {
    expect($this->importControl->pending('job-1'))->toBeNull();
});

test('it remembers a pause, its author and its time', function (): void {
    $this->importControl->request('job-1', ImportSignal::Pause, '12345', 1_700_000_000);

    $pending = $this->importControl->pending('job-1');

    expect($pending)->toBeInstanceOf(ImportRequest::class)
        ->and($pending->signal)->toBe(ImportSignal::Pause)
        ->and($pending->actor)->toBe('12345')
        ->and($pending->requestedAt)->toBe(1_700_000_000);
});

test('a cancellation replaces a pause, the stronger order winning', function (): void {
    $this->importControl->request('job-1', ImportSignal::Pause, '12345', 1_700_000_000);
    $this->importControl->request('job-1', ImportSignal::Cancel, '12345', 1_700_000_060);

    expect($this->importControl->pending('job-1')->signal)->toBe(ImportSignal::Cancel);
});

test('a request only reaches the import it was aimed at', function (): void {
    $this->importControl->request('job-1', ImportSignal::Pause, '12345', 1_700_000_000);

    expect($this->importControl->pending('job-2'))->toBeNull();
});

test('clearing a request lets the import carry on', function (): void {
    $this->importControl->request('job-1', ImportSignal::Pause, '12345', 1_700_000_000);

    $this->importControl->clear('job-1');

    expect($this->importControl->pending('job-1'))->toBeNull();
});

test('a request survives a cache flush, which the panel can trigger mid-run', function (): void {
    $this->importControl->request('job-1', ImportSignal::Pause, '12345', 1_700_000_000);

    Cache::flush();

    expect($this->importControl->pending('job-1'))->not->toBeNull();
});

test('a request expires on its own, so an import nobody resumed leaves no stale order behind', function (): void {
    $this->importControl->request('job-1', ImportSignal::Pause, '12345', 1_700_000_000);

    $ttl = Redis::connection('imports')->ttl('import_control:job-1');

    expect($ttl)->toBeGreaterThan(0)
        ->and($ttl)->toBeLessThanOrEqual(ImportControl::TTL_S);
});

test('a request left unreadable is read as no request, rather than stalling the import', function (): void {
    Redis::connection('imports')->set('import_control:job-1', 'ni json ni rien');

    expect($this->importControl->pending('job-1'))->toBeNull();
});
