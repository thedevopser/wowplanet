<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Importers\DecorImporter;
use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Models\WowCollectionTaxonomy;
use App\Models\WowDecor;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    Sleep::fake();
});

/**
 * Mocke l'index API des décorations, qui tranche l'existence et le nom.
 *
 * @param  list<array{id: int, name: string}>  $decors
 */
function mockDecorIndex(\Mockery\MockInterface $mock, array $decors): void
{
    $mock->shouldReceive('get')
        ->with('data/wow/decor/index', \Mockery::any())
        ->andReturn(['decor_items' => $decors]);
}

/**
 * Mocke le balayage de recherche des décorations, qui n'apporte que l'item lié.
 *
 * @param  list<array<string, mixed>>  $documents
 */
function mockDecorSearch(\Mockery\MockInterface $mock, array $documents): void
{
    $mock->shouldReceive('getAsync')
        ->withArgs(fn (string $requested): bool => str_starts_with($requested, 'data/wow/search/decor'))
        ->andReturnUsing(fn (): PromiseInterface => Create::promiseFor(new Response(200, [], (string) json_encode([
            'results' => array_map(static fn (array $document): array => ['data' => $document], $documents),
        ]))));
}

/**
 * Mocke le balayage des media d'items, d'où viennent les icônes des décorations.
 *
 * @param  array<int, string>  $icons  [item_id => icon_url]
 */
function mockDecorItemMedia(\Mockery\MockInterface $mock, array $icons): void
{
    $mock->shouldReceive('getAsync')
        ->withArgs(fn (string $requested): bool => str_starts_with($requested, 'data/wow/search/media')
            && str_contains($requested, 'tags=item'))
        ->andReturnUsing(fn (): PromiseInterface => Create::promiseFor(new Response(200, [], (string) json_encode([
            'results' => array_map(
                static fn (string $url, int $id): array => ['data' => ['id' => $id, 'assets' => [['key' => 'icon', 'value' => $url]]]],
                $icons,
                array_keys($icons),
            ),
        ]))));
}

function curateDecor(int $entryId, ?string $category, ?string $source, bool $obtainable = true): void
{
    WowCollectionTaxonomy::factory()->create([
        'entity' => CollectionEntity::Decor,
        'entry_id' => $entryId,
        'category' => $category,
        'source' => $source,
        'obtainable' => $obtainable,
    ]);
}

test('it takes the identity from the API and the ranking from the taxonomy', function (): void {
    curateDecor(80, 'Quartiers', 'Vendor');

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockDecorIndex($client, [['id' => 80, 'name' => 'Foyer orné en pierre']]);
    mockDecorSearch($client, [['id' => 80, 'name' => ['fr_FR' => 'Foyer orné en pierre'], 'item' => ['id' => 235994]]]);
    mockDecorItemMedia($client, [235994 => 'https://render.worldofwarcraft.com/eu/icons/56/135234.jpg']);

    resolve(DecorImporter::class)->import();

    $wowDecor = WowDecor::query()->findOrFail(80);

    expect($wowDecor->name_fr)->toBe('Foyer orné en pierre')
        ->and($wowDecor->category)->toBe('Quartiers')
        ->and($wowDecor->source)->toBe('Vendor')
        ->and($wowDecor->item_id)->toBe(235994)
        ->and($wowDecor->icon_url)->toBe('https://render.worldofwarcraft.com/eu/icons/56/135234.jpg')
        ->and($wowDecor->is_active)->toBeTrue();
});

test('it deactivates a decor the curation marks as no longer obtainable', function (): void {
    curateDecor(80, 'Quartiers', 'Promotion', obtainable: false);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockDecorIndex($client, [['id' => 80, 'name' => 'Foyer orné en pierre']]);
    mockDecorSearch($client, []);
    mockDecorItemMedia($client, []);

    resolve(DecorImporter::class)->import();

    expect(WowDecor::query()->findOrFail(80)->is_active)->toBeFalse();
});

