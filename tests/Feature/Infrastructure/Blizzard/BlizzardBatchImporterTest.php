<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\BlizzardBatchImporter;
use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Models\WowAchievement;
use App\Models\WowCollectionTaxonomy;
use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;
use App\Models\WowProfession;
use App\Models\WowQuest;
use App\Models\WowRecipe;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    Sleep::fake();
});

// ─── Quest Import ───────────────────────────────────────────

test('importQuests creates quests with expansion and zone maps', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);

    // Area index with 2 areas
    $mock->shouldReceive('get')
        ->with('data/wow/quest/area/index', \Mockery::any())
        ->andReturn(['areas' => [
            ['id' => 10, 'name' => 'Durotar'],
            ['id' => 20, 'name' => 'Nagrand'],
        ]]);

    // Area 10: Durotar with 1 quest
    $mock->shouldReceive('getAsync')
        ->with('data/wow/quest/area/10', \Mockery::any())
        ->andReturn(Create::promiseFor(new Response(200, [], json_encode([
            'area' => ['name' => 'Durotar'],
            'quests' => [['id' => 100, 'name' => 'Quête de Durotar']],
        ]))));

    // Area 20: Nagrand with 2 quests
    $mock->shouldReceive('getAsync')
        ->with('data/wow/quest/area/20', \Mockery::any())
        ->andReturn(Create::promiseFor(new Response(200, [], json_encode([
            'area' => ['name' => 'Nagrand'],
            'quests' => [
                ['id' => 101, 'name' => 'Quête de Nagrand'],
                ['id' => 200, 'name' => 'Quête sans expansion'],
            ],
        ]))));

    $areaExpansionMap = [10 => 0, 20 => 1];

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importQuests($areaExpansionMap);

    expect(WowQuest::query()->count())->toBe(3);
    expect(WowQuest::query()->find(100)->expansion_id)->toBe(0);
    expect(WowQuest::query()->find(100)->zone_name)->toBe('Durotar');
    expect(WowQuest::query()->find(101)->expansion_id)->toBe(1);
    expect(WowQuest::query()->find(101)->zone_name)->toBe('Nagrand');
    expect(WowQuest::query()->find(200)->expansion_id)->toBe(1); // fallback to area expansion
    expect(WowQuest::query()->find(200)->zone_name)->toBe('Nagrand');
});

test('importQuests uses ContentTuning expansion over area expansion', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);

    $mock->shouldReceive('get')
        ->with('data/wow/quest/area/index', \Mockery::any())
        ->andReturn(['areas' => [['id' => 10, 'name' => 'Zone']]]);

    $mock->shouldReceive('getAsync')
        ->with('data/wow/quest/area/10', \Mockery::any())
        ->andReturn(Create::promiseFor(new Response(200, [], json_encode([
            'area' => ['name' => 'Durotar'],
            'quests' => [
                ['id' => 500, 'name' => 'Quête TWW'],
                ['id' => 501, 'name' => 'Quête Midnight'],
            ],
        ]))));

    $areaExpansionMap = [10 => 0]; // area says Classic
    $questExpansionMap = [500 => 10, 501 => 11]; // ContentTuning says TWW/Midnight

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importQuests($areaExpansionMap, $questExpansionMap);

    expect(WowQuest::query()->find(500)->expansion_id)->toBe(10); // CT wins
    expect(WowQuest::query()->find(501)->expansion_id)->toBe(11); // CT wins
});

