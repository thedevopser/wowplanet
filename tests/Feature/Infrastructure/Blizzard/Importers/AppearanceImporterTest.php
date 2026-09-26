<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Importers\AppearanceImporter;
use App\Models\WowAppearance;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    Sleep::fake();
    Cache::flush();
});

/**
 * Mocke les 18 index de slots : ceux listés dans $slots reçoivent leurs apparences,
 * les autres renvoient une liste vide.
 *
 * @param  array<string, list<int>>  $slots  [slotType => list<appearanceId>]
 * @param  list<string>  $failing
 */
function mockSlotIndexes(\Mockery\MockInterface $mock, array $slots, array $failing = []): void
{
    $allSlots = [
        'HEAD', 'SHOULDER', 'BODY', 'CHEST', 'WAIST', 'LEGS', 'FEET', 'WRIST', 'HAND',
        'CLOAK', 'TABARD', 'WEAPON', 'SHIELD', 'RANGED', 'TWOHWEAPON', 'WEAPONMAINHAND',
        'WEAPONOFFHAND', 'HOLDABLE',
    ];

    foreach ($allSlots as $allSlot) {
        $expectation = $mock->shouldReceive('get')
            ->with('data/wow/item-appearance/slot/'.$allSlot, \Mockery::any());

        if (in_array($allSlot, $failing, true)) {
            $expectation->andThrow(new \Exception('API error: 404 Not Found'));

            continue;
        }

        $ids = $slots[$allSlot] ?? [];
        $expectation->andReturn([
            'appearances' => array_map(fn (int $id): array => ['id' => $id], $ids),
        ]);
    }
}

/**
 * Borne du balayage : le plus grand identifiant d'item du catalogue.
 */
function mockHighestItemId(\Mockery\MockInterface $mock, ?int $highestItemId): void
{
    $expectation = $mock->shouldReceive('get')
        ->withArgs(fn (string $endpoint): bool => str_contains($endpoint, 'orderby=id:desc'));

    if ($highestItemId === null) {
        $expectation->andThrow(new \Exception('API error: 500 Internal Server Error'));

        return;
    }

    $expectation->andReturn(['results' => [['data' => ['id' => $highestItemId]]]]);
}

/**
 * Un document de recherche d'item, réduit à ce que l'importer lit.
 *
 * @param  list<int>  $appearanceIds
 * @return array<string, mixed>
 */
function itemDocument(int $id, string $name, string $quality, array $appearanceIds, string $category = 'Armure'): array
{
    return [
        'id' => $id,
        'name' => ['fr_FR' => $name],
        'quality' => ['type' => $quality],
        'media' => ['id' => $id],
        'item_class' => ['id' => 4, 'name' => ['fr_FR' => $category]],
        'appearances' => array_map(fn (int $appearanceId): array => ['id' => $appearanceId], $appearanceIds),
    ];
}

/**
 * Mocke les fenêtres de recherche d'un endpoint : celles qui ne sont pas décrites
 * répondent une page vide, comme le ferait une plage d'identifiants sans résultat.
 *
 * @param  array<int, list<array<string, mixed>>>  $windows  [windowIndex => list<document>]
 */
function mockSearchWindows(\Mockery\MockInterface $mock, string $endpoint, array $windows): void
{
    foreach ($windows as $window => $documents) {
        $range = sprintf('id=[%d,%d]', $window * 1000, $window * 1000 + 999);

        $mock->shouldReceive('getAsync')
            ->withArgs(fn (string $requested): bool => str_starts_with($requested, $endpoint.'?') && str_contains($requested, $range))
            ->andReturnUsing(fn (): \GuzzleHttp\Promise\PromiseInterface => Create::promiseFor(new Response(200, [], (string) json_encode([
                'results' => array_map(static fn (array $document): array => ['data' => $document], $documents),
            ]))));
    }

    $mock->shouldReceive('getAsync')
        ->withArgs(fn (string $requested): bool => str_starts_with($requested, $endpoint.'?'))
        ->andReturnUsing(fn (): \GuzzleHttp\Promise\PromiseInterface => Create::promiseFor(new Response(200, [], '{"results":[]}')));
}

/**
 * Un media d'item : l'icône que porte l'item représentatif.
 *
 * @return array<string, mixed>
 */
