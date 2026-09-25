<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Importers\MountImporter;
use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Models\WowCollectionTaxonomy;
use App\Models\WowMount;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    Sleep::fake();
    Http::preventStrayRequests();
});

/**
 * Doublure du CDN de rendu, d'où l'importer vérifie les icônes qu'il compose.
 *
 * Posée par test et non dans le beforeEach : les doublures Http s'empilent, et la première
 * qui correspond gagne — une seconde ne remplacerait pas la première.
 */
function fakeIconCdn(int $status): void
{
    Http::fake(['render.worldofwarcraft.com/*' => Http::response('', $status)]);
}

/**
 * Mocke l'index API des montures, qui tranche l'existence et le nom.
 *
 * @param  list<array{id: int, name: string}>  $mounts
 */
function mockMountIndex(\Mockery\MockInterface $mock, array $mounts): void
{
    $mock->shouldReceive('get')
        ->with('data/wow/mount/index', \Mockery::any())
        ->andReturn(['mounts' => $mounts]);
}

/**
 * Mocke le balayage de recherche des montures, qui n'apporte que le type de source.
 *
 * @param  list<array<string, mixed>>  $documents
 */
function mockMountSearch(\Mockery\MockInterface $mock, array $documents): void
{
    $mock->shouldReceive('getAsync')
        ->withArgs(fn (string $requested): bool => str_starts_with($requested, 'data/wow/search/mount'))
        ->andReturnUsing(fn (): PromiseInterface => Create::promiseFor(new Response(200, [], (string) json_encode([
            'results' => array_map(static fn (array $document): array => ['data' => $document], $documents),
        ]))));
}

function curateMount(int $entryId, ?string $category, ?string $source): void
{
    WowCollectionTaxonomy::factory()->create([
        'entity' => CollectionEntity::Mount,
        'entry_id' => $entryId,
        'category' => $category,
        'source' => $source,
    ]);
}

function seedMountReference(int $mountId, int $sourceSpellId, ?int $iconFileDataId = null): void
{
    DB::table('wow_ref_mount')->insert(['id' => $mountId, 'source_spell_id' => $sourceSpellId]);

    if ($iconFileDataId !== null) {
        DB::table('wow_ref_spell_misc')->insert([
            'id' => $sourceSpellId,
            'spell_id' => $sourceSpellId,
            'spell_icon_file_data_id' => $iconFileDataId,
        ]);
    }
}

test('it takes the identity from the API and the ranking from the taxonomy', function (): void {
    curateMount(100, 'Classic', 'Reputation');
    curateMount(101, 'Legion', 'Class Hall');
    seedMountReference(100, 1234, 132261);
    fakeIconCdn(200);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockMountIndex($client, [['id' => 100, 'name' => 'Monture Test'], ['id' => 101, 'name' => 'Destrier squelette']]);
    mockMountSearch($client, [['id' => 100, 'name' => ['fr_FR' => 'Monture Test'], 'source' => ['type' => 'VENDOR']]]);

    resolve(MountImporter::class)->import();

    $wowMount = WowMount::query()->findOrFail(100);

    expect(WowMount::query()->count())->toBe(2)
        ->and($wowMount->name_fr)->toBe('Monture Test')
        ->and($wowMount->category)->toBe('Classic')
        ->and($wowMount->source)->toBe('Reputation')
        ->and($wowMount->icon_url)->toBe('https://render.worldofwarcraft.com/eu/icons/56/132261.jpg')
        ->and($wowMount->source_spell_id)->toBe(1234)
        ->and($wowMount->is_active)->toBeTrue()
        ->and(WowMount::query()->findOrFail(101)->category)->toBe('Legion');
});

test('it never reads the curated files, so a missing SimpleArmory dump stops nothing', function (): void {
    curateMount(100, 'Classic', 'Reputation');

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockMountIndex($client, [['id' => 100, 'name' => 'Monture Test']]);
    mockMountSearch($client, []);

    resolve(MountImporter::class)->import();

    expect(WowMount::query()->findOrFail(100)->name_fr)->toBe('Monture Test');
});

test('it falls back to the API source type for a mount nobody has ranked yet', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockMountIndex($client, [['id' => 999, 'name' => 'Monture à arbitrer']]);
    mockMountSearch($client, [['id' => 999, 'name' => ['fr_FR' => 'Monture à arbitrer'], 'source' => ['type' => 'TRADINGPOST']]]);

    resolve(MountImporter::class)->import();

    $wowMount = WowMount::query()->findOrFail(999);

    expect($wowMount->category)->toBeNull()
        ->and($wowMount->source)->toBe('Trading Post')
        ->and($wowMount->is_active)->toBeTrue();
});

