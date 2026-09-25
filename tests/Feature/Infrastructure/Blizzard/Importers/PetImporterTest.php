<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Importers\PetImporter;
use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Models\WowCollectionTaxonomy;
use App\Models\WowPet;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    Sleep::fake();
});

/**
 * Mocke l'index API des mascottes, qui tranche l'existence et le nom.
 *
 * @param  list<array{id: int, name: string}>  $pets
 */
function mockPetIndex(\Mockery\MockInterface $mock, array $pets): void
{
    $mock->shouldReceive('get')
        ->with('data/wow/pet/index', \Mockery::any())
        ->andReturn(['pets' => $pets]);
}

/**
 * Mocke le détail d'une mascotte, qui porte son icône, sa créature et son type de source.
 *
 * @param  array<string, mixed>  $detail
 */
function mockPetDetail(\Mockery\MockInterface $mock, int $id, array $detail): void
{
    $mock->shouldReceive('getAsync')
        ->withArgs(fn (string $requested): bool => $requested === 'data/wow/pet/'.$id)
        ->andReturnUsing(fn (): PromiseInterface => Create::promiseFor(new Response(200, [], (string) json_encode($detail))));
}

function mockPetDetailFailure(\Mockery\MockInterface $mock, int $id): void
{
    $mock->shouldReceive('getAsync')
        ->withArgs(fn (string $requested): bool => $requested === 'data/wow/pet/'.$id)
        ->andReturnUsing(fn (): PromiseInterface => Create::rejectionFor(new Response(500)));
}

function curatePet(int $entryId, ?string $category, ?string $source): void
{
    WowCollectionTaxonomy::factory()->create([
        'entity' => CollectionEntity::Pet,
        'entry_id' => $entryId,
        'category' => $category,
        'source' => $source,
    ]);
}

test('it takes the identity from the API detail and the ranking from the taxonomy', function (): void {
    curatePet(39, 'Classic', 'Profession');

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockPetIndex($client, [['id' => 39, 'name' => 'Écureuil mécanique']]);
    mockPetDetail($client, 39, [
        'id' => 39,
        'name' => 'Écureuil mécanique',
        'icon' => 'https://render.worldofwarcraft.com/eu/icons/56/656559.jpg',
        'creature' => ['id' => 2671],
        'source' => ['type' => 'PROFESSION'],
    ]);

    resolve(PetImporter::class)->import();

    $wowPet = WowPet::query()->findOrFail(39);

    expect($wowPet->name_fr)->toBe('Écureuil mécanique')
        ->and($wowPet->category)->toBe('Classic')
        ->and($wowPet->source)->toBe('Profession')
        ->and($wowPet->icon_url)->toBe('https://render.worldofwarcraft.com/eu/icons/56/656559.jpg')
        ->and($wowPet->creature_id)->toBe(2671)
        ->and($wowPet->is_active)->toBeTrue();
});

test('it falls back to the API source type for a pet nobody has ranked yet', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockPetIndex($client, [['id' => 3317, 'name' => 'Mascotte sauvage']]);
    mockPetDetail($client, 3317, ['id' => 3317, 'name' => 'Mascotte sauvage', 'source' => ['type' => 'WILDPET']]);

    resolve(PetImporter::class)->import();

    expect(WowPet::query()->findOrFail(3317)->category)->toBeNull()
        ->and(WowPet::query()->findOrFail(3317)->source)->toBe('Wild Pet');
});

test('it reports how many pets are waiting to be arbitrated', function (): void {
    curatePet(39, 'Classic', 'Profession');

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockPetIndex($client, [
        ['id' => 39, 'name' => 'Rangée'],
        ['id' => 998, 'name' => 'À arbitrer'],
        ['id' => 999, 'name' => 'À arbitrer aussi'],
    ]);
    mockPetDetail($client, 39, ['id' => 39, 'name' => 'Rangée']);
    mockPetDetail($client, 998, ['id' => 998, 'name' => 'À arbitrer']);
    mockPetDetail($client, 999, ['id' => 999, 'name' => 'À arbitrer aussi']);

    ob_start();
    resolve(PetImporter::class)->import();
    $output = (string) ob_get_clean();

    expect($output)->toContain('2 awaiting arbitration');
});

test('a failed detail leaves the pet its icon and its creature rather than blanking them', function (): void {
    WowPet::query()->create([
        'id' => 39,
        'name_fr' => 'Écureuil mécanique',
        'icon_url' => 'https://render.worldofwarcraft.com/eu/icons/56/656559.jpg',
        'creature_id' => 2671,
        'is_active' => true,
    ]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockPetIndex($client, [['id' => 39, 'name' => 'Écureuil mécanique']]);
    mockPetDetailFailure($client, 39);

    resolve(PetImporter::class)->import();

    $wowPet = WowPet::query()->findOrFail(39);

    expect($wowPet->icon_url)->toBe('https://render.worldofwarcraft.com/eu/icons/56/656559.jpg')
        ->and($wowPet->creature_id)->toBe(2671);
});

test('it leaves a row the API has not changed untouched', function (): void {
    curatePet(39, 'Classic', 'Profession');

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockPetIndex($client, [['id' => 39, 'name' => 'Écureuil mécanique']]);
    mockPetDetail($client, 39, ['id' => 39, 'name' => 'Écureuil mécanique', 'creature' => ['id' => 2671]]);

    resolve(PetImporter::class)->import();

    ob_start();
    resolve(PetImporter::class)->import();
    $output = (string) ob_get_clean();

    expect($output)->toContain('Saving 0 pets');
});

test('it deletes pets that dropped out of the API catalog', function (): void {
    WowPet::query()->create(['id' => 5000, 'name_fr' => 'Mascotte retirée', 'is_active' => true]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockPetIndex($client, [['id' => 39, 'name' => 'Écureuil mécanique']]);
    mockPetDetail($client, 39, ['id' => 39, 'name' => 'Écureuil mécanique']);

    resolve(PetImporter::class)->import();

    expect(WowPet::query()->find(5000))->toBeNull()
        ->and(WowPet::query()->count())->toBe(1);
});

test('it aborts without touching the catalog when the API index is empty', function (): void {
    WowPet::query()->create(['id' => 200, 'name_fr' => 'Mascotte existante', 'is_active' => true]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockPetIndex($client, []);

    resolve(PetImporter::class)->import();

    expect(WowPet::query()->count())->toBe(1)
        ->and(WowPet::query()->findOrFail(200)->name_fr)->toBe('Mascotte existante');
});

test('it aborts without deleting anything when the pet index call fails', function (): void {
    WowPet::query()->create(['id' => 300, 'name_fr' => 'Mascotte existante', 'is_active' => true]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    $client->shouldReceive('get')
        ->with('data/wow/pet/index', \Mockery::any())
        ->andThrow(new \Exception('API error: 500 Internal Server Error'));

    resolve(PetImporter::class)->import();

    expect(WowPet::query()->count())->toBe(1)
        ->and(WowPet::query()->findOrFail(300)->name_fr)->toBe('Mascotte existante');
});
