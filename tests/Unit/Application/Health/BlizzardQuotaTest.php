<?php

declare(strict_types=1);

use App\Application\Health\BlizzardQuota;
use App\Application\Health\HealthStatus;
use App\Infrastructure\Blizzard\HourlyBudgetGuard;

test('a quota well under the import ceiling is healthy', function (): void {
    $blizzardQuota = new BlizzardQuota(used: 1_000, importCeiling: 30_000);

    expect($blizzardQuota->status())->toBe(HealthStatus::Ok)
        ->and($blizzardQuota->issue())->toBeNull();
});

test('a quota close to the import ceiling is a warning', function (): void {
    $blizzardQuota = new BlizzardQuota(used: 27_000, importCeiling: 30_000);

    expect($blizzardQuota->status())->toBe(HealthStatus::Warning)
        ->and($blizzardQuota->issue())->toBe('Quota proche du plafond réservé aux imports.');
});

test('a quota at the enforced limit is critical', function (): void {
    $blizzardQuota = new BlizzardQuota(used: HourlyBudgetGuard::HOURLY_LIMIT, importCeiling: 30_000);

    expect($blizzardQuota->status())->toBe(HealthStatus::Critical)
        ->and($blizzardQuota->issue())->toBe('Quota Blizzard atteint : les appels sont suspendus.');
});

test('it exposes the consumed quota against the three ceilings', function (): void {
    expect((new BlizzardQuota(used: 1_000, importCeiling: 30_000))->toArray())->toBe([
        'status' => 'ok',
        'issue' => null,
        'used' => 1_000,
        'import_ceiling' => 30_000,
        'enforced_limit' => HourlyBudgetGuard::HOURLY_LIMIT,
        'published_quota' => HourlyBudgetGuard::PUBLISHED_HOURLY_QUOTA,
    ]);
});

test('a negative consumption cannot be read', function (): void {
    new BlizzardQuota(used: -1, importCeiling: 30_000);
})->throws(InvalidArgumentException::class);

test('an import ceiling above the enforced limit cannot be read', function (): void {
    new BlizzardQuota(used: 0, importCeiling: HourlyBudgetGuard::HOURLY_LIMIT + 1);
})->throws(InvalidArgumentException::class);