test('importQuests assigns faction from quest and zone faction maps', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);

    $mock->shouldReceive('get')
        ->with('data/wow/quest/area/index', \Mockery::any())
        ->andReturn(['areas' => [['id' => 10, 'name' => 'Zone']]]);

    $mock->shouldReceive('getAsync')
        ->with('data/wow/quest/area/10', \Mockery::any())
        ->andReturn(Create::promiseFor(new Response(200, [], json_encode([
            'area' => ['name' => 'Hurlevent'],
            'quests' => [
                ['id' => 100, 'name' => 'Quête Alliance'],
                ['id' => 101, 'name' => 'Quête zone'],
            ],
        ]))));

    $questFactionMap = [100 => 'Alliance'];
    $zoneFactionMap = [10 => 'Alliance'];

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importQuests([], [], $questFactionMap, $zoneFactionMap);

    expect(WowQuest::query()->find(100)->faction)->toBe('Alliance'); // from questFactionMap
    expect(WowQuest::query()->find(101)->faction)->toBe('Alliance'); // from zoneFactionMap
});

test('importQuests handles empty area index', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);

    $mock->shouldReceive('get')
        ->with('data/wow/quest/area/index', \Mockery::any())
        ->andReturn(['areas' => []]);

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importQuests([]);

    expect(WowQuest::query()->count())->toBe(0);
});

test('importQuests defaults unmapped areas to expansion 0', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);

    $mock->shouldReceive('get')
        ->with('data/wow/quest/area/index', \Mockery::any())
        ->andReturn(['areas' => [['id' => 99, 'name' => 'Unknown']]]);

    $mock->shouldReceive('getAsync')
        ->with('data/wow/quest/area/99', \Mockery::any())
        ->andReturn(Create::promiseFor(new Response(200, [], json_encode([
            'area' => ['name' => 'Unknown Zone'],
            'quests' => [['id' => 100, 'name' => 'Quête orpheline']],
        ]))));

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importQuests([]); // no area expansion map

    expect(WowQuest::query()->find(100)->expansion_id)->toBe(0);
});

// ─── Achievement Import ─────────────────────────────────────

test('importAchievements builds the catalog from the category hierarchy', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);

    $mock->shouldReceive('get')
        ->with('data/wow/achievement-category/index', \Mockery::any())
        ->andReturn(['categories' => [['id' => 96, 'name' => 'Quêtes'], ['id' => 15547, 'name' => 'Midnight']]]);
    bbiMockAsync($mock, 'data/wow/achievement-category/96', ['id' => 96, 'name' => 'Quêtes']);
    bbiMockAsync($mock, 'data/wow/achievement-category/15547', [
        'id' => 15547,
        'name' => 'Midnight',
        'parent_category' => ['id' => 96, 'name' => 'Quêtes'],
        'achievements' => [['id' => 41802, 'name' => 'Reprise des Chants éternels']],
    ]);
    bbiMockAsync($mock, 'data/wow/achievement/41802', ['id' => 41802, 'points' => 25]);
    bbiMockAsync($mock, 'data/wow/search/media', ['results' => [['data' => ['id' => 41802, 'assets' => [['key' => 'icon', 'value' => 'https://render.worldofwarcraft.com/eu/icons/56/41802.jpg']]]]]]);

    resolve(BlizzardBatchImporter::class)->importAchievements();

    $wowAchievement = WowAchievement::query()->findOrFail(41802);
    expect($wowAchievement->name_fr)->toBe('Reprise des Chants éternels')
        ->and($wowAchievement->category_name)->toBe('Quêtes')
        ->and($wowAchievement->expansion_id)->toBe(11)
        ->and($wowAchievement->points)->toBe(25)
        ->and($wowAchievement->icon_url)->toBe('https://render.worldofwarcraft.com/eu/icons/56/41802.jpg');
});

test('importAchievements leaves the catalog untouched when the hierarchy is unreachable', function (): void {
    WowAchievement::query()->create(['id' => 10, 'name_fr' => 'Achievement existant', 'expansion_id' => 0, 'category_name' => 'Quêtes', 'points' => 10, 'is_active' => true]);

    $mock = $this->mock(BlizzardApiClient::class);
    $mock->shouldReceive('get')->andThrow(new \Exception('API error: 500 Internal Server Error'));
    $mock->shouldNotReceive('getAsync');

    resolve(BlizzardBatchImporter::class)->importAchievements();

    expect(WowAchievement::query()->count())->toBe(1)
        ->and(WowAchievement::query()->findOrFail(10)->name_fr)->toBe('Achievement existant');
});

