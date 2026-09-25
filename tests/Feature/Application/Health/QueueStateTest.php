<?php

declare(strict_types=1);

use App\Application\Health\QueueState;
use App\Application\Import\CurrentImport;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    useRedisQueue();
});

test('an idle queue with no failed job is healthy', function (): void {
    expect(resolve(QueueState::class)->snapshot())->toBe([
        'status' => 'ok',
        'issue' => null,
        'queue' => 'imports',
        'pending' => 0,
        'delayed' => 0,
        'reserved' => 0,
        'current' => null,
        'failed' => [],
    ]);
});

test('it counts pending and delayed jobs on the imports queue', function (): void {
    Queue::connection('redis')->pushRaw('{"uuid":"a"}', 'imports');
    Queue::connection('redis')->pushRaw('{"uuid":"b"}', 'imports');
    Queue::connection('redis')->laterOn('imports', 600, 'job');

    expect(resolve(QueueState::class)->snapshot())->toMatchArray(['pending' => 2, 'delayed' => 1, 'reserved' => 0]);
});

test('the running import is shown as the current job', function (): void {
    resolve(CurrentImport::class)->mark('job-1', 1_700_000_000);

    expect(resolve(QueueState::class)->snapshot()['current'])->toBe(['job_id' => 'job-1', 'started_at' => 1_700_000_000]);
});

test('failed jobs waiting for a decision are flagged', function (): void {
    recordFailedJob();
    recordFailedJob();

    $snapshot = resolve(QueueState::class)->snapshot();

    expect($snapshot['status'])->toBe('warning')
        ->and($snapshot['issue'])->toBe('2 jobs échoués en attente de décision.')
        ->and($snapshot['failed'])->toHaveCount(2);
});

test('a single failed job is named in the singular', function (): void {
    recordFailedJob();

    expect(resolve(QueueState::class)->snapshot()['issue'])->toBe('1 job échoué en attente de décision.');
});

test('a queue that is not on Redis cannot be measured', function (): void {
    config(['queue.default' => 'sync']);

    resolve(QueueState::class)->snapshot();
})->throws(UnexpectedValueException::class);
