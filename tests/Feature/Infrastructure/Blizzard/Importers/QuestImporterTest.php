<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Importers\QuestImporter;
use App\Models\WowQuest;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    Sleep::fake();
});

test('it imports quests from API', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    $client->shouldReceive('get')
        ->with('data/wow/quest/area/index', \Mockery::any())
        ->andReturn(['areas' => [
            ['id' => 10, 'name' => 'Durotar'],
            ['id' => 20, 'name' => 'Nagrand'],
        ]]);

    $client->shouldReceive('getAsync')
        ->with('data/wow/quest/area/10', \Mockery::any())
        ->andReturn(Create::promiseFor(new Response(200, [], json_encode([
            'area' => ['name' => 'Durotar'],
            'quests' => [['id' => 100, 'name' => 'Quete de Durotar']],
        ]))));

    $client->shouldReceive('getAsync')
        ->with('data/wow/quest/area/20', \Mockery::any())
        ->andReturn(Create::promiseFor(new Response(200, [], json_encode([
            'area' => ['name' => 'Nagrand'],
            'quests' => [
                ['id' => 101, 'name' => 'Quete de Nagrand'],
                ['id' => 102, 'name' => 'Quete secondaire'],
            ],
        ]))));

    $areaExpansionMap = [10 => 0, 20 => 1];

    $questImporter = resolve(QuestImporter::class);
    $questImporter->import($areaExpansionMap);

    expect(WowQuest::query()->count())->toBe(3);
    expect(WowQuest::query()->find(100)->name_fr)->toBe('Quete de Durotar');
    expect(WowQuest::query()->find(100)->expansion_id)->toBe(0);
    expect(WowQuest::query()->find(100)->zone_name)->toBe('Durotar');
    expect(WowQuest::query()->find(101)->expansion_id)->toBe(1);
    expect(WowQuest::query()->find(101)->zone_name)->toBe('Nagrand');
    expect(WowQuest::query()->find(102)->zone_name)->toBe('Nagrand');
});

test('it returns early when API fails', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    $client->shouldReceive('get')
        ->with('data/wow/quest/area/index', \Mockery::any())
        ->andThrow(new \Exception('API error: 500 Internal Server Error'));

    $questImporter = resolve(QuestImporter::class);
    $questImporter->import([]);

    expect(WowQuest::query()->count())->toBe(0);
});

