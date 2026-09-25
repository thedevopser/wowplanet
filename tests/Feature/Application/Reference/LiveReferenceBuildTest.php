<?php

declare(strict_types=1);

use App\Application\Build\UpstreamSource;
use App\Application\Reference\LiveReferenceBuild;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => Cache::flush());

function fakeWagoBuild(string $build): void
{
    Http::fake([
        'wago.tools/api/builds' => Http::response(['wow' => [['product' => 'wow', 'version' => $build]]]),
    ]);
}

test('it reports the build wago serves', function (): void {
    fakeWagoBuild('12.1.0.69875');

    expect(resolve(LiveReferenceBuild::class)->current())->toBe('12.1.0.69875');
});

test('it asks wago once an hour, not once per page view', function (): void {
    fakeWagoBuild('12.1.0.69875');

    $liveReferenceBuild = resolve(LiveReferenceBuild::class);

    expect($liveReferenceBuild->current())->toBe('12.1.0.69875')
        ->and($liveReferenceBuild->current())->toBe('12.1.0.69875');

    Http::assertSentCount(1);
});

test('an unreachable wago leaves the page without a comparison, never without a page', function (): void {
    Http::fake(['wago.tools/api/builds' => Http::response(status: 503)]);

    expect(resolve(LiveReferenceBuild::class)->current())->toBeNull();
});

test('wago unreachable is a failure like any other, not a five hundred on the panel', function (): void {
    // Une panne de résolution ou un délai dépassé lève une ConnectionException avant
    // toute réponse : ce n'est pas la même exception qu'un 503, et c'est le cas réel.
    Http::fake(fn () => throw new ConnectionException('Could not resolve host: wago.tools'));

    expect(resolve(LiveReferenceBuild::class)->current())->toBeNull();
});

test('a failure is not cached, so the next view tries again', function (): void {
    Http::fake(['wago.tools/api/builds' => Http::response(status: 503)]);

    $liveReferenceBuild = resolve(LiveReferenceBuild::class);

    expect($liveReferenceBuild->current())->toBeNull()
        ->and($liveReferenceBuild->current())->toBeNull();

    Http::assertSentCount(2);
});

test('a build already read is served from the cache without touching wago', function (): void {
    Cache::put(UpstreamSource::Wago->cacheKey(), '12.1.0.69875', 60);
    Http::fake();

    expect(resolve(LiveReferenceBuild::class)->current())->toBe('12.1.0.69875');

    Http::assertNothingSent();
});
