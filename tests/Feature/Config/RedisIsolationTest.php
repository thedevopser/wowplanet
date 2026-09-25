<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Redis;

/**
 * Parallel test processes share the four Redis test indexes: each one works under its own
 * key prefix, and only ever clears its own keys.
 */
test('every Redis connection of the suite works under the prefix of its process', function (string $connection): void {
    expect(Redis::connection($connection)->client()->getOption(\Redis::OPT_PREFIX))->toBe(testRedisPrefix());
})->with(['budget', 'imports', 'queue', 'cache']);

test('the prefix names the test process', function (): void {
    expect(testRedisPrefix())->toBe('wowplanet-test-'.(getenv('TEST_TOKEN') ?: '0').':');
});

test('clearing the test keys of a process spares those of the other processes', function (): void {
    $connection = Redis::connection('budget');
    $connection->set('own', '1');
    $connection->eval("return redis.call('set', ARGV[1], '1')", 0, 'wowplanet-test-elsewhere:foreign');

    clearTestRedis('budget');

    expect($connection->exists('own'))->toBe(0)
        ->and($connection->eval("return redis.call('exists', ARGV[1])", 0, 'wowplanet-test-elsewhere:foreign'))->toBe(1);

    $connection->eval("return redis.call('del', ARGV[1])", 0, 'wowplanet-test-elsewhere:foreign');
});