test('it tags mirror quest factions', function (): void {
    WowQuest::factory()->create([
        'id' => 100,
        'name_fr' => 'Mission de guerre',
        'zone_name' => 'Vallee de Tiragarde',
        'expansion_id' => 7,
        'faction' => null,
        'is_active' => true,
    ]);
    WowQuest::factory()->create([
        'id' => 101,
        'name_fr' => 'Mission de guerre',
        'zone_name' => 'Vallee de Tiragarde',
        'expansion_id' => 7,
        'faction' => null,
        'is_active' => true,
    ]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    // Quest 100 returns a Horde reputation
    $client->shouldReceive('getAsync')
        ->with('data/wow/quest/100', \Mockery::any())
        ->andReturn(Create::promiseFor(new Response(200, [], json_encode([
            'rewards' => [
                'reputations' => [
                    ['reward' => ['id' => 2103]],
                ],
            ],
        ]))));

    // Quest 101 returns no faction reputation
    $client->shouldReceive('getAsync')
        ->with('data/wow/quest/101', \Mockery::any())
        ->andReturn(Create::promiseFor(new Response(200, [], json_encode([
            'rewards' => [],
        ]))));

    $reputationFactionMap = [2103 => 'Horde'];

    $questImporter = resolve(QuestImporter::class);
    $questImporter->tagMirrorFactions($reputationFactionMap);

    expect(WowQuest::query()->find(100)->faction)->toBe('Horde');
    expect(WowQuest::query()->find(101)->faction)->toBe('Alliance');
});

// ─── Réécriture des lignes inchangées ───────────────────────

/**
 * @param  list<array{id: int, name: string}>  $quests
 */
function questApiReturning(array $quests): void
{
    /** @var BlizzardApiClient&\Mockery\MockInterface $mock */
    $mock = \Mockery::mock(BlizzardApiClient::class);
    app()->instance(BlizzardApiClient::class, $mock);

    $mock->shouldReceive('get')
        ->with('data/wow/quest/area/index', \Mockery::any())
        ->andReturn(['areas' => [['id' => 10, 'name' => 'Durotar']]]);

    $mock->shouldReceive('getAsync')
        ->with('data/wow/quest/area/10', \Mockery::any())
        ->andReturn(Create::promiseFor(new Response(200, [], json_encode([
            'area' => ['name' => 'Durotar'],
            'quests' => $quests,
        ]))));
}

/**
 * `updated_at` ne peut pas servir de preuve ici : la colonne n'a pas de précision
 * sous la seconde, et deux passes de test tombent dans la même. On compte donc les
 * écritures réellement émises.
 */
function questWritesDuring(callable $callback): int
{
    $writes = 0;

    DB::listen(function (Illuminate\Database\Events\QueryExecuted $queryExecuted) use (&$writes): void {
        if (str_contains($queryExecuted->sql, 'wow_quests') && ! str_starts_with($queryExecuted->sql, 'select')) {
            $writes++;
        }
    });

    $callback();

    return $writes;
}

test('a second pass over unchanged quests writes nothing', function (): void {
    questApiReturning([['id' => 100, 'name' => 'Quete de Durotar']]);
    resolve(QuestImporter::class)->import([10 => 0]);

    questApiReturning([['id' => 100, 'name' => 'Quete de Durotar']]);
    $writes = questWritesDuring(fn () => resolve(QuestImporter::class)->import([10 => 0]));

    expect($writes)->toBe(0);
});

test('a quest whose name changed upstream is rewritten', function (): void {
    questApiReturning([['id' => 100, 'name' => 'Ancien nom']]);
    resolve(QuestImporter::class)->import([10 => 0]);

    questApiReturning([['id' => 100, 'name' => 'Nouveau nom']]);
    resolve(QuestImporter::class)->import([10 => 0]);

    expect(WowQuest::query()->find(100)->name_fr)->toBe('Nouveau nom');
});

test('a faction resolved by the mirror tagger survives the next import', function (): void {
    questApiReturning([['id' => 100, 'name' => 'Quete de Durotar']]);
    resolve(QuestImporter::class)->import([10 => 0]);

    // Ce que fait tagMirrorQuestFactions : il écrit une faction que les cartes de
    // référence ne portent pas, déduite des récompenses de réputation.
    WowQuest::query()->where('id', 100)->update(['faction' => 'Alliance']);

    questApiReturning([['id' => 100, 'name' => 'Quete de Durotar']]);
    resolve(QuestImporter::class)->import([10 => 0]);

    expect(WowQuest::query()->find(100)->faction)->toBe('Alliance');
});

test('a faction carried by the reference maps still wins over the stored one', function (): void {
    questApiReturning([['id' => 100, 'name' => 'Quete de Durotar']]);
    resolve(QuestImporter::class)->import([10 => 0]);

    WowQuest::query()->where('id', 100)->update(['faction' => 'Alliance']);

    questApiReturning([['id' => 100, 'name' => 'Quete de Durotar']]);
    resolve(QuestImporter::class)->import([10 => 0], questFactionMap: [100 => 'Horde']);

    expect(WowQuest::query()->find(100)->faction)->toBe('Horde');
});

test('a quest carrying an internal Blizzard name is not imported', function (): void {
    questApiReturning([
        ['id' => 100, 'name' => 'Test de courage'],
        ['id' => 101, 'name' => '[PH] Acheter une bride'],
        ['id' => 102, 'name' => 'Le héraut <NYI>'],
    ]);

    resolve(QuestImporter::class)->import([10 => 0]);

    expect(WowQuest::query()->pluck('name_fr')->all())->toBe(['Test de courage']);
});

test('a stored quest carrying an internal Blizzard name is removed on the next import', function (): void {
    WowQuest::query()->create(['id' => 101, 'name_fr' => '[PÉRIMÉ]Semez votre graine', 'expansion_id' => 0, 'is_active' => true]);
    questApiReturning([
        ['id' => 100, 'name' => 'Quete de Durotar'],
        ['id' => 101, 'name' => '[PÉRIMÉ]Semez votre graine'],
    ]);

    resolve(QuestImporter::class)->import([10 => 0]);

    expect(WowQuest::query()->pluck('id')->all())->toBe([100]);
});
