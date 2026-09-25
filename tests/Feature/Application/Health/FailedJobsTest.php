<?php

declare(strict_types=1);

use App\Application\Health\FailedJobNotFoundException;
use App\Application\Health\FailedJobNotRetryableException;
use App\Application\Health\FailedJobs;
use App\Application\Import\CurrentImport;
use App\Application\Import\ImportAlreadyRunningException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    useRedisQueue();
});

test('it lists failed jobs with their class and the first line of their exception', function (): void {
    $uuid = recordFailedJob(\App\Jobs\RunImportJob::class, 'the quota is exhausted');

    $failed = resolve(FailedJobs::class)->all();

    expect($failed)->toHaveCount(1)
        ->and($failed[0])->toMatchArray([
            'uuid' => $uuid,
            'queue' => 'imports',
            'job' => \App\Jobs\RunImportJob::class,
        ])
        ->and($failed[0]['exception'])->toStartWith('RuntimeException: the quota is exhausted in ')
        ->and($failed[0]['exception'])->not->toContain("\n")
        ->and($failed[0]['failed_at'])->toBeString();
});

test('failed jobs are listed most recent first', function (): void {
    $newer = recordFailedJob();
    $older = recordFailedJob();

    DB::table('failed_jobs')->where('uuid', $older)->update(['failed_at' => now()->subHour()]);

    expect(array_column(resolve(FailedJobs::class)->all(), 'uuid'))->toBe([$newer, $older]);
});

test('a payload without a job name is listed under an explicit placeholder', function (): void {
    $uuid = recordFailedJob();
    DB::table('failed_jobs')->where('uuid', $uuid)->update(['payload' => 'not json']);

    expect(resolve(FailedJobs::class)->all()[0]['job'])->toBe('Job illisible');
});

test('retrying a failed job pushes it back onto its queue instead of running it', function (): void {
    $uuid = recordFailedJob();

    resolve(FailedJobs::class)->retry($uuid, 'admin-1');

    expect(Queue::connection('redis')->size('imports'))->toBe(1)
        ->and(resolve(FailedJobs::class)->all())->toBe([]);
});

test('a job whose payload can no longer be read is refused and kept', function (): void {
    $uuid = recordFailedJob();
    DB::table('failed_jobs')->where('uuid', $uuid)->update(['payload' => json_encode([
        'uuid' => $uuid,
        'displayName' => 'App\\Jobs\\RenamedJob',
        'data' => ['command' => 'neither serialized nor encrypted'],
    ])]);

    expect(fn () => resolve(FailedJobs::class)->retry($uuid, 'admin-1'))
        ->toThrow(FailedJobNotRetryableException::class, 'Le job échoué '.$uuid.' ne peut pas être relancé')
        ->and(Queue::connection('redis')->size('imports'))->toBe(0)
        ->and(resolve(FailedJobs::class)->all())->toHaveCount(1);
});

test('a retry is refused while an import runs', function (): void {
    $uuid = recordFailedJob();
    resolve(CurrentImport::class)->mark('job-running', now()->getTimestamp());

    expect(fn () => resolve(FailedJobs::class)->retry($uuid, 'admin-1'))->toThrow(ImportAlreadyRunningException::class)
        ->and(Queue::connection('redis')->size('imports'))->toBe(0)
        ->and(resolve(FailedJobs::class)->all())->toHaveCount(1);
});

test('forgetting a failed job removes it without running it', function (): void {
    $uuid = recordFailedJob();

    resolve(FailedJobs::class)->forget($uuid, 'admin-1');

    expect(resolve(FailedJobs::class)->all())->toBe([])
        ->and(Queue::connection('redis')->size('imports'))->toBe(0);
});

test('an unknown failed job can be neither retried nor forgotten', function (string $action): void {
    resolve(FailedJobs::class)->{$action}('00000000-0000-0000-0000-000000000000', 'admin-1');
})->with(['retry', 'forget'])->throws(FailedJobNotFoundException::class);

test('retrying and forgetting are recorded with who did it', function (): void {
    $retried = recordFailedJob();
    $forgotten = recordFailedJob();

    resolve(FailedJobs::class)->retry($retried, 'admin-1');
    resolve(FailedJobs::class)->forget($forgotten, 'admin-2');

    expect(auditTrail())->toBe([
        ['message' => 'Failed job retried from the admin panel', 'context' => ['uuid' => $retried, 'actor' => 'admin-1']],
        ['message' => 'Failed job forgotten from the admin panel', 'context' => ['uuid' => $forgotten, 'actor' => 'admin-2']],
    ]);
});
