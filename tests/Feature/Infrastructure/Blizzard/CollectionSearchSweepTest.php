<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\CollectionSearchSweep;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    Sleep::fake();
});

/**
 * Mocke une fenêtre de recherche : la doublure n'accepte que l'endpoint et l'intervalle demandés.
 *
 * @param  list<array<string, mixed>>  $documents
 */
function mockCollectionWindow(\Mockery\MockInterface $mock, string $endpoint, int $window, array $documents): void
{
    $range = sprintf('id=[%d,%d]', $window * 1000, $window * 1000 + 999);

    $mock->shouldReceive('getAsync')
        ->withArgs(fn (string $requested): bool => str_starts_with($requested, $endpoint) && str_contains($requested, $range))
        ->andReturnUsing(fn (): PromiseInterface => Create::promiseFor(new Response(200, [], (string) json_encode([
            'results' => array_map(static fn (array $document): array => ['data' => $document], $documents),
        ]))));
}

test('sweeping mounts indexes their identity by mount id', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockCollectionWindow($client, 'data/wow/search/mount', 0, [
        ['id' => 14, 'name' => ['fr_FR' => 'Loup des bois'], 'source' => ['type' => 'VENDOR']],
        ['id' => 802, 'name' => ['fr_FR' => 'Hippogriffe oublié depuis longtemps'], 'source' => ['type' => 'DISCOVERY']],
    ]);

    $mounts = resolve(CollectionSearchSweep::class)->sweepMounts([0]);

    expect(array_keys($mounts))->toBe([14, 802])
        ->and($mounts[14]->nameFr)->toBe('Loup des bois')
        ->and($mounts[14]->sourceType)->toBe('VENDOR')
        ->and($mounts[802]->sourceType)->toBe('DISCOVERY');
});

test('sweeping decors indexes their identity and their bound item by decor id', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockCollectionWindow($client, 'data/wow/search/decor', 0, [
        ['id' => 80, 'name' => ['fr_FR' => 'Foyer orné en pierre'], 'item' => ['id' => 235994]],
    ]);

    $decors = resolve(CollectionSearchSweep::class)->sweepDecors([0]);

    expect(array_keys($decors))->toBe([80])
        ->and($decors[80]->nameFr)->toBe('Foyer orné en pierre')
        ->and($decors[80]->itemId)->toBe(235994);
});

test('sweeping several windows gathers them into one index', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockCollectionWindow($client, 'data/wow/search/mount', 0, [['id' => 14, 'name' => ['fr_FR' => 'Loup']]]);
    mockCollectionWindow($client, 'data/wow/search/mount', 3, [['id' => 3119, 'name' => ['fr_FR' => 'Alpaga']]]);

    expect(array_keys(resolve(CollectionSearchSweep::class)->sweepMounts([0, 3])))->toBe([14, 3119]);
});

test('sweeping nothing asks the API for nothing', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    $client->shouldNotReceive('getAsync');

    expect(resolve(CollectionSearchSweep::class)->sweepMounts([]))->toBe([]);
});

test('a window the API fails to serve leaves the others readable', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockCollectionWindow($client, 'data/wow/search/mount', 0, [['id' => 14, 'name' => ['fr_FR' => 'Loup']]]);
    $client->shouldReceive('getAsync')
        ->withArgs(fn (string $requested): bool => str_contains($requested, 'id=[1000,1999]'))
        ->andReturnUsing(fn (): PromiseInterface => Create::rejectionFor(new Response(500)));

    expect(array_keys(resolve(CollectionSearchSweep::class)->sweepMounts([0, 1])))->toBe([14]);
});
