<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\MediaSearchSweep;
use App\Infrastructure\Blizzard\MediaSearchTag;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    Sleep::fake();
});

/**
 * Mocke une fenêtre de media : la doublure n'accepte que l'intervalle et le tag demandés.
 *
 * @param  list<array<string, mixed>>  $documents
 */
function mockMediaWindow(\Mockery\MockInterface $mock, MediaSearchTag $mediaSearchTag, int $window, array $documents): void
{
    $range = sprintf('id=[%d,%d]', $window * 1000, $window * 1000 + 999);

    $mock->shouldReceive('getAsync')
        ->withArgs(fn (string $requested): bool => str_starts_with($requested, 'data/wow/search/media')
            && str_contains($requested, $range)
            && str_contains($requested, 'tags='.$mediaSearchTag->value))
        ->andReturnUsing(fn (): PromiseInterface => Create::promiseFor(new Response(200, [], (string) json_encode([
            'results' => array_map(static fn (array $document): array => ['data' => $document], $documents),
        ]))));
}

test('sweeping media indexes the icons of the requested tag by media id', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockMediaWindow($client, MediaSearchTag::Achievement, 41, [
        ['id' => 41802, 'assets' => [['key' => 'icon', 'value' => 'https://render.worldofwarcraft.com/eu/icons/56/236409.jpg', 'file_data_id' => 236409]]],
        ['id' => 41803, 'assets' => []],
    ]);

    $media = resolve(MediaSearchSweep::class)->sweep([41], MediaSearchTag::Achievement);

    expect(array_keys($media))->toBe([41802, 41803])
        ->and($media[41802]->iconUrl)->toBe('https://render.worldofwarcraft.com/eu/icons/56/236409.jpg')
        ->and($media[41802]->fileDataId)->toBe(236409)
        ->and($media[41803]->iconUrl)->toBeNull();
});

test('sweeping media keeps the tags apart', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockMediaWindow($client, MediaSearchTag::Item, 0, [['id' => 10, 'assets' => []]]);
    mockMediaWindow($client, MediaSearchTag::Achievement, 0, [['id' => 20, 'assets' => []]]);

    expect(array_keys(resolve(MediaSearchSweep::class)->sweep([0], MediaSearchTag::Item)))->toBe([10])
        ->and(array_keys(resolve(MediaSearchSweep::class)->sweep([0], MediaSearchTag::Achievement)))->toBe([20]);
});

test('sweeping media skips a window the API did not answer', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockMediaWindow($client, MediaSearchTag::Achievement, 0, [['id' => 10, 'assets' => []]]);
    $client->shouldReceive('getAsync')
        ->withArgs(fn (string $requested): bool => str_contains($requested, 'id=[1000,1999]'))
        ->andReturnUsing(fn (): PromiseInterface => Create::promiseFor(new Response(500, [], '')));

    expect(array_keys(resolve(MediaSearchSweep::class)->sweep([0, 1], MediaSearchTag::Achievement)))->toBe([10]);
});

test('sweeping no window at all calls nothing', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    $client->shouldNotReceive('getAsync');

    expect(resolve(MediaSearchSweep::class)->sweep([], MediaSearchTag::Achievement))->toBe([]);
});