function mediaDocument(int $id, string $icon, ?int $fileDataId = null): array
{
    return [
        'id' => $id,
        'assets' => [array_filter([
            'key' => 'icon',
            'value' => $icon,
            'file_data_id' => $fileDataId,
        ], static fn (mixed $value): bool => $value !== null)],
    ];
}

test('it builds the wardrobe from item searches without a single appearance detail call', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSlotIndexes($client, [
        'HEAD' => [321],
        'BODY' => [400],
        'WEAPONMAINHAND' => [500],
    ]);
    mockHighestItemId($client, 700);

    $client->shouldNotReceive('getAsync')
        ->withArgs(fn (string $endpoint): bool => str_starts_with($endpoint, 'data/wow/item-appearance/'));

    mockSearchWindows($client, 'data/wow/search/item', [
        0 => [
            itemDocument(10, 'Couvre-œil du forcené', 'RARE', [321]),
            itemDocument(20, 'Couvre-œil en écailles de lézard', 'EPIC', [321]),
            itemDocument(600, 'Chemise élégante', 'COMMON', [400]),
            itemDocument(700, 'Lame fidèle', 'LEGENDARY', [500], category: 'Arme'),
        ],
    ]);
    mockSearchWindows($client, 'data/wow/search/media', [
        0 => [
            mediaDocument(20, 'https://render.worldofwarcraft.com/eu/icons/56/inv_chest_samurai.jpg', 132759),
            mediaDocument(600, 'https://render.worldofwarcraft.com/eu/icons/56/inv_shirt_01.jpg', 100001),
            mediaDocument(700, 'https://render.worldofwarcraft.com/eu/icons/56/inv_sword_01.jpg', 100002),
        ],
    ]);

    resolve(AppearanceImporter::class)->import();

    expect(WowAppearance::query()->count())->toBe(3);

    $head = WowAppearance::query()->find(321);
    // item représentatif = meilleure qualité parmi les items liés
    expect($head->name_fr)->toBe('Couvre-œil en écailles de lézard')
        ->and($head->item_id)->toBe(20)
        ->and($head->quality)->toBe(4)
        ->and($head->slot)->toBe('HEAD')
        ->and($head->category)->toBe('Armure')
        ->and($head->icon_url)->toBe('https://render.worldofwarcraft.com/eu/icons/56/inv_chest_samurai.jpg')
        ->and($head->icon_file_data_id)->toBe(132759)
        ->and($head->is_active)->toBeTrue();

    // vocabulaire de slots du front préservé : BODY → SHIRT, WEAPONMAINHAND → WEAPON
    expect(WowAppearance::query()->find(400)->slot)->toBe('SHIRT');

    $weapon = WowAppearance::query()->find(500);
    expect($weapon->slot)->toBe('WEAPON')
        ->and($weapon->category)->toBe('Arme')
        ->and($weapon->quality)->toBe(5);
});

test('only the appearances listed in the slot indexes enter the catalog', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSlotIndexes($client, ['HEAD' => [321]]);
    mockHighestItemId($client, 30);

    // 999 est porté par un item mais absent des index : apparence non collectionnable.
    mockSearchWindows($client, 'data/wow/search/item', [
        0 => [
            itemDocument(10, 'Heaume', 'RARE', [321]),
            itemDocument(30, 'Heaume interne', 'EPIC', [999]),
        ],
    ]);
    mockSearchWindows($client, 'data/wow/search/media', [0 => [mediaDocument(10, 'https://render.worldofwarcraft.com/eu/icons/56/a.jpg', 1)]]);

    resolve(AppearanceImporter::class)->import();

    expect(WowAppearance::query()->count())->toBe(1)
        ->and(WowAppearance::query()->find(999))->toBeNull();
});

