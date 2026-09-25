<?php

declare(strict_types=1);

use App\Jobs\ComputeCrossCharacterJob;
use App\Jobs\RunImportJob;

/**
 * A queue connection that hands a job back before it has had time to finish runs
 * it twice at once. On imports that means duplicated Blizzard traffic against a
 * quota, so the invariant is worth a test rather than a comment.
 */
test('every queue connection waits longer than the slowest job before retrying', function (string $connection): void {
    $longestTimeout = max(
        (new RunImportJob('job', 'app:wow-data-import'))->timeout,
        (new ComputeCrossCharacterJob('job', 'bnet', [], 'token'))->timeout,
    );

    $retryAfter = config(sprintf('queue.connections.%s.retry_after', $connection));

    expect($retryAfter)->toBeInt()->toBeGreaterThan($longestTimeout);
})->with(['redis', 'database']);

/**
 * RedisStore::flush() issues FLUSHDB, so cache:clear wipes a whole database index.
 * Sharing one between the cache, the queue and the sessions would mean clearing a
 * cache drops the pending imports and logs everyone out.
 */
test('cache, queue and sessions each get their own redis database index', function (): void {
    $indexes = [
        config('database.redis.cache.database'),
        config('database.redis.queue.database'),
        config('database.redis.session.database'),
    ];

    expect($indexes)->each->not->toBeNull()
        ->and(array_unique($indexes))->toHaveCount(3);
});

test('the redis queue reads from its own connection rather than the default one', function (): void {
    expect(config('queue.connections.redis.connection'))->toBe('queue')
        ->and(config('cache.stores.redis.connection'))->toBe('cache');
});
