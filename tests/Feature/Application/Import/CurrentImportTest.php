<?php

declare(strict_types=1);

use App\Application\Import\CurrentImport;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    $this->currentImport = new CurrentImport;
});

test('nothing runs until an import says otherwise', function (): void {
    expect($this->currentImport->jobId())->toBeNull();
});

test('it names the import that is running', function (): void {
    $this->currentImport->mark('job-1', now()->getTimestamp());

    expect($this->currentImport->jobId())->toBe('job-1');
});

test('it forgets the import once it is over', function (): void {
    $this->currentImport->mark('job-1', now()->getTimestamp());

    $this->currentImport->clear();

    expect($this->currentImport->jobId())->toBeNull();
});

test('a second import replaces the one being pointed at', function (): void {
    $this->currentImport->mark('job-1', now()->getTimestamp());
    $this->currentImport->mark('job-2', now()->getTimestamp());

    expect($this->currentImport->jobId())->toBe('job-2');
});

test('the pointer survives a cache flush, which the panel can trigger mid-run', function (): void {
    $this->currentImport->mark('job-1', now()->getTimestamp());

    Cache::flush();

    expect($this->currentImport->jobId())->toBe('job-1');
});

test('the pointer expires on its own, so a worker killed mid-import does not block the panel forever', function (): void {
    $this->currentImport->mark('job-1', now()->getTimestamp());

    $ttl = Illuminate\Support\Facades\Redis::connection('imports')->ttl('import_current');

    expect($ttl)->toBeGreaterThan(0)
        ->and($ttl)->toBeLessThanOrEqual(CurrentImport::TTL_S);
});

test('it remembers since when the running import has been running', function (): void {
    $startedAt = now()->getTimestamp();

    $this->currentImport->mark('job-1', $startedAt);

    expect($this->currentImport->startedAt())->toBe($startedAt);
});

test('nothing has been running since any time when nothing runs', function (): void {
    expect($this->currentImport->startedAt())->toBeNull();
});

test('a pointer left unreadable is read as nothing running, rather than crashing the panel', function (): void {
    Illuminate\Support\Facades\Redis::connection('imports')->set('import_current', 'ni json ni rien');

    expect($this->currentImport->jobId())->toBeNull()
        ->and($this->currentImport->startedAt())->toBeNull();
});

test('the lock is granted when nothing runs', function (): void {
    expect($this->currentImport->tryMark('job-1', now()->getTimestamp()))->toBeTrue()
        ->and($this->currentImport->jobId())->toBe('job-1');
});

test('the lock is refused to a second import, which leaves the first pointer alone', function (): void {
    $this->currentImport->tryMark('job-1', now()->getTimestamp());

    expect($this->currentImport->tryMark('job-2', now()->getTimestamp()))->toBeFalse()
        ->and($this->currentImport->jobId())->toBe('job-1');
});

test('the lock is free again once the import is over', function (): void {
    $this->currentImport->tryMark('job-1', now()->getTimestamp());
    $this->currentImport->clear();

    expect($this->currentImport->tryMark('job-2', now()->getTimestamp()))->toBeTrue();
});