test('the representative item is the best quality, ties broken by the lowest item id', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSlotIndexes($client, ['HEAD' => [321]]);
    mockHighestItemId($client, 1500);

    mockSearchWindows($client, 'data/wow/search/item', [
        0 => [
            itemDocument(50, 'Heaume rare', 'RARE', [321]),
            itemDocument(60, 'Heaume épique', 'EPIC', [321]),
        ],
        // La fenêtre suivante porte un second item épique : l'ordre de parcours ne doit
        // pas décider, sans quoi une reprise ne rendrait pas le même représentant.
        1 => [itemDocument(1200, 'Heaume épique bis', 'EPIC', [321])],
    ]);
    mockSearchWindows($client, 'data/wow/search/media', [
        0 => [mediaDocument(60, 'https://render.worldofwarcraft.com/eu/icons/56/a.jpg', 1)],
    ]);

    resolve(AppearanceImporter::class)->import();

    expect(WowAppearance::query()->find(321)->item_id)->toBe(60)
        ->and(WowAppearance::query()->find(321)->name_fr)->toBe('Heaume épique');
});

test('it aborts without deleting anything when a single slot index fails', function (): void {
    WowAppearance::factory()->create(['id' => 999, 'is_active' => true]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    // 17 slots répondent, CLOAK non : un index partiel effacerait tout un slot.
    mockSlotIndexes($client, ['HEAD' => [321]], failing: ['CLOAK']);
    $client->shouldNotReceive('getAsync');

    resolve(AppearanceImporter::class)->import();

    expect(WowAppearance::query()->find(999))->not->toBeNull()
        ->and(WowAppearance::query()->find(321))->toBeNull();
});

test('it aborts without touching the catalog when the highest item id is unreadable', function (): void {
    WowAppearance::factory()->create(['id' => 999, 'is_active' => true]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSlotIndexes($client, ['HEAD' => [321]]);
    mockHighestItemId($client, null);
    $client->shouldNotReceive('getAsync');

    // Balayer une plage devinée manquerait les items les plus récents, donc les
    // apparences neuves, et la suppression finale prendrait le reste pour du rebut.
    resolve(AppearanceImporter::class)->import();

    expect(WowAppearance::query()->find(999))->not->toBeNull()
        ->and(WowAppearance::query()->find(321))->toBeNull();
});

test('it deletes stale appearances no longer present in the API slot indexes', function (): void {
    WowAppearance::factory()->create(['id' => 999, 'is_active' => true]);
    // Reliquat désactivé par une passe précédente : doit être purgé lui aussi.
    WowAppearance::factory()->create(['id' => 998, 'is_active' => false]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSlotIndexes($client, ['HEAD' => [321]]);
    mockHighestItemId($client, 10);
    mockSearchWindows($client, 'data/wow/search/item', [0 => [itemDocument(10, 'Heaume', 'RARE', [321])]]);
    mockSearchWindows($client, 'data/wow/search/media', [0 => [mediaDocument(10, 'https://render.worldofwarcraft.com/eu/icons/56/a.jpg', 1)]]);

    resolve(AppearanceImporter::class)->import();

    expect(WowAppearance::query()->find(999))->toBeNull()
        ->and(WowAppearance::query()->find(998))->toBeNull()
        ->and(WowAppearance::query()->find(321)->is_active)->toBeTrue();
});

test('an appearance whose items carry no name gets no row', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSlotIndexes($client, ['HEAD' => [888]]);
    mockHighestItemId($client, 10);
    mockSearchWindows($client, 'data/wow/search/item', [
        0 => [['id' => 10, 'appearances' => [['id' => 888]]]],
    ]);
    mockSearchWindows($client, 'data/wow/search/media', []);

    resolve(AppearanceImporter::class)->import();

    // Sans nom, la ligne serait affichée vide et compterait au dénominateur.
    expect(WowAppearance::query()->find(888))->toBeNull();
});

test('an appearance whose representative item has no icon keeps a null icon', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSlotIndexes($client, ['HEAD' => [900]]);
    mockHighestItemId($client, 10);
    mockSearchWindows($client, 'data/wow/search/item', [0 => [itemDocument(10, 'Heaume', 'RARE', [900])]]);
    mockSearchWindows($client, 'data/wow/search/media', [0 => [['id' => 10, 'assets' => []]]]);

    resolve(AppearanceImporter::class)->import();

    $appearance = WowAppearance::query()->find(900);
    expect($appearance)->not->toBeNull()
        ->and($appearance->name_fr)->toBe('Heaume')
        ->and($appearance->icon_url)->toBeNull()
        ->and($appearance->icon_file_data_id)->toBeNull();
});

test('a row already holding the same representative keeps its icon untouched', function (): void {
    WowAppearance::factory()->create([
        'id' => 321,
        'name_fr' => 'Heaume',
        'slot' => 'HEAD',
        'category' => 'Armure',
        'quality' => 3,
        'item_id' => 10,
        'icon_url' => 'https://render.worldofwarcraft.com/eu/icons/56/old.jpg',
        'icon_file_data_id' => 7,
    ]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSlotIndexes($client, ['HEAD' => [321]]);
    mockHighestItemId($client, 10);
    mockSearchWindows($client, 'data/wow/search/item', [0 => [itemDocument(10, 'Heaume', 'RARE', [321])]]);
    mockSearchWindows($client, 'data/wow/search/media', [
        0 => [mediaDocument(10, 'https://render.worldofwarcraft.com/eu/icons/56/new.jpg', 8)],
    ]);

    resolve(AppearanceImporter::class)->import();

    expect(WowAppearance::query()->find(321)->icon_url)->toBe('https://render.worldofwarcraft.com/eu/icons/56/old.jpg');
});

test('a full refresh refetches the icons of every row', function (): void {
    WowAppearance::factory()->create([
        'id' => 321,
        'name_fr' => 'Heaume',
        'slot' => 'HEAD',
        'category' => 'Armure',
        'quality' => 3,
        'item_id' => 10,
        'icon_url' => 'https://render.worldofwarcraft.com/eu/icons/56/old.jpg',
        'icon_file_data_id' => 7,
    ]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSlotIndexes($client, ['HEAD' => [321]]);
    mockHighestItemId($client, 10);
    mockSearchWindows($client, 'data/wow/search/item', [0 => [itemDocument(10, 'Heaume', 'RARE', [321])]]);
    mockSearchWindows($client, 'data/wow/search/media', [
        0 => [mediaDocument(10, 'https://render.worldofwarcraft.com/eu/icons/56/new.jpg', 8)],
    ]);

    resolve(AppearanceImporter::class)->import(full: true);

    expect(WowAppearance::query()->find(321)->icon_url)->toBe('https://render.worldofwarcraft.com/eu/icons/56/new.jpg')
        ->and(WowAppearance::query()->find(321)->icon_file_data_id)->toBe(8);
});

test('a better representative item replaces the stale icon of the row', function (): void {
    WowAppearance::factory()->create([
        'id' => 321,
        'name_fr' => 'Heaume rare',
        'slot' => 'HEAD',
        'category' => 'Armure',
        'quality' => 3,
        'item_id' => 10,
        'icon_url' => 'https://render.worldofwarcraft.com/eu/icons/56/old.jpg',
        'icon_file_data_id' => 7,
    ]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSlotIndexes($client, ['HEAD' => [321]]);
    mockHighestItemId($client, 20);
    mockSearchWindows($client, 'data/wow/search/item', [
        0 => [
            itemDocument(10, 'Heaume rare', 'RARE', [321]),
            itemDocument(20, 'Heaume épique', 'EPIC', [321]),
        ],
    ]);
    mockSearchWindows($client, 'data/wow/search/media', [
        0 => [mediaDocument(20, 'https://render.worldofwarcraft.com/eu/icons/56/epic.jpg', 9)],
    ]);

    resolve(AppearanceImporter::class)->import();

    $appearance = WowAppearance::query()->find(321);
    expect($appearance->item_id)->toBe(20)
        ->and($appearance->name_fr)->toBe('Heaume épique')
        ->and($appearance->icon_url)->toBe('https://render.worldofwarcraft.com/eu/icons/56/epic.jpg')
        ->and($appearance->icon_file_data_id)->toBe(9);
});

test('the limit caps the swept windows and leaves the catalog untouched', function (): void {
    WowAppearance::factory()->create(['id' => 999, 'is_active' => true]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSlotIndexes($client, ['HEAD' => [321, 322]]);
    mockHighestItemId($client, 2500);

    mockSearchWindows($client, 'data/wow/search/item', [
        0 => [itemDocument(10, 'Heaume', 'RARE', [321])],
        1 => [itemDocument(1100, 'Heaume lointain', 'EPIC', [322])],
    ]);
    mockSearchWindows($client, 'data/wow/search/media', [
        0 => [mediaDocument(10, 'https://render.worldofwarcraft.com/eu/icons/56/a.jpg', 1)],
    ]);

    resolve(AppearanceImporter::class)->import(limit: 1);

    expect(WowAppearance::query()->find(321))->not->toBeNull()
        ->and(WowAppearance::query()->find(322))->toBeNull()
        ->and(WowAppearance::query()->find(999))->not->toBeNull();
});

test('importChunk stops without sleeping when the hourly budget is exhausted', function (): void {
    // Budget déjà au-delà du plafond réservé aux imports → importChunk doit rendre la
    // main (le job se re-dispatchera), sans dormir ni balayer la moindre fenêtre.
    resolve(\App\Infrastructure\Blizzard\HourlyBudgetGuard::class)->consume(\App\Infrastructure\Blizzard\HourlyBudgetGuard::HOURLY_LIMIT);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSlotIndexes($client, ['HEAD' => [321]]);
    mockHighestItemId($client, 10);
    $client->shouldNotReceive('getAsync');

    $appearanceImportProgress = resolve(AppearanceImporter::class)->importChunk(full: false, offset: 0, timeBoxSeconds: 600);

    expect($appearanceImportProgress->done)->toBeFalse()
        ->and($appearanceImportProgress->offset)->toBe(0) // aucun avancement : la fenêtre sera rejouée
        ->and($appearanceImportProgress->secondsUntilBudget)->toBeGreaterThan(0)
        ->and(WowAppearance::query()->count())->toBe(0);
    Sleep::assertNeverSlept();
});

test('importChunk resumes at the window where the previous pass stopped', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSlotIndexes($client, ['HEAD' => [321, 322]]);
    mockHighestItemId($client, 1500);

    $client->shouldNotReceive('getAsync')
        ->withArgs(fn (string $endpoint): bool => str_starts_with($endpoint, 'data/wow/search/item?') && str_contains($endpoint, 'id=[0,999]'));

    mockSearchWindows($client, 'data/wow/search/item', [
        1 => [itemDocument(1100, 'Heaume lointain', 'EPIC', [322])],
    ]);
    mockSearchWindows($client, 'data/wow/search/media', [
        1 => [mediaDocument(1100, 'https://render.worldofwarcraft.com/eu/icons/56/b.jpg', 2)],
    ]);

    // Deux fenêtres d'items, donc quatre unités de reprise : items puis media.
    $appearanceImportProgress = resolve(AppearanceImporter::class)->importChunk(full: false, offset: 1, timeBoxSeconds: 600);

    expect($appearanceImportProgress->done)->toBeTrue()
        ->and($appearanceImportProgress->offset)->toBe(4)
        ->and($appearanceImportProgress->total)->toBe(4)
        ->and(WowAppearance::query()->find(321))->toBeNull()
        ->and(WowAppearance::query()->find(322)->icon_url)->toBe('https://render.worldofwarcraft.com/eu/icons/56/b.jpg');
});

test('it returns early when every slot index fails', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    $client->shouldReceive('get')
        ->withArgs(fn (string $endpoint): bool => str_starts_with($endpoint, 'data/wow/item-appearance/slot/'))
        ->andThrow(new \Exception('API error: 500 Internal Server Error'));

    resolve(AppearanceImporter::class)->import();

    expect(WowAppearance::query()->count())->toBe(0);
});

test('a stored representative of better quality survives a sweep that only finds worse', function (): void {
    WowAppearance::factory()->create([
        'id' => 321,
        'name_fr' => 'Heaume épique',
        'slot' => 'HEAD',
        'category' => 'Armure',
        'quality' => 4,
        'item_id' => 20,
        'icon_url' => 'https://render.worldofwarcraft.com/eu/icons/56/epic.jpg',
        'icon_file_data_id' => 9,
    ]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSlotIndexes($client, ['HEAD' => [321]]);
    mockHighestItemId($client, 10);
    // L'item 20 n'est plus servi par l'API : le balayage ne voit que le 10, moins bon.
    mockSearchWindows($client, 'data/wow/search/item', [0 => [itemDocument(10, 'Heaume rare', 'RARE', [321])]]);
    mockSearchWindows($client, 'data/wow/search/media', []);

    resolve(AppearanceImporter::class)->import();

    $appearance = WowAppearance::query()->find(321);
    expect($appearance->item_id)->toBe(20)
        ->and($appearance->name_fr)->toBe('Heaume épique')
        ->and($appearance->quality)->toBe(4);
});

test('a row still without any representative takes the one the sweep finds', function (): void {
    WowAppearance::factory()->create([
        'id' => 321,
        'name_fr' => 'Apparence sans item',
        'slot' => 'HEAD',
        'quality' => null,
        'item_id' => null,
        'icon_url' => null,
    ]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSlotIndexes($client, ['HEAD' => [321]]);
    mockHighestItemId($client, 10);
    mockSearchWindows($client, 'data/wow/search/item', [0 => [itemDocument(10, 'Heaume commun', 'COMMON', [321])]]);
    mockSearchWindows($client, 'data/wow/search/media', [0 => [mediaDocument(10, 'https://render.worldofwarcraft.com/eu/icons/56/a.jpg', 1)]]);

    resolve(AppearanceImporter::class)->import();

    $appearance = WowAppearance::query()->find(321);
    expect($appearance->item_id)->toBe(10)
        ->and($appearance->name_fr)->toBe('Heaume commun')
        ->and($appearance->icon_url)->toBe('https://render.worldofwarcraft.com/eu/icons/56/a.jpg');
});

test('an item the media search does not know leaves its appearance without icon', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSlotIndexes($client, ['HEAD' => [321]]);
    mockHighestItemId($client, 10);
    mockSearchWindows($client, 'data/wow/search/item', [0 => [itemDocument(10, 'Heaume', 'RARE', [321])]]);
    // La fenêtre répond, mais sur d'autres identifiants : environ un tiers des items
    // n'a pas de media exploitable.
    mockSearchWindows($client, 'data/wow/search/media', [0 => [mediaDocument(11, 'https://render.worldofwarcraft.com/eu/icons/56/autre.jpg', 2)]]);

    resolve(AppearanceImporter::class)->import();

    expect(WowAppearance::query()->find(321)->icon_url)->toBeNull();
});

test('a full refresh writes nothing when every icon is already correct', function (): void {
    WowAppearance::factory()->create([
        'id' => 321,
        'name_fr' => 'Heaume',
        'slot' => 'HEAD',
        'category' => 'Armure',
        'quality' => 3,
        'item_id' => 10,
        'icon_url' => 'https://render.worldofwarcraft.com/eu/icons/56/a.jpg',
        'icon_file_data_id' => 1,
    ]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSlotIndexes($client, ['HEAD' => [321]]);
    mockHighestItemId($client, 10);
    mockSearchWindows($client, 'data/wow/search/item', [0 => [itemDocument(10, 'Heaume', 'RARE', [321])]]);
    mockSearchWindows($client, 'data/wow/search/media', [0 => [mediaDocument(10, 'https://render.worldofwarcraft.com/eu/icons/56/a.jpg', 1)]]);

    \Illuminate\Support\Facades\DB::enableQueryLog();
    resolve(AppearanceImporter::class)->import(full: true);
    $writes = array_filter(
        \Illuminate\Support\Facades\DB::getQueryLog(),
        static fn (array $query): bool => str_contains((string) $query['query'], 'insert into "wow_appearances"'),
    );
    \Illuminate\Support\Facades\DB::disableQueryLog();

    expect($writes)->toBe([]);
});

test('importChunk hands back its position when the time box runs out', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSlotIndexes($client, ['HEAD' => [321]]);
    mockHighestItemId($client, 1500);
    $client->shouldNotReceive('getAsync');

    $appearanceImportProgress = resolve(AppearanceImporter::class)->importChunk(full: false, offset: 0, timeBoxSeconds: 0);

    expect($appearanceImportProgress->done)->toBeFalse()
        ->and($appearanceImportProgress->offset)->toBe(0)
        ->and($appearanceImportProgress->total)->toBe(4)
        ->and($appearanceImportProgress->secondsUntilBudget)->toBe(0);
});

test('a sweep stopped before its first window deletes nothing and keeps its offset', function (): void {
    WowAppearance::factory()->create(['id' => 999, 'is_active' => true]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSlotIndexes($client, ['HEAD' => [321]]);
    mockHighestItemId($client, 10);
    $client->shouldNotReceive('getAsync');

    $appearanceImportProgress = resolve(AppearanceImporter::class)
        ->importChunk(full: false, offset: 0, timeBoxSeconds: 600, limit: null, stopRequested: static fn (): bool => true);

    expect($appearanceImportProgress->done)->toBeFalse()
        ->and($appearanceImportProgress->offset)->toBe(0)
        ->and(WowAppearance::query()->find(999))->not->toBeNull();
});

test('a sweep stopped mid-flight keeps what it swept and spares the catalog', function (): void {
    WowAppearance::factory()->create(['id' => 999, 'is_active' => true]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSlotIndexes($client, ['HEAD' => [321]]);
    mockHighestItemId($client, 10);
    mockSearchWindows($client, 'data/wow/search/item', [0 => [itemDocument(10, 'Heaume', 'RARE', [321])]]);
    mockSearchWindows($client, 'data/wow/search/media', [0 => [mediaDocument(10, 'https://render.worldofwarcraft.com/eu/icons/56/a.jpg', 1)]]);

    $turns = 0;

    $appearanceImportProgress = resolve(AppearanceImporter::class)->importChunk(
        full: false,
        offset: 0,
        timeBoxSeconds: 600,
        limit: null,
        stopRequested: function () use (&$turns): bool {
            $turns++;

            return $turns > 1;
        },
    );

    // L'annulation en pleine entité est le cas où un bug coûte un catalogue : le rebut
    // ne se purge qu'après un balayage complet, jamais après une passe interrompue.
    expect($appearanceImportProgress->done)->toBeFalse()
        ->and($appearanceImportProgress->offset)->toBeGreaterThan(0)
        ->and(WowAppearance::query()->find(999))->not->toBeNull()
        ->and(WowAppearance::query()->find(321))->not->toBeNull();
});

test('an item carrying an internal Blizzard name never represents an appearance', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSlotIndexes($client, ['WEAPON' => [321, 322]]);
    mockHighestItemId($client, 12);
    mockSearchWindows($client, 'data/wow/search/item', [0 => [
        itemDocument(10, '[PH] Rainbow Axe - 1h - Purple', 'EPIC', [321]),
        itemDocument(11, 'Hache du grizzly', 'RARE', [321]),
        itemDocument(12, '9.0 PvP - PvP Reward - Tabard - 4', 'EPIC', [322]),
    ]]);
    mockSearchWindows($client, 'data/wow/search/media', []);

    resolve(AppearanceImporter::class)->import();

    expect(WowAppearance::query()->find(321)->name_fr)->toBe('Hache du grizzly')
        ->and(WowAppearance::query()->find(322))->toBeNull();
});

test('a stored appearance under an internal name yields to any released representative', function (): void {
    WowAppearance::factory()->create([
        'id' => 321, 'name_fr' => '[PH] Rainbow Axe - 1h - Purple', 'slot' => 'WEAPON',
        'quality' => 4, 'item_id' => 10,
    ]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSlotIndexes($client, ['WEAPON' => [321]]);
    mockHighestItemId($client, 11);
    mockSearchWindows($client, 'data/wow/search/item', [0 => [itemDocument(11, 'Hache du grizzly', 'RARE', [321])]]);
    mockSearchWindows($client, 'data/wow/search/media', []);

    resolve(AppearanceImporter::class)->import();

    expect(WowAppearance::query()->find(321))
        ->name_fr->toBe('Hache du grizzly')
        ->item_id->toBe(11);
});

test('a full sweep removes the listed appearances still named by a placeholder', function (): void {
    WowAppearance::factory()->create(['id' => 116141, 'name_fr' => '[EN] Appearance #116141', 'slot' => 'CLOAK', 'category' => null]);
    WowAppearance::factory()->create(['id' => 322, 'name_fr' => "DNT Ula'tek Pole Dummy F", 'slot' => 'WEAPON']);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSlotIndexes($client, ['CLOAK' => [116141, 321], 'WEAPON' => [322]]);
    mockHighestItemId($client, 10);
    mockSearchWindows($client, 'data/wow/search/item', [0 => [itemDocument(10, 'Cape du grizzly', 'RARE', [321])]]);
    mockSearchWindows($client, 'data/wow/search/media', []);

    resolve(AppearanceImporter::class)->import();

    expect(WowAppearance::query()->pluck('id')->all())->toBe([321]);
});