// ─── Mount Import ───────────────────────────────────────────

test('importMounts takes the identity from the API and the ranking from the taxonomy', function (): void {
    bbiCurate(CollectionEntity::Mount, [1 => ['Classic', 'Reputation'], 2 => ['Classic', 'Reputation']]);
    DB::table('wow_ref_mount')->insert([['id' => 1, 'source_spell_id' => 12345]]);
    DB::table('wow_ref_spell_misc')->insert([['id' => 1, 'spell_id' => 12345, 'spell_icon_file_data_id' => 132261]]);
    Http::fake(['render.worldofwarcraft.com/*' => Http::response('', 200)]);

    $mock = $this->mock(BlizzardApiClient::class);
    bbiMockNameIndex($mock, 'mount/index', 'mounts', [1 => 'Loup noir', 2 => 'Destrier squelette']);
    bbiMockSearchSweep($mock, 'data/wow/search/mount', [
        ['id' => 1, 'name' => ['fr_FR' => 'Loup noir'], 'source' => ['type' => 'VENDOR']],
    ]);

    resolve(BlizzardBatchImporter::class)->importMounts();

    expect(WowMount::query()->count())->toBe(2)
        ->and(WowMount::query()->findOrFail(1)->name_fr)->toBe('Loup noir')
        ->and(WowMount::query()->findOrFail(1)->category)->toBe('Classic')
        ->and(WowMount::query()->findOrFail(1)->source)->toBe('Reputation')
        ->and(WowMount::query()->findOrFail(1)->source_spell_id)->toBe(12345)
        ->and(WowMount::query()->findOrFail(1)->icon_url)->toBe('https://render.worldofwarcraft.com/eu/icons/56/132261.jpg')
        ->and(WowMount::query()->findOrFail(2)->name_fr)->toBe('Destrier squelette');
});

// ─── Pet Import ─────────────────────────────────────────────

test('importPets takes the identity from the API detail and the ranking from the taxonomy', function (): void {
    bbiCurate(CollectionEntity::Pet, [1 => ['Classic', 'Drop'], 2 => ['Classic', 'Drop']]);

    $mock = $this->mock(BlizzardApiClient::class);
    bbiMockNameIndex($mock, 'pet/index', 'pets', [1 => 'Dragonnet', 2 => 'Petit chat']);
    bbiMockPetDetail($mock, 1, ['id' => 1, 'name' => 'Dragonnet', 'creature' => ['id' => 9999], 'icon' => 'https://render.worldofwarcraft.com/eu/icons/56/136118.jpg']);
    bbiMockPetDetail($mock, 2, ['id' => 2, 'name' => 'Petit chat', 'creature' => ['id' => 8888]]);

    resolve(BlizzardBatchImporter::class)->importPets();

    expect(WowPet::query()->count())->toBe(2)
        ->and(WowPet::query()->findOrFail(1)->name_fr)->toBe('Dragonnet')
        ->and(WowPet::query()->findOrFail(1)->creature_id)->toBe(9999)
        ->and(WowPet::query()->findOrFail(1)->category)->toBe('Classic')
        ->and(WowPet::query()->findOrFail(1)->source)->toBe('Drop')
        ->and(WowPet::query()->findOrFail(1)->icon_url)->toBe('https://render.worldofwarcraft.com/eu/icons/56/136118.jpg')
        ->and(WowPet::query()->findOrFail(2)->name_fr)->toBe('Petit chat');
});

// ─── Decor Import ───────────────────────────────────────────

