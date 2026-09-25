<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\ItemSearchSweep;
use App\Infrastructure\Blizzard\Responses\ItemSearchDocument;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    Sleep::fake();
});

/**
 * Mocke une fenêtre de recherche : la réponse ne répond qu'à l'intervalle demandé.
 *
 * @param  list<array<string, mixed>>  $documents
 */
function mockSearchWindow(\Mockery\MockInterface $mock, string $endpoint, int $window, array $documents): void
{
    $range = sprintf('id=[%d,%d]', $window * 1000, $window * 1000 + 999);

    $mock->shouldReceive('getAsync')
        ->withArgs(fn (string $requested): bool => str_starts_with($requested, $endpoint) && str_contains($requested, $range))
        ->andReturnUsing(fn (): \GuzzleHttp\Promise\PromiseInterface => Create::promiseFor(new Response(200, [], (string) json_encode([
            'results' => array_map(static fn (array $document): array => ['data' => $document], $documents),
        ]))));
}

test('the highest item id is read from a descending search', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    $client->shouldReceive('get')
        ->withArgs(fn (string $endpoint): bool => str_contains($endpoint, 'data/wow/search/item') && str_contains($endpoint, 'orderby=id:desc'))
        ->andReturn(['results' => [['data' => ['id' => 285062]]]]);

    expect(resolve(ItemSearchSweep::class)->highestItemId())->toBe(285062);
});

test('an unreadable descending search carries no highest item id', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    $client->shouldReceive('get')->andThrow(new \Exception('API error: 500 Internal Server Error'));

    expect(resolve(ItemSearchSweep::class)->highestItemId())->toBeNull();
});

test('an empty descending search carries no highest item id', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    $client->shouldReceive('get')->andReturn(['results' => []]);

    expect(resolve(ItemSearchSweep::class)->highestItemId())->toBeNull();
});

test('the window count covers the whole catalog of item ids', function (): void {
    expect(ItemSearchSweep::windowCountFor(285062))->toBe(286)
        ->and(ItemSearchSweep::windowCountFor(999))->toBe(1)
        ->and(ItemSearchSweep::windowCountFor(1000))->toBe(2)
        ->and(ItemSearchSweep::windowCountFor(0))->toBe(1);
});

test('sweeping items hands every document of every requested window to the caller', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSearchWindow($client, 'data/wow/search/item', 0, [
        ['id' => 10, 'name' => ['fr_FR' => 'Heaume'], 'quality' => ['type' => 'RARE'], 'appearances' => [['id' => 321]]],
    ]);
    mockSearchWindow($client, 'data/wow/search/item', 2, [
        ['id' => 2500, 'name' => ['fr_FR' => 'Lame'], 'quality' => ['type' => 'EPIC'], 'appearances' => [['id' => 500]]],
    ]);

    $swept = [];
    resolve(ItemSearchSweep::class)->sweepItems([0, 2], function (ItemSearchDocument $itemSearchDocument) use (&$swept): void {
        $swept[$itemSearchDocument->id] = $itemSearchDocument->appearanceIds;
    });

    expect($swept)->toBe([10 => [321], 2500 => [500]]);
});

test('sweeping items skips a window the API did not answer', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSearchWindow($client, 'data/wow/search/item', 0, [['id' => 10, 'name' => ['fr_FR' => 'Heaume']]]);
    $client->shouldReceive('getAsync')
        ->withArgs(fn (string $requested): bool => str_contains($requested, 'id=[1000,1999]'))
        ->andReturnUsing(fn (): \GuzzleHttp\Promise\PromiseInterface => Create::promiseFor(new Response(500, [], '')));

    $swept = [];
    resolve(ItemSearchSweep::class)->sweepItems([0, 1], function (ItemSearchDocument $itemSearchDocument) use (&$swept): void {
        $swept[] = $itemSearchDocument->id;
    });

    expect($swept)->toBe([10]);
});

test('sweeping item media indexes the icons by media id', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSearchWindow($client, 'data/wow/search/media', 0, [
        ['id' => 10, 'assets' => [['key' => 'icon', 'value' => 'https://render.worldofwarcraft.com/eu/icons/56/a.jpg', 'file_data_id' => 1]]],
        ['id' => 11, 'assets' => []],
    ]);

    $media = resolve(ItemSearchSweep::class)->sweepItemMedia([0]);

    expect(array_keys($media))->toBe([10, 11])
        ->and($media[10]->iconUrl)->toBe('https://render.worldofwarcraft.com/eu/icons/56/a.jpg')
        ->and($media[10]->fileDataId)->toBe(1)
        ->and($media[11]->iconUrl)->toBeNull();
});

test('a negative highest item id is rejected', function (): void {
    ItemSearchSweep::windowCountFor(-1);
})->throws(InvalidArgumentException::class, 'A highest id cannot be negative, got -1.');

test('sweeping no window at all calls nothing', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    $client->shouldNotReceive('getAsync');

    resolve(ItemSearchSweep::class)->sweepItems([], function (ItemSearchDocument $itemSearchDocument): void {
        throw new \LogicException('No document can be swept from no window.');
    });

    expect(resolve(ItemSearchSweep::class)->sweepItemMedia([]))->toBe([]);
});