test('it holds a decor obtainable when no curation says otherwise', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockDecorIndex($client, [['id' => 999, 'name' => 'Décoration à arbitrer']]);
    mockDecorSearch($client, []);
    mockDecorItemMedia($client, []);

    resolve(DecorImporter::class)->import();

    expect(WowDecor::query()->findOrFail(999)->is_active)->toBeTrue()
        ->and(WowDecor::query()->findOrFail(999)->category)->toBeNull();
});

test('it reports how many decors are waiting to be arbitrated', function (): void {
    curateDecor(80, 'Quartiers', 'Vendor');

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockDecorIndex($client, [
        ['id' => 80, 'name' => 'Rangée'],
        ['id' => 998, 'name' => 'À arbitrer'],
        ['id' => 999, 'name' => 'À arbitrer aussi'],
    ]);
    mockDecorSearch($client, []);
    mockDecorItemMedia($client, []);

    ob_start();
    resolve(DecorImporter::class)->import();
    $output = (string) ob_get_clean();

    expect($output)->toContain('2 awaiting arbitration');
});

test('a failed search window leaves the decor the item and icon it had', function (): void {
    WowDecor::query()->create([
        'id' => 80,
        'name_fr' => 'Foyer orné en pierre',
        'item_id' => 235994,
        'icon_url' => 'https://render.worldofwarcraft.com/eu/icons/56/135234.jpg',
        'is_active' => true,
    ]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockDecorIndex($client, [['id' => 80, 'name' => 'Foyer orné en pierre']]);
    $client->shouldReceive('getAsync')
        ->withArgs(fn (string $requested): bool => str_starts_with($requested, 'data/wow/search/decor'))
        ->andReturnUsing(fn (): PromiseInterface => Create::rejectionFor(new Response(500)));
    mockDecorItemMedia($client, []);

    resolve(DecorImporter::class)->import();

    $wowDecor = WowDecor::query()->findOrFail(80);

    expect($wowDecor->item_id)->toBe(235994)
        ->and($wowDecor->icon_url)->toBe('https://render.worldofwarcraft.com/eu/icons/56/135234.jpg');
});

test('it leaves a row the API has not changed untouched', function (): void {
    curateDecor(80, 'Quartiers', 'Vendor');

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockDecorIndex($client, [['id' => 80, 'name' => 'Foyer orné en pierre']]);
    mockDecorSearch($client, [['id' => 80, 'name' => ['fr_FR' => 'Foyer orné en pierre'], 'item' => ['id' => 235994]]]);
    mockDecorItemMedia($client, [235994 => 'https://render.worldofwarcraft.com/eu/icons/56/135234.jpg']);

    resolve(DecorImporter::class)->import();

    ob_start();
    resolve(DecorImporter::class)->import();
    $output = (string) ob_get_clean();

    expect($output)->toContain('Saving 0 decors');
});

test('it deletes decors that dropped out of the API catalog', function (): void {
    WowDecor::query()->create(['id' => 5000, 'name_fr' => 'Décoration retirée', 'is_active' => true]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockDecorIndex($client, [['id' => 80, 'name' => 'Foyer orné en pierre']]);
    mockDecorSearch($client, []);
    mockDecorItemMedia($client, []);

    resolve(DecorImporter::class)->import();

    expect(WowDecor::query()->find(5000))->toBeNull()
        ->and(WowDecor::query()->count())->toBe(1);
});

test('it aborts without touching the catalog when the API index is empty', function (): void {
    WowDecor::query()->create(['id' => 200, 'name_fr' => 'Décoration existante', 'is_active' => true]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockDecorIndex($client, []);

    resolve(DecorImporter::class)->import();

    expect(WowDecor::query()->count())->toBe(1)
        ->and(WowDecor::query()->findOrFail(200)->name_fr)->toBe('Décoration existante');
});

test('it aborts without deleting anything when the decor index call fails', function (): void {
    WowDecor::query()->create(['id' => 300, 'name_fr' => 'Décoration existante', 'is_active' => true]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    $client->shouldReceive('get')
        ->with('data/wow/decor/index', \Mockery::any())
        ->andThrow(new \Exception('API error: 500 Internal Server Error'));

    resolve(DecorImporter::class)->import();

    expect(WowDecor::query()->count())->toBe(1)
        ->and(WowDecor::query()->findOrFail(300)->name_fr)->toBe('Décoration existante');
});