test('importDecor binds a decor to its item and to the icon of that item', function (): void {
    bbiCurate(CollectionEntity::Decor, [1 => ['The War Within', 'Quest'], 2 => ['The War Within', 'Quest']]);

    $mock = $this->mock(BlizzardApiClient::class);
    bbiMockNameIndex($mock, 'decor/index', 'decor_items', [1 => 'Foyer orné', 2 => 'Tapis elfique']);
    bbiMockSearchSweep($mock, 'data/wow/search/decor', [
        ['id' => 1, 'name' => ['fr_FR' => 'Foyer orné'], 'item' => ['id' => 245000]],
        ['id' => 2, 'name' => ['fr_FR' => 'Tapis elfique'], 'item' => ['id' => 245001]],
    ]);
    bbiMockItemMedia($mock, [245000 => 'https://render.worldofwarcraft.com/eu/icons/56/135234.jpg']);

    resolve(BlizzardBatchImporter::class)->importDecor();

    expect(WowDecor::query()->count())->toBe(2)
        ->and(WowDecor::query()->findOrFail(1)->name_fr)->toBe('Foyer orné')
        ->and(WowDecor::query()->findOrFail(1)->category)->toBe('The War Within')
        ->and(WowDecor::query()->findOrFail(1)->source)->toBe('Quest')
        ->and(WowDecor::query()->findOrFail(1)->item_id)->toBe(245000)
        ->and(WowDecor::query()->findOrFail(1)->icon_url)->toBe('https://render.worldofwarcraft.com/eu/icons/56/135234.jpg');
});

test('importDecor deactivates a decor the curation marks as no longer obtainable', function (): void {
    bbiCurate(CollectionEntity::Decor, [1 => ['The War Within', 'Quest']]);
    WowCollectionTaxonomy::factory()->create([
        'entity' => CollectionEntity::Decor,
        'entry_id' => 10,
        'category' => 'Undiscovered',
        'source' => 'Undiscovered Sources',
        'obtainable' => false,
    ]);

    $mock = $this->mock(BlizzardApiClient::class);
    bbiMockNameIndex($mock, 'decor/index', 'decor_items', [1 => 'Foyer orné', 10 => 'Décor caché']);
    bbiMockSearchSweep($mock, 'data/wow/search/decor', []);
    bbiMockItemMedia($mock, []);

    resolve(BlizzardBatchImporter::class)->importDecor();

    expect(WowDecor::query()->count())->toBe(2)
        ->and(WowDecor::query()->findOrFail(1)->is_active)->toBeTrue()
        ->and(WowDecor::query()->findOrFail(10)->is_active)->toBeFalse()
        ->and(WowDecor::query()->findOrFail(10)->category)->toBe('Undiscovered');
});

// ─── Profession Import ──────────────────────────────────────

test('importProfessions creates professions and recipes from the API', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);
    bbiMockProfessionApi($mock, 171, 'Alchimie', 'PRIMARY', 2871, 'Alchimie de Khaz Algar', [
        ['name' => 'Potions', 'recipes' => [
            ['id' => 5001, 'name' => 'Potion de vie'],
            ['id' => 5002, 'name' => 'Potion de mana'],
        ]],
    ]);

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importProfessions();

    expect(WowProfession::query()->count())->toBe(1);
    expect(WowProfession::query()->find(171)->name_fr)->toBe('Alchimie');
    expect(WowProfession::query()->find(171)->type)->toBe('primary');

    expect(WowRecipe::query()->count())->toBe(2);
    expect(WowRecipe::query()->find(5001)->name_fr)->toBe('Potion de vie');
    expect(WowRecipe::query()->find(5001)->expansion_id)->toBe(10); // Khaz Algar = TWW
    expect(WowRecipe::query()->find(5001)->category_name)->toBe('Potions');
});

test('importProfessions assigns recipe factions from faction map', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);
    bbiMockProfessionApi($mock, 171, 'Alchimie', 'PRIMARY', 2871, 'Alchimie classique', [
        ['name' => 'Potions classiques', 'recipes' => [
            ['id' => 5001, 'name' => 'Potion Alliance'],
        ]],
    ]);

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importProfessions([5001 => 'Alliance']);

    expect(WowRecipe::query()->find(5001)->faction)->toBe('Alliance');
});

