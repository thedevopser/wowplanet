<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Importers\QuestImporter;
use App\Models\WowQuest;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;
use Mockery\MockInterface;

beforeEach(function (): void {
    Sleep::fake();
});

test('it imports quests from API', function (): void {
    /** @var BlizzardApiClient|MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    $client->shouldReceive('get')
        ->with('data/wow/quest/area/index', Mockery::any())
        ->andReturn(['areas' => [
            ['id' => 10, 'name' => 'Durotar'],
            ['id' => 20, 'name' => 'Nagrand'],
        ]]);

    $client->shouldReceive('getAsync')
        ->with('data/wow/quest/area/10', Mockery::any())
        ->andReturn(Create::promiseFor(new Response(200, [], json_encode([
            'area' => ['name' => 'Durotar'],
            'quests' => [['id' => 100, 'name' => 'Quete de Durotar']],
        ]))));

    $client->shouldReceive('getAsync')
        ->with('data/wow/quest/area/20', Mockery::any())
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
    /** @var BlizzardApiClient|MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    $client->shouldReceive('get')
        ->with('data/wow/quest/area/index', Mockery::any())
        ->andThrow(new Exception('API error: 500 Internal Server Error'));

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

    /** @var BlizzardApiClient|MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    // Quest 100 returns a Horde reputation
    $client->shouldReceive('getAsync')
        ->with('data/wow/quest/100', Mockery::any())
        ->andReturn(Create::promiseFor(new Response(200, [], json_encode([
            'rewards' => [
                'reputations' => [
                    ['reward' => ['id' => 2103]],
                ],
            ],
        ]))));

    // Quest 101 returns no faction reputation
    $client->shouldReceive('getAsync')
        ->with('data/wow/quest/101', Mockery::any())
        ->andReturn(Create::promiseFor(new Response(200, [], json_encode([
            'rewards' => [],
        ]))));

    $reputationFactionMap = [2103 => 'Horde'];

    $questImporter = resolve(QuestImporter::class);
    $questImporter->tagMirrorFactions($reputationFactionMap, force: false);

    expect(WowQuest::query()->find(100)->faction)->toBe('Horde');
    expect(WowQuest::query()->find(101)->faction)->toBe('Alliance');
});

// ─── Réécriture des lignes inchangées ───────────────────────

/**
 * @param  list<array{id: int, name: string}>  $quests
 */
function questApiReturning(array $quests): void
{
    /** @var BlizzardApiClient&MockInterface $mock */
    $mock = Mockery::mock(BlizzardApiClient::class);
    app()->instance(BlizzardApiClient::class, $mock);

    $mock->shouldReceive('get')
        ->with('data/wow/quest/area/index', Mockery::any())
        ->andReturn(['areas' => [['id' => 10, 'name' => 'Durotar']]]);

    $mock->shouldReceive('getAsync')
        ->with('data/wow/quest/area/10', Mockery::any())
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

    DB::listen(function (QueryExecuted $queryExecuted) use (&$writes): void {
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

// ─── Verdict des paires miroir sans réputation ──────────────

const MIRROR_REPUTATIONS = [2103 => 'Horde'];

function mirrorQuest(int $id, ?string $unresolvedAt = null): void
{
    WowQuest::factory()->create([
        'id' => $id,
        'name_fr' => 'Mission de guerre',
        'zone_name' => 'Vallee de Tiragarde',
        'faction' => null,
        'is_active' => true,
    ]);

    WowQuest::query()->where('id', $id)->update(['mirror_unresolved_at' => $unresolvedAt]);
}

/**
 * Chaque détail attendu doit être demandé une fois, et aucun autre ne doit l'être.
 *
 * @param  array<int, list<int>|null>  $rewardedReputations  [questId => reputation ids], null for a detail the API does not serve
 */
function questDetailsAnswering(array $rewardedReputations): void
{
    /** @var BlizzardApiClient&MockInterface $mock */
    $mock = Mockery::mock(BlizzardApiClient::class);
    app()->instance(BlizzardApiClient::class, $mock);

    foreach ($rewardedReputations as $questId => $reputationIds) {
        $mock->shouldReceive('getAsync')
            ->once()
            ->with('data/wow/quest/'.$questId, Mockery::any())
            ->andReturn($reputationIds === null
                ? Create::rejectionFor(new RuntimeException('404 Not Found'))
                : Create::promiseFor(new Response(200, [], json_encode([
                    'rewards' => ['reputations' => array_map(
                        static fn (int $reputationId): array => ['reward' => ['id' => $reputationId]],
                        $reputationIds,
                    )],
                ]))));
    }
}

function tagMirrorQuests(bool $force = false): void
{
    resolve(QuestImporter::class)->tagMirrorFactions(MIRROR_REPUTATIONS, $force);
}

function mirrorVerdict(int $id): ?string
{
    return WowQuest::query()->findOrFail($id)->mirror_unresolved_at?->toDateTimeString();
}

test('a mirror pair answered without reputation data is not asked again', function (): void {
    $this->travelTo('2026-10-12 10:00:00');
    mirrorQuest(100);
    mirrorQuest(101);

    questDetailsAnswering([100 => [], 101 => []]);
    tagMirrorQuests();

    expect(mirrorVerdict(100))->toBe('2026-10-12 10:00:00')
        ->and(mirrorVerdict(101))->toBe('2026-10-12 10:00:00');

    questDetailsAnswering([]);
    tagMirrorQuests();
});

test('a reputation the socle does not know leaves the pair without a faction and judged', function (): void {
    mirrorQuest(100);
    mirrorQuest(101);

    questDetailsAnswering([100 => [9999], 101 => []]);
    tagMirrorQuests();

    expect(WowQuest::query()->findOrFail(100)->faction)->toBeNull()
        ->and(mirrorVerdict(100))->not->toBeNull();
});

test('a mirror pair whose details did not answer is asked again', function (): void {
    mirrorQuest(100);
    mirrorQuest(101);

    questDetailsAnswering([100 => null, 101 => null]);
    tagMirrorQuests();

    expect(mirrorVerdict(100))->toBeNull()
        ->and(mirrorVerdict(101))->toBeNull();

    questDetailsAnswering([100 => [], 101 => []]);
    tagMirrorQuests();
});

test('a mirror pair with a single answered detail is asked again', function (int $unanswered): void {
    mirrorQuest(100);
    mirrorQuest(101);

    questDetailsAnswering([100 => [], 101 => [], $unanswered => null]);
    tagMirrorQuests();

    expect(mirrorVerdict(100))->toBeNull()
        ->and(mirrorVerdict(101))->toBeNull();

    questDetailsAnswering([100 => [], 101 => []]);
    tagMirrorQuests();
})->with([100, 101]);

test('a new quest joining a judged pair has the pair examined again', function (): void {
    mirrorQuest(100, '2026-10-01 08:00:00');
    mirrorQuest(101, '2026-10-01 08:00:00');
    mirrorQuest(102);

    questDetailsAnswering([100 => [], 101 => []]);
    tagMirrorQuests();

    expect(mirrorVerdict(102))->not->toBeNull();

    questDetailsAnswering([]);
    tagMirrorQuests();
});

test('a judged quest left alone in its group asks for nothing', function (): void {
    mirrorQuest(100, '2026-10-01 08:00:00');

    questDetailsAnswering([]);
    tagMirrorQuests(force: true);

    expect(mirrorVerdict(100))->toBe('2026-10-01 08:00:00');
});

test('a forced pass examines the judged pairs again and rewrites their verdict', function (): void {
    $this->travelTo('2026-10-12 10:00:00');
    mirrorQuest(100, '2026-10-01 08:00:00');
    mirrorQuest(101, '2026-10-01 08:00:00');

    questDetailsAnswering([100 => [], 101 => []]);
    tagMirrorQuests(force: true);

    expect(mirrorVerdict(100))->toBe('2026-10-12 10:00:00')
        ->and(mirrorVerdict(101))->toBe('2026-10-12 10:00:00');
});

test('a judged pair that a forced pass resolves gets its factions and loses its verdict', function (): void {
    mirrorQuest(100, '2026-10-01 08:00:00');
    mirrorQuest(101, '2026-10-01 08:00:00');

    questDetailsAnswering([100 => [2103], 101 => []]);
    tagMirrorQuests(force: true);

    expect(WowQuest::query()->findOrFail(100)->faction)->toBe('Horde')
        ->and(WowQuest::query()->findOrFail(101)->faction)->toBe('Alliance')
        ->and(mirrorVerdict(100))->toBeNull()
        ->and(mirrorVerdict(101))->toBeNull();
});

test('the verdict of a judged quest survives the next import untouched', function (): void {
    questApiReturning([['id' => 100, 'name' => 'Quete de Durotar']]);
    resolve(QuestImporter::class)->import([10 => 0]);
    WowQuest::query()->where('id', 100)->update(['mirror_unresolved_at' => '2026-10-01 08:00:00']);

    questApiReturning([['id' => 100, 'name' => 'Quete de Durotar']]);
    $writes = questWritesDuring(fn () => resolve(QuestImporter::class)->import([10 => 0]));

    expect($writes)->toBe(0)
        ->and(mirrorVerdict(100))->toBe('2026-10-01 08:00:00');
});
