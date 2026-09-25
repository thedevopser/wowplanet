<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\RenderedIconProbe;
use Illuminate\Support\Facades\Http;

function probeIcons(array $urls): array
{
    return resolve(RenderedIconProbe::class)->servedUrls($urls);
}

test('it keeps the icons the CDN serves', function (): void {
    Http::fake([
        'render.worldofwarcraft.com/eu/icons/56/1.jpg' => Http::response('', 200),
        'render.worldofwarcraft.com/eu/icons/56/2.jpg' => Http::response('', 200),
    ]);

    expect(probeIcons([
        'https://render.worldofwarcraft.com/eu/icons/56/1.jpg',
        'https://render.worldofwarcraft.com/eu/icons/56/2.jpg',
    ]))->toBe([
        'https://render.worldofwarcraft.com/eu/icons/56/1.jpg',
        'https://render.worldofwarcraft.com/eu/icons/56/2.jpg',
    ]);
});

test('it drops an icon the CDN refuses, which would show as a broken image', function (): void {
    Http::fake([
        'render.worldofwarcraft.com/eu/icons/56/1.jpg' => Http::response('', 200),
        'render.worldofwarcraft.com/eu/icons/56/2.jpg' => Http::response('', 403),
    ]);

    expect(probeIcons([
        'https://render.worldofwarcraft.com/eu/icons/56/1.jpg',
        'https://render.worldofwarcraft.com/eu/icons/56/2.jpg',
    ]))->toBe(['https://render.worldofwarcraft.com/eu/icons/56/1.jpg']);
});

test('it drops an icon the CDN does not answer for at all', function (): void {
    Http::fake(fn (): never => throw new \Illuminate\Http\Client\ConnectionException('timeout'));

    expect(probeIcons(['https://render.worldofwarcraft.com/eu/icons/56/1.jpg']))->toBe([]);
});

test('it asks the CDN for nothing when there is nothing to check', function (): void {
    Http::fake();

    expect(probeIcons([]))->toBe([]);

    Http::assertNothingSent();
});

test('it asks once for an icon several mounts share', function (): void {
    Http::fake(['render.worldofwarcraft.com/*' => Http::response('', 200)]);

    $url = 'https://render.worldofwarcraft.com/eu/icons/56/132261.jpg';

    expect(probeIcons([$url, $url, $url]))->toBe([$url]);

    Http::assertSentCount(1);
});

test('it checks more icons than one pool holds', function (): void {
    Http::fake(['render.worldofwarcraft.com/*' => Http::response('', 200)]);

    $urls = array_map(static fn (int $i): string => sprintf('https://render.worldofwarcraft.com/eu/icons/56/%d.jpg', $i), range(1, 120));

    expect(probeIcons($urls))->toHaveCount(120);

    Http::assertSentCount(120);
});