// ─── Mirror Quest Faction Tagging ───────────────────────────

test('tagMirrorQuestFactions tags mirror quest pairs via API reputation', function (): void {
    // Create mirror pair: same name + zone, no faction
    WowQuest::factory()->create([
        'id' => 100,
        'name_fr' => 'Mission de guerre',
        'zone_name' => 'Vallée de Tiragarde',
        'expansion_id' => 7,
        'faction' => null,
        'is_active' => true,
    ]);
    WowQuest::factory()->create([
        'id' => 101,
        'name_fr' => 'Mission de guerre',
        'zone_name' => 'Vallée de Tiragarde',
        'expansion_id' => 7,
        'faction' => null,
        'is_active' => true,
    ]);

    $mock = $this->mock(BlizzardApiClient::class);

    // Quest 100 returns a Horde reputation
    $mock->shouldReceive('getAsync')
        ->with('data/wow/quest/100', \Mockery::any())
        ->andReturn(Create::promiseFor(new Response(200, [], json_encode([
            'rewards' => [
                'reputations' => [
                    ['reward' => ['id' => 2103]], // Zandalari
                ],
            ],
        ]))));

    // Quest 101 returns no faction reputation
    $mock->shouldReceive('getAsync')
        ->with('data/wow/quest/101', \Mockery::any())
        ->andReturn(Create::promiseFor(new Response(200, [], json_encode([
            'rewards' => [],
        ]))));

    $reputationFactionMap = [2103 => 'Horde'];

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->tagMirrorQuestFactions($reputationFactionMap);

    expect(WowQuest::query()->find(100)->faction)->toBe('Horde');
    expect(WowQuest::query()->find(101)->faction)->toBe('Alliance');
});

// ─── Mirror Recipe Faction Tagging ──────────────────────────

test('tagMirrorRecipeFactions tags untagged recipe in mirror pair', function (): void {
    $profession = WowProfession::factory()->create(['id' => 171]);

    WowRecipe::factory()->create([
        'id' => 5001,
        'name_fr' => 'Potion spéciale',
        'profession_id' => 171,
        'expansion_id' => 7,
        'faction' => 'Alliance',
        'is_active' => true,
    ]);
    WowRecipe::factory()->create([
        'id' => 5002,
        'name_fr' => 'Potion spéciale',
        'profession_id' => 171,
        'expansion_id' => 7,
        'faction' => null,
        'is_active' => true,
    ]);

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->tagMirrorRecipeFactions();

    expect(WowRecipe::query()->find(5002)->faction)->toBe('Horde');
});

/**
 * Range des entrées dans la taxonomie curée, d'où les importers tirent leur rangement.
 *
 * @param  array<int, array{0: string|null, 1: string|null}>  $rankings  identifiant => [catégorie, source]
 */
function bbiCurate(CollectionEntity $collectionEntity, array $rankings): void
{
    foreach ($rankings as $entryId => $ranking) {
        WowCollectionTaxonomy::factory()->create([
            'entity' => $collectionEntity,
            'entry_id' => $entryId,
            'category' => $ranking[0],
            'source' => $ranking[1],
        ]);
    }
}

/**
 * Mocke un balayage de recherche par fenêtres d'identifiants.
 *
 * @param  list<array<string, mixed>>  $documents
 */
function bbiMockSearchSweep(\Mockery\MockInterface $mock, string $endpoint, array $documents): void
{
    $mock->shouldReceive('getAsync')
        ->withArgs(fn (string $requested): bool => str_starts_with($requested, $endpoint))
        ->andReturnUsing(fn (): \GuzzleHttp\Promise\PromiseInterface => Create::promiseFor(new Response(200, [], (string) json_encode([
            'results' => array_map(static fn (array $document): array => ['data' => $document], $documents),
        ]))));
}

