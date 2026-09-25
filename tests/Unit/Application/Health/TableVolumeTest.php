<?php

declare(strict_types=1);

use App\Application\Health\HealthStatus;
use App\Application\Health\TableVolume;

test('a populated catalogue table is healthy', function (): void {
    $tableVolume = TableVolume::catalogue('wow_mounts', rows: 10, active: 8, withoutIcon: 2);

    expect($tableVolume->status())->toBe(HealthStatus::Ok)
        ->and($tableVolume->issue())->toBeNull();
});

test('an empty catalogue table is critical', function (): void {
    $tableVolume = TableVolume::catalogue('wow_mounts', rows: 0, active: 0, withoutIcon: 0);

    expect($tableVolume->status())->toBe(HealthStatus::Critical)
        ->and($tableVolume->issue())->toBe('Table du catalogue vide.');
});

test('an empty application table is not an anomaly', function (): void {
    expect(TableVolume::application('character_tasks', rows: 0)->status())->toBe(HealthStatus::Ok);
});

test('a catalogue table exposes its active and iconless rows', function (): void {
    expect(TableVolume::catalogue('wow_mounts', rows: 10, active: 8, withoutIcon: 2)->toArray())->toBe([
        'table' => 'wow_mounts',
        'family' => 'catalogue',
        'rows' => 10,
        'active' => 8,
        'without_icon' => 2,
        'status' => 'ok',
        'issue' => null,
    ]);
});

test('a catalogue table without an icon column leaves the iconless count out', function (): void {
    expect(TableVolume::catalogue('wow_quests', rows: 10, active: 10, withoutIcon: null)->toArray()['without_icon'])->toBeNull();
});

test('an application table only exposes its rows', function (): void {
    expect(TableVolume::application('users', rows: 3)->toArray())->toBe([
        'table' => 'users',
        'family' => 'application',
        'rows' => 3,
        'active' => null,
        'without_icon' => null,
        'status' => 'ok',
        'issue' => null,
    ]);
});

test('more active rows than rows cannot be counted', function (): void {
    TableVolume::catalogue('wow_mounts', rows: 1, active: 2, withoutIcon: 0);
})->throws(InvalidArgumentException::class);

test('more iconless rows than rows cannot be counted', function (): void {
    TableVolume::catalogue('wow_mounts', rows: 1, active: 1, withoutIcon: 2);
})->throws(InvalidArgumentException::class);

test('a negative row count cannot be counted', function (): void {
    TableVolume::application('users', rows: -1);
})->throws(InvalidArgumentException::class);