test('it leaves a mount ranked nowhere when the taxonomy curates it as ranked nowhere', function (): void {
    curateMount(100, null, null);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockMountIndex($client, [['id' => 100, 'name' => 'Monture Test']]);
    mockMountSearch($client, [['id' => 100, 'name' => ['fr_FR' => 'Monture Test'], 'source' => ['type' => 'VENDOR']]]);

    resolve(MountImporter::class)->import();

    expect(WowMount::query()->findOrFail(100)->category)->toBeNull()
        ->and(WowMount::query()->findOrFail(100)->source)->toBeNull();
});

test('it reports how many mounts are waiting to be arbitrated', function (): void {
    curateMount(100, 'Classic', 'Drop');

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockMountIndex($client, [
        ['id' => 100, 'name' => 'Rangée'],
        ['id' => 998, 'name' => 'À arbitrer'],
        ['id' => 999, 'name' => 'À arbitrer aussi'],
    ]);
    mockMountSearch($client, []);

    ob_start();
    resolve(MountImporter::class)->import();
    $output = (string) ob_get_clean();

    expect($output)->toContain('2 awaiting arbitration');
});

test('it keeps the icon a mount already had when the reference socle carries none', function (): void {
    WowMount::query()->create([
        'id' => 100,
        'name_fr' => 'Monture Test',
        'icon_url' => 'https://render.worldofwarcraft.com/eu/icons/56/999999.jpg',
        'is_active' => true,
    ]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockMountIndex($client, [['id' => 100, 'name' => 'Monture Test']]);
    mockMountSearch($client, []);

    resolve(MountImporter::class)->import();

    expect(WowMount::query()->findOrFail(100)->icon_url)
        ->toBe('https://render.worldofwarcraft.com/eu/icons/56/999999.jpg');
});

test('it leaves a row the API has not changed untouched', function (): void {
    curateMount(100, 'Classic', 'Reputation');
    seedMountReference(100, 1234, 132261);
    fakeIconCdn(200);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockMountIndex($client, [['id' => 100, 'name' => 'Monture Test']]);
    mockMountSearch($client, []);

    resolve(MountImporter::class)->import();

    ob_start();
    resolve(MountImporter::class)->import();
    $output = (string) ob_get_clean();

    expect($output)->toContain('Saving 0 mounts');
});

test('it deletes mounts that dropped out of the API catalog', function (): void {
    WowMount::query()->create(['id' => 3021, 'name_fr' => 'Monture retirée', 'is_active' => true]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockMountIndex($client, [['id' => 100, 'name' => 'Monture Test']]);
    mockMountSearch($client, []);

    resolve(MountImporter::class)->import();

    expect(WowMount::query()->find(3021))->toBeNull()
        ->and(WowMount::query()->count())->toBe(1);
});

test('it aborts without touching the catalog when the API index is empty', function (): void {
    WowMount::query()->create(['id' => 200, 'name_fr' => 'Monture existante', 'is_active' => true]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockMountIndex($client, []);

    resolve(MountImporter::class)->import();

    expect(WowMount::query()->count())->toBe(1)
        ->and(WowMount::query()->findOrFail(200)->name_fr)->toBe('Monture existante');
});

test('it aborts without deleting anything when the mount index call fails', function (): void {
    WowMount::query()->create(['id' => 300, 'name_fr' => 'Monture existante', 'is_active' => true]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    $client->shouldReceive('get')
        ->with('data/wow/mount/index', \Mockery::any())
        ->andThrow(new \Exception('API error: 500 Internal Server Error'));

    resolve(MountImporter::class)->import();

    expect(WowMount::query()->count())->toBe(1)
        ->and(WowMount::query()->findOrFail(300)->name_fr)->toBe('Monture existante');
});

test('a failed search window costs the source type, never the mount itself', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockMountIndex($client, [['id' => 100, 'name' => 'Monture Test']]);
    $client->shouldReceive('getAsync')
        ->withArgs(fn (string $requested): bool => str_starts_with($requested, 'data/wow/search/mount'))
        ->andReturnUsing(fn (): PromiseInterface => Create::rejectionFor(new Response(500)));

    resolve(MountImporter::class)->import();

    expect(WowMount::query()->findOrFail(100)->name_fr)->toBe('Monture Test')
        ->and(WowMount::query()->findOrFail(100)->source)->toBeNull();
});

test('it drops an icon the render CDN refuses rather than writing a broken image', function (): void {
    seedMountReference(100, 1234, 132233);
    fakeIconCdn(403);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockMountIndex($client, [['id' => 100, 'name' => 'Faucon de guerre rapide']]);
    mockMountSearch($client, []);

    resolve(MountImporter::class)->import();

    expect(WowMount::query()->findOrFail(100)->icon_url)->toBeNull();
});

test('it checks only the icons whose URL changes, so a second pass asks the CDN nothing', function (): void {
    seedMountReference(100, 1234, 132261);
    fakeIconCdn(200);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockMountIndex($client, [['id' => 100, 'name' => 'Monture Test']]);
    mockMountSearch($client, []);

    resolve(MountImporter::class)->import();

    Http::assertSentCount(1);

    resolve(MountImporter::class)->import();

    Http::assertSentCount(1);
});