/**
 * Mocke le balayage des media d'items, d'où viennent les icônes des décorations.
 *
 * @param  array<int, string>  $icons  [item_id => icon_url]
 */
function bbiMockItemMedia(\Mockery\MockInterface $mock, array $icons): void
{
    $mock->shouldReceive('getAsync')
        ->withArgs(fn (string $requested): bool => str_starts_with($requested, 'data/wow/search/media')
            && str_contains($requested, 'tags=item'))
        ->andReturnUsing(fn (): \GuzzleHttp\Promise\PromiseInterface => Create::promiseFor(new Response(200, [], (string) json_encode([
            'results' => array_map(
                static fn (string $url, int $id): array => ['data' => ['id' => $id, 'assets' => [['key' => 'icon', 'value' => $url]]]],
                $icons,
                array_keys($icons),
            ),
        ]))));
}

/**
 * Mocke le détail d'une mascotte.
 *
 * @param  array<string, mixed>  $detail
 */
function bbiMockPetDetail(\Mockery\MockInterface $mock, int $id, array $detail): void
{
    $mock->shouldReceive('getAsync')
        ->withArgs(fn (string $requested): bool => $requested === 'data/wow/pet/'.$id)
        ->andReturnUsing(fn (): \GuzzleHttp\Promise\PromiseInterface => Create::promiseFor(new Response(200, [], (string) json_encode($detail))));
}

/**
 * Mocke un index API id → nom (mount/index, pet/index, achievement/index, decor/index).
 *
 * @param  array<int, string>  $names  [id => nom FR]
 */
/**
 * Mocke une réponse asynchrone, celle des lots de l'importer.
 *
 * @param  array<string, mixed>  $payload
 */
function bbiMockAsync(\Mockery\MockInterface $mock, string $endpoint, array $payload): void
{
    $mock->shouldReceive('getAsync')
        ->withArgs(fn (string $requested): bool => str_starts_with($requested, $endpoint))
        ->andReturnUsing(fn (): \GuzzleHttp\Promise\PromiseInterface => Create::promiseFor(new Response(200, [], (string) json_encode($payload))));
}

function bbiMockNameIndex(\Mockery\MockInterface $mock, string $endpoint, string $listKey, array $names): void
{
    $entries = [];
    foreach ($names as $id => $name) {
        $entries[] = ['id' => $id, 'name' => $name];
    }

    $mock->shouldReceive('get')
        ->with('data/wow/'.$endpoint, \Mockery::any())
        ->andReturn([$listKey => $entries]);
}

/**
 * Mocke le pipeline Profession API complet : index → détail → skill tier.
 *
 * @param  list<array{name: string, recipes: list<array{id: int, name: string}>}>  $categories
 */
function bbiMockProfessionApi(\Mockery\MockInterface $mock, int $professionId, string $name, string $type, int $tierId, string $tierName, array $categories): void
{
    $mock->shouldReceive('get')
        ->with('data/wow/profession/index', \Mockery::any())
        ->andReturn(['professions' => [['id' => $professionId, 'name' => $name]]]);

    $mock->shouldReceive('getAsync')
        ->with('data/wow/profession/'.$professionId, \Mockery::any())
        ->andReturnUsing(fn (): \GuzzleHttp\Promise\PromiseInterface => Create::promiseFor(new Response(200, [], (string) json_encode([
            'id' => $professionId,
            'name' => $name,
            'type' => ['type' => $type, 'name' => 'x'],
            'skill_tiers' => [['id' => $tierId, 'name' => $tierName]],
        ]))));

    $mock->shouldReceive('getAsync')
        ->with(sprintf('data/wow/profession/%d/skill-tier/%d', $professionId, $tierId), \Mockery::any())
        ->andReturnUsing(fn (): \GuzzleHttp\Promise\PromiseInterface => Create::promiseFor(new Response(200, [], (string) json_encode([
            'id' => $tierId,
            'categories' => $categories,
        ]))));
}
