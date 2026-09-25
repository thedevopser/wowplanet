<?php

declare(strict_types=1);

use App\Application\Health\QueueState;
use App\Application\Import\CurrentImport;
use App\Jobs\ComputeCrossCharacterJob;
use App\Jobs\RunImportJob;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
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
        'running' => [],
        'waiting' => [],
        'failed' => [],
    ]);
});

test('it counts pending and delayed jobs on the imports queue', function (): void {
    Queue::connection('redis')->pushRaw('{"uuid":"a"}', 'imports');
    Queue::connection('redis')->pushRaw('{"uuid":"b"}', 'imports');
    Queue::connection('redis')->laterOn('imports', 600, 'job');

    expect(resolve(QueueState::class)->snapshot())->toMatchArray(['pending' => 2, 'delayed' => 1, 'reserved' => 0]);
});

test('a job taken by the worker is listed as running since it was taken', function (): void {
    $this->travelTo(now()->setTimestamp(1_700_000_000));
    dispatch(new ComputeCrossCharacterJob('job-1', '42', [], 'secret-token', 'Thrall#1234'));
    $this->travel(30)->seconds();
    Queue::connection('redis')->pop('imports');

    $snapshot = resolve(QueueState::class)->snapshot();

    expect($snapshot['running'])->toBe([['label' => 'Calcul du score de compte', 'account' => 'Thrall#1234', 'since' => 1_700_000_030]])
        ->and($snapshot['waiting'])->toBe([]);
});

test('jobs waiting for the worker are listed in queue order since they were queued', function (): void {
    $this->travelTo(now()->setTimestamp(1_700_000_000));
    dispatch(new RunImportJob('job-1', 'app:wow-data-import'));
    $this->travel(5)->seconds();
    dispatch(new ComputeCrossCharacterJob('job-2', '42', [], 'secret-token', 'Jaina#5678'));

    expect(resolve(QueueState::class)->snapshot()['waiting'])->toBe([
        ['label' => 'Import du catalogue', 'account' => null, 'since' => 1_700_000_000],
        ['label' => 'Calcul du score de compte', 'account' => 'Jaina#5678', 'since' => 1_700_000_005],
    ]);
});

test('a job without a public label is shown under its short class name', function (): void {
    Queue::connection('redis')->pushRaw('{"uuid":"a","displayName":"App\\\\Jobs\\\\PruneHistoryJob","createdAt":1700000000}', 'imports');

    expect(resolve(QueueState::class)->snapshot()['waiting'])->toBe([['label' => 'PruneHistoryJob', 'account' => null, 'since' => 1_700_000_000]]);
});

test('an unreadable job is left out of the list with a warning, without failing the page', function (string $payload): void {
    $warnings = [];
    Event::listen(MessageLogged::class, function (MessageLogged $messageLogged) use (&$warnings): void {
        if ($messageLogged->level === 'warning') {
            $warnings[] = $messageLogged->message;
        }
    });
    Queue::connection('redis')->pushRaw($payload, 'imports');
    dispatch(new RunImportJob('job-1', 'app:wow-data-import'));

    $snapshot = resolve(QueueState::class)->snapshot();

    expect($snapshot['waiting'])->toHaveCount(1)
        ->and($snapshot['waiting'][0]['label'])->toBe('Import du catalogue')
        ->and($snapshot['pending'])->toBe(2)
        ->and($warnings)->toBe(['An unreadable job was left out of the queue listing']);
})->with([
    'not JSON' => ['{broken'],
    'not an object' => ['"a string"'],
    'no date' => ['{"displayName":"App\\\\Jobs\\\\PruneHistoryJob"}'],
    'no name' => ['{"createdAt":1700000000}'],
    'an unreadable label' => ['{"displayName":"App\\\\Jobs\\\\PruneHistoryJob","createdAt":1700000000,"described":{"label":42,"account":null}}'],
]);

test('the job data, where a player token may sit, is never read for the listing', function (): void {
    Queue::connection('redis')->pushRaw('{"displayName":"App\\\\Jobs\\\\PruneHistoryJob","createdAt":1700000000,"data":{"commandName":"Leaky","accessToken":"secret-token"}}', 'imports');
    dispatch(new ComputeCrossCharacterJob('job-1', '42', [], 'secret-token', 'Thrall#1234'));
    Queue::connection('redis')->pop('imports');

    expect(json_encode(resolve(QueueState::class)->snapshot()))->not->toContain('secret-token')->not->toContain('Leaky');
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
