<?php

declare(strict_types=1);

use App\Application\Build\UpstreamBuildProbe;
use App\Application\Build\UpstreamSource;
use App\Models\WowUpstreamBuild;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'services.blizzard.client_id' => 'test-client-id',
        'services.blizzard.client_secret' => 'test-client-secret',
        'services.blizzard.region' => 'eu',
    ]);
    Cache::flush();
});

function probe(): UpstreamBuildProbe
{
    return resolve(UpstreamBuildProbe::class);
}

test('it reports the build the Blizzard API serves', function (): void {
    stubBlizzardBuild('12.1.0_68914');

    $upstreamBuild = probe()->current(UpstreamSource::Blizzard);

    expect($upstreamBuild->build)->toBe('12.1.0_68914')
        ->and($upstreamBuild->reachable)->toBeTrue()
        ->and($upstreamBuild->source)->toBe(UpstreamSource::Blizzard);
});

test('it reports the build wago serves', function (): void {
    stubWagoBuild('12.1.0.69875');

    $upstreamBuild = probe()->current(UpstreamSource::Wago);

    expect($upstreamBuild->build)->toBe('12.1.0.69875')
        ->and($upstreamBuild->reachable)->toBeTrue();
});

test('a build already read is served from the cache without touching the upstream', function (): void {
    Cache::put(UpstreamSource::Wago->cacheKey(), '12.1.0.69875', 3600);
    $mockHandler = stubBlizzardBuild('12.1.0_68914');
    Cache::put(UpstreamSource::Blizzard->cacheKey(), '12.1.0_68914', 3600);
    Http::fake();

    expect(probe()->current(UpstreamSource::Wago)->build)->toBe('12.1.0.69875')
        ->and(probe()->current(UpstreamSource::Blizzard)->build)->toBe('12.1.0_68914')
        ->and($mockHandler->count())->toBe(5);

    Http::assertNothingSent();
});

test('it writes down what it read, so a cache:clear does not erase what is known', function (): void {
    \Illuminate\Support\Facades\Date::setTestNow('2026-09-21 09:12:04');
    stubWagoBuild('12.1.0.69875');

    probe()->current(UpstreamSource::Wago);

    $known = WowUpstreamBuild::query()->find('wago');

    expect($known)->not->toBeNull()
        ->and($known->build)->toBe('12.1.0.69875')
        ->and($known->outcome)->toBe('ok')
        ->and($known->checked_at->toDateTimeString())->toBe('2026-09-21 09:12:04');

    \Illuminate\Support\Facades\Date::setTestNow();
});

test('an unreachable upstream yields the last known build with the date it was read', function (): void {
    WowUpstreamBuild::query()->create([
        'source' => 'blizzard',
        'build' => '12.1.0_68914',
        'checked_at' => '2026-09-19 17:41:00',
        'outcome' => 'ok',
    ]);
    stubBlizzardBuild(null);

    $upstreamBuild = probe()->current(UpstreamSource::Blizzard);

    expect($upstreamBuild->build)->toBe('12.1.0_68914')
        ->and($upstreamBuild->checkedAt?->toDateTimeString())->toBe('2026-09-19 17:41:00');
});

test('an unreachable upstream never passes for a fresh reading', function (): void {
    WowUpstreamBuild::query()->create([
        'source' => 'wago',
        'build' => '12.1.0.69875',
        'checked_at' => '2026-09-19 17:41:00',
        'outcome' => 'ok',
    ]);
    stubWagoBuild(null);

    expect(probe()->current(UpstreamSource::Wago)->reachable)->toBeFalse()
        ->and(WowUpstreamBuild::query()->find('wago')->outcome)->toBe('unreachable');
});

test('a failing check leaves the last known build untouched', function (): void {
    WowUpstreamBuild::query()->create([
        'source' => 'wago',
        'build' => '12.1.0.69875',
        'checked_at' => '2026-09-19 17:41:00',
        'outcome' => 'ok',
    ]);
    stubWagoBuild(null);

    probe()->current(UpstreamSource::Wago);

    $known = WowUpstreamBuild::query()->find('wago');

    expect($known->build)->toBe('12.1.0.69875')
        ->and($known->checked_at->toDateTimeString())->toBe('2026-09-19 17:41:00');
});

test('an upstream never reached yields no build rather than a stale guess', function (): void {
    stubWagoBuild(null);

    $upstreamBuild = probe()->current(UpstreamSource::Wago);

    expect($upstreamBuild->build)->toBeNull()
        ->and($upstreamBuild->checkedAt)->toBeNull()
        ->and($upstreamBuild->reachable)->toBeFalse();
});

test('a failure is not cached, so the next check tries again', function (): void {
    stubWagoBuild(null);

    probe()->current(UpstreamSource::Wago);
    probe()->current(UpstreamSource::Wago);

    expect(Cache::get(UpstreamSource::Wago->cacheKey()))->toBeNull();

    Http::assertSentCount(2);
});

test('a forced check goes out although the cache is still warm', function (): void {
    Cache::put(UpstreamSource::Wago->cacheKey(), '12.1.0.69587', 3600);
    stubWagoBuild('12.1.0.69875');

    expect(probe()->current(UpstreamSource::Wago, force: true)->build)->toBe('12.1.0.69875');

    Http::assertSentCount(1);
});
