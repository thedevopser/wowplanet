<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\HourlyBudgetGuard;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Redis;

beforeEach(function (): void {
    Date::setTestNow('2026-07-19 10:00:00');
});

afterEach(function (): void {
    Date::setTestNow();
});

test('it allows requests when under the hourly limit', function (): void {
    $guard = new HourlyBudgetGuard;

    expect($guard->secondsUntilAvailable(1000))->toBe(0);
});

test('it tracks consumed requests and blocks when the limit would be exceeded', function (): void {
    $guard = new HourlyBudgetGuard;

    $guard->consume(HourlyBudgetGuard::HOURLY_LIMIT);

    expect($guard->secondsUntilAvailable(1))->toBeGreaterThan(0);
});

test('it stays available while cumulative usage remains within the limit', function (): void {
    $guard = new HourlyBudgetGuard;

    $guard->consume(HourlyBudgetGuard::HOURLY_LIMIT - 500);

    expect($guard->secondsUntilAvailable(500))->toBe(0)
        ->and($guard->secondsUntilAvailable(501))->toBeGreaterThan(0);
});

test('it forgets usage older than one hour', function (): void {
    $guard = new HourlyBudgetGuard;

    $guard->consume(HourlyBudgetGuard::HOURLY_LIMIT);

    expect($guard->secondsUntilAvailable(1))->toBeGreaterThan(0);

    Date::setTestNow('2026-07-19 11:01:00');

    expect($guard->secondsUntilAvailable(1))->toBe(0);
});

test('secondsUntilAvailable honours a lower ceiling than HOURLY_LIMIT', function (): void {
    $guard = new HourlyBudgetGuard;

    $guard->consume(29_500);

    expect($guard->secondsUntilAvailable(400, 30_000))->toBe(0)
        ->and($guard->secondsUntilAvailable(600, 30_000))->toBeGreaterThan(0)
        ->and($guard->secondsUntilAvailable(600))->toBe(0);
});

test('it computes the wait as the delay until the oldest bucket leaves the window', function (): void {
    $guard = new HourlyBudgetGuard;

    $guard->consume(HourlyBudgetGuard::HOURLY_LIMIT);

    expect($guard->secondsUntilAvailable(1))->toBeGreaterThan(3500)->toBeLessThanOrEqual(3660);
});

test('it reports what the sliding window holds', function (): void {
    $guard = new HourlyBudgetGuard;

    expect($guard->usedInWindow())->toBe(0);

    $guard->consume(7);

    expect($guard->usedInWindow())->toBe(7);
});

test('consuming a batch costs one operation and counts as the whole batch', function (): void {
    $guard = new HourlyBudgetGuard;

    $guard->consume(500);

    expect($guard->usedInWindow())->toBe(500);
});

test('consuming a batch and consuming one by one reach the same count', function (): void {
    $batched = new HourlyBudgetGuard;
    $batched->consume(50);

    $batchedTotal = $batched->usedInWindow();

    clearTestRedis('budget');

    $oneByOne = new HourlyBudgetGuard;
    for ($i = 0; $i < 50; $i++) {
        $oneByOne->consume(1);
    }

    expect($oneByOne->usedInWindow())->toBe($batchedTotal);
});

test('usage spread over several minutes adds up across the window', function (): void {
    $guard = new HourlyBudgetGuard;

    $guard->consume(10);
    Date::setTestNow('2026-07-19 10:30:00');
    $guard->consume(20);
    Date::setTestNow('2026-07-19 10:59:00');

    expect($guard->usedInWindow())->toBe(30);
});

test('a minute bucket carries an expiry so nothing has to purge it', function (): void {
    $guard = new HourlyBudgetGuard;

    $guard->consume(1);

    $connection = Redis::connection('budget');

    $expectedKey = 'blizzard_budget:'.intdiv(now()->getTimestamp(), 60);

    expect($connection->keys('*'))->toHaveCount(2)
        ->and($connection->ttl($expectedKey))->toBeGreaterThan(3600)->toBeLessThanOrEqual(3660);
});

test('the running total survives the minutes leaving the sliding window', function (): void {
    $guard = new HourlyBudgetGuard;

    $guard->consume(10);

    Date::setTestNow('2026-07-19 12:00:00');

    $guard->consume(5);

    expect($guard->usedInWindow())->toBe(5)
        ->and($guard->totalConsumed())->toBe(15);
});

test('the running total starts at zero and counts batches as a whole', function (): void {
    $guard = new HourlyBudgetGuard;

    expect($guard->totalConsumed())->toBe(0);

    $guard->consume(500);

    expect($guard->totalConsumed())->toBe(500);
});

test('the running total never expires, so a step can be measured across an hour', function (): void {
    $guard = new HourlyBudgetGuard;

    $guard->consume(1);

    expect(Redis::connection('budget')->ttl('blizzard_budget:total'))->toBe(-1);
});

test('two concurrent processes never overwrite each others count', function (): void {
    $perProcess = 300;

    $childPid = pcntl_fork();
    expect($childPid)->not->toBe(-1);

    if ($childPid === 0) {
        // Le socket Redis est hérité du parent : sans réouverture, les deux processus
        // se marchent dessus sur le même descripteur.
        Redis::purge('budget');
        $childGuard = new HourlyBudgetGuard;
        for ($i = 0; $i < $perProcess; $i++) {
            $childGuard->consume(1);
        }

        posix_kill(posix_getpid(), SIGKILL);
    }

    Redis::purge('budget');
    $guard = new HourlyBudgetGuard;
    for ($i = 0; $i < $perProcess; $i++) {
        $guard->consume(1);
    }

    pcntl_waitpid($childPid, $status);

    expect($guard->usedInWindow())->toBe($perProcess * 2);
});
