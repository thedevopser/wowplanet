<?php

declare(strict_types=1);

use App\Application\Import\ImportWait;
use App\Application\Import\ImportWaitReason;

test('a wait says why it waits and for how long', function (): void {
    $importWait = ImportWait::hourlyBudget(240);

    expect($importWait->reason)->toBe(ImportWaitReason::HourlyBudget)
        ->and($importWait->seconds)->toBe(240)
        ->and($importWait->describe())->toContain('plafond horaire')
        ->and($importWait->describe())->toContain('240');
});

test('a rate limit backoff names the recoil rather than the ceiling', function (): void {
    expect(ImportWait::rateLimitBackoff(20)->describe())
        ->toContain('429')
        ->and(ImportWait::rateLimitBackoff(20)->reason)->toBe(ImportWaitReason::RateLimitBackoff);
});

test('a batch in flight says how many requests it holds', function (): void {
    $importWait = ImportWait::batch(286);

    expect($importWait->reason)->toBe(ImportWaitReason::Batch)
        ->and($importWait->seconds)->toBe(0)
        ->and($importWait->describe())->toContain('286');
});

test('a wait survives a round trip through the tracking payload', function (): void {
    $importWait = ImportWait::hourlyBudget(240);

    expect(ImportWait::fromArray($importWait->toArray()))->toEqual($importWait);
});

test('an unknown payload carries no wait rather than a broken one', function (): void {
    expect(ImportWait::fromArray(['reason' => 'moon_phase', 'seconds' => 3, 'count' => 0]))->toBeNull();
});
