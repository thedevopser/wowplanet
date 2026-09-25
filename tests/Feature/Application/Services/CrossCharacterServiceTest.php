<?php

declare(strict_types=1);

use App\Application\DTOs\CharacterProfileDTO;
use App\Application\DTOs\CrossCharacterProgress;
use App\Application\Services\CrossCharacterService;
use App\Application\Services\MissingBattleTagException;
use App\Application\Services\UserCharacterService;
use App\Jobs\ComputeCrossCharacterJob;
use App\Models\CrossCharacterData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    config(['services.blizzard.region' => 'eu']);
    Cache::flush();
    Cache::put('blizzard_access_token', 'app-token', 3600);
    Sleep::fake();
});

/**
 * @return list<string>
 */
function crossCharacterEndpoints(): array
{
    return ['quests/completed', 'achievements', 'reputations', 'professions'];
}

function crossCharacterUrl(string $realm, string $name, string $endpoint): string
{
    return sprintf('eu.api.blizzard.com/profile/wow/character/%s/%s/%s*', $realm, $name, $endpoint);
}

/**
 * Réponses d'un personnage qui a tout : deux quêtes, un haut-fait accompli et un en cours,
 * une réputation et une recette.
 *
 * @return array<string, array<string, list<array<string, mixed>>>>
 */
function crossCharacterPayloads(int $questId, int $achievementId, int $factionId, int $recipeId): array
{
    return [
        'quests/completed' => ['quests' => [['id' => $questId], ['id' => $questId + 1]]],
        'achievements' => ['achievements' => [
            ['id' => $achievementId, 'completed_timestamp' => 1_700_000_000_000],
            ['id' => $achievementId + 1],
        ]],
        'reputations' => ['reputations' => [
            ['faction' => ['id' => $factionId], 'standing' => ['raw' => 500, 'tier' => 5, 'name' => 'Honoré', 'max' => 3000]],
        ]],
        'professions' => ['primaries' => [[
            'profession' => ['id' => 164],
            'tiers' => [['tier' => ['id' => 2822], 'skill_points' => 50, 'max_skill_points' => 100, 'known_recipes' => [['id' => $recipeId]]]],
        ]]],
    ];
}

/**
 * @param  array<string, array<string, list<array<string, mixed>>>>  $payloads
 * @return array<string, \GuzzleHttp\Promise\PromiseInterface>
 */
function fakeCrossCharacter(string $realm, string $name, array $payloads): array
{
    $fakes = [];
    foreach ($payloads as $endpoint => $payload) {
        $fakes[crossCharacterUrl($realm, $name, $endpoint)] = Http::response($payload);
    }

    return $fakes;
}

/**
 * @return array<string, \GuzzleHttp\Promise\PromiseInterface>
 */
function fakeMissingCharacter(string $realm, string $name): array
{
    $fakes = [];
    foreach (crossCharacterEndpoints() as $endpoint) {
        $fakes[crossCharacterUrl($realm, $name, $endpoint)] = Http::response(['code' => 404], 404);
    }

    return $fakes;
}

/**
 * @param  list<array<string, mixed>>  $characters
 * @return array<string, mixed>
 */
function mergeCrossCharacters(array $characters, ?string $accessToken = 'user-token'): array
{
    $crossCharacterProgress = new CrossCharacterProgress;

    resolve(CrossCharacterService::class)->fetchAndMergeCharacters($characters, $crossCharacterProgress, $accessToken);

    return $crossCharacterProgress->buildResult();
}

/**
 * @param  list<int>  $questIds
 * @param  list<array<string, mixed>>  $professions
 */
function crossCharacterProfile(string $name, array $questIds = [], array $professions = []): CharacterProfileDTO
{
    return new CharacterProfileDTO(
        name: $name,
        realm: 'Hyjal',
        race: 'Orc',
        class: 'Chaman',
        classId: 7,
        level: 80,
        ilvl: 620,
        faction: 'Horde',
        avatarUrl: '',
        classIconUrl: '',
        collections: [],
        mountsCount: 0,
        petsCount: 0,
        professions: $professions,
        completedQuestIds: $questIds,
    );
}

function signInCrossCharacterUser(string $bnetUserId = '42'): void
{
    Session::put('blizzard_user_token', 'user-token');
    Session::put('bnet_user_id', $bnetUserId);
}

// ─── compute ────────────────────────────────────────────────

test('compute refuses a visitor without a Battle.net session', function (): void {
    expect(resolve(CrossCharacterService::class)->compute('Thrall#1234'))->toBe(['status' => 'unauthenticated']);
});

test('compute refuses a Battle.net user id whose session token is gone', function (): void {
    Session::put('bnet_user_id', '42');

    expect(resolve(CrossCharacterService::class)->compute('Thrall#1234'))->toBe(['status' => 'unauthenticated']);
});

test('compute refuses a session that carries no Battle.net user id', function (): void {
    Session::put('blizzard_user_token', 'user-token');

    expect(resolve(CrossCharacterService::class)->compute('Thrall#1234'))->toBe(['status' => 'unauthenticated']);
});

test('compute serves fresh stored data without queuing anything', function (): void {
    Queue::fake();
    signInCrossCharacterUser();
    CrossCharacterData::query()->create([
        'bnet_user_id' => '42',
        'data' => ['completedQuestIds' => [1]],
        'character_count' => 3,
        'fetched_at' => now()->subHour(),
    ]);

    expect(resolve(CrossCharacterService::class)->compute('Thrall#1234'))->toBe([
        'status' => 'ready',
        'data' => ['completedQuestIds' => [1]],
        'characterCount' => 3,
    ]);
    Queue::assertNothingPushed();
});

test('compute is ready with no data for an account without characters', function (): void {
    Queue::fake();
    signInCrossCharacterUser();
    $this->partialMock(UserCharacterService::class)->shouldReceive('getUserCharacters')->andReturn([]);

    expect(resolve(CrossCharacterService::class)->compute('Thrall#1234'))->toBe(['status' => 'ready', 'data' => null]);
    Queue::assertNothingPushed();
});

test('compute queues the computation of every character of the account', function (): void {
    Queue::fake();
    signInCrossCharacterUser();
    $this->partialMock(UserCharacterService::class)->shouldReceive('getUserCharacters')->andReturn([
        ['name' => 'Thrall', 'realmSlug' => 'hyjal', 'level' => 80],
        ['name' => 'Jaina', 'realmSlug' => 'ysondre', 'level' => 80],
    ]);

    $result = resolve(CrossCharacterService::class)->compute('Thrall#1234');

    expect($result['status'])->toBe('computing')
        ->and($result['jobId'])->toBeUuid()
        ->and(Cache::get('cross_character:'.$result['jobId']))->toBe(['status' => 'pending']);

    Queue::assertPushedOn('imports', ComputeCrossCharacterJob::class, fn (ComputeCrossCharacterJob $computeCrossCharacterJob): bool => $computeCrossCharacterJob->jobId === $result['jobId']
        && $computeCrossCharacterJob->bnetUserId === '42'
        && $computeCrossCharacterJob->accessToken === 'app-token'
        && $computeCrossCharacterJob->account() === 'Thrall#1234'
        && $computeCrossCharacterJob->characters === [
            ['name' => 'Thrall', 'realmSlug' => 'hyjal'],
            ['name' => 'Jaina', 'realmSlug' => 'ysondre'],
        ]);
});

test('compute refuses to queue a computation for an account without a BattleTag', function (): void {
    Queue::fake();
    signInCrossCharacterUser();
    $this->partialMock(UserCharacterService::class)->shouldReceive('getUserCharacters')->andReturn([
        ['name' => 'Thrall', 'realmSlug' => 'hyjal', 'level' => 80],
    ]);

    expect(fn (): array => resolve(CrossCharacterService::class)->compute(''))->toThrow(MissingBattleTagException::class);
    Queue::assertNothingPushed();
});

// ─── getJobStatus ───────────────────────────────────────────

test('getJobStatus reads the status the job published', function (): void {
    Cache::put('cross_character:job-1', ['status' => 'running'], 3600);

    expect(resolve(CrossCharacterService::class)->getJobStatus('job-1'))->toBe(['status' => 'running']);
});

test('getJobStatus reports an unknown job as not found', function (): void {
    expect(resolve(CrossCharacterService::class)->getJobStatus('unknown'))->toBe(['status' => 'not_found']);
});

// ─── getStoredData ──────────────────────────────────────────

test('getStoredData returns nothing without a Battle.net user', function (): void {
    CrossCharacterData::query()->create(['bnet_user_id' => '', 'data' => ['a' => 1], 'character_count' => 1, 'fetched_at' => now()]);

    expect(resolve(CrossCharacterService::class)->getStoredData())->toBeNull();
});

test('getStoredData returns nothing when the account was never computed', function (): void {
    signInCrossCharacterUser();

    expect(resolve(CrossCharacterService::class)->getStoredData())->toBeNull();
});

test('getStoredData ignores data merged from a profile but never fully computed', function (): void {
    signInCrossCharacterUser();
    CrossCharacterData::query()->create(['bnet_user_id' => '42', 'data' => ['a' => 1], 'character_count' => 1, 'fetched_at' => null]);

    expect(resolve(CrossCharacterService::class)->getStoredData())->toBeNull();
});

test('getStoredData serves data computed less than a day ago', function (): void {
    $this->freezeSecond();
    signInCrossCharacterUser();
    CrossCharacterData::query()->create([
        'bnet_user_id' => '42',
        'data' => ['questOwners' => [100 => 'Thrall']],
        'character_count' => 2,
        'fetched_at' => now()->subHours(24)->addMinute(),
    ]);

    expect(resolve(CrossCharacterService::class)->getStoredData())->toBe([
        'data' => ['questOwners' => [100 => 'Thrall']],
        'character_count' => 2,
    ]);
});

test('getStoredData treats data computed a day ago as stale', function (): void {
    $this->freezeSecond();
    signInCrossCharacterUser();
    CrossCharacterData::query()->create(['bnet_user_id' => '42', 'data' => ['a' => 1], 'character_count' => 2, 'fetched_at' => now()->subHours(24)]);

    expect(resolve(CrossCharacterService::class)->getStoredData())->toBeNull();
});

// ─── fetchAndMergeCharacters ────────────────────────────────

test('it merges the progress of every character with its owner', function (): void {
    Http::fake([
        ...fakeCrossCharacter('hyjal', 'thrall', crossCharacterPayloads(100, 200, 2600, 900)),
        ...fakeCrossCharacter('ysondre', 'jaina', crossCharacterPayloads(300, 400, 2601, 901)),
    ]);

    $result = mergeCrossCharacters([
        ['name' => 'Thrall', 'realmSlug' => 'hyjal'],
        ['name' => 'Jaina', 'realmSlug' => 'ysondre'],
    ]);

    expect($result['questOwners'])->toBe([100 => 'Thrall', 101 => 'Thrall', 300 => 'Jaina', 301 => 'Jaina'])
        ->and($result['achievementOwners'])->toBe([200 => 'Thrall', 400 => 'Jaina'])
        ->and($result['recipeOwners'])->toBe([900 => 'Thrall', 901 => 'Jaina'])
        ->and(array_keys($result['bestFactionStandings']))->toBe([2600, 2601])
        ->and($result['bestFactionStandings'][2601]['character_name'])->toBe('Jaina')
        ->and($result['skillPointOwners'][164][2822]['skill_points'])->toBe(50);
});

test('it asks Blizzard for the lowercased character in the profile namespace', function (): void {
    Http::fake(fakeCrossCharacter('hyjal', 'thrall', crossCharacterPayloads(100, 200, 2600, 900)));

    mergeCrossCharacters([['name' => 'Thrall', 'realmSlug' => 'Hyjal']]);

    Http::assertSentCount(4);
    foreach (crossCharacterEndpoints() as $endpoint) {
        Http::assertSent(fn (Request $request): bool => $request->url() === sprintf('https://eu.api.blizzard.com/profile/wow/character/hyjal/thrall/%s?locale=fr_FR', $endpoint)
            && $request->hasHeader('Authorization', 'Bearer user-token')
            && $request->hasHeader('Battlenet-Namespace', 'profile-eu'));
    }
});

test('it falls back on the application token when none is given', function (): void {
    Http::fake(fakeCrossCharacter('hyjal', 'thrall', crossCharacterPayloads(100, 200, 2600, 900)));

    mergeCrossCharacters([['name' => 'Thrall', 'realmSlug' => 'hyjal']], accessToken: null);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer app-token'));
});

test('it skips a character without realm or name', function (): void {
    Http::fake();

    $result = mergeCrossCharacters([
        ['name' => 'Thrall'],
        ['name' => 'Thrall', 'realmSlug' => ''],
        ['realmSlug' => 'hyjal'],
        ['name' => '', 'realmSlug' => 'hyjal'],
        ['name' => 42, 'realmSlug' => 'hyjal'],
    ]);

    Http::assertNothingSent();
    expect($result['completedQuestIds'])->toBe([]);
});

test('a character without realm or name does not stop the next ones', function (array $incomplete): void {
    Http::fake(fakeCrossCharacter('hyjal', 'thrall', crossCharacterPayloads(100, 200, 2600, 900)));

    $result = mergeCrossCharacters([$incomplete, ['name' => 'Thrall', 'realmSlug' => 'hyjal']]);

    expect($result['questOwners'])->toBe([100 => 'Thrall', 101 => 'Thrall']);
})->with([
    'without realm' => [['name' => 'Jaina']],
    'without name' => [['realmSlug' => 'ysondre']],
    'with an empty realm' => [['name' => 'Jaina', 'realmSlug' => '']],
    'with an empty name' => [['name' => '', 'realmSlug' => 'ysondre']],
]);

test('a deleted character does not prevent merging the others', function (): void {
    Http::fake([
        ...fakeMissingCharacter('hyjal', 'renamed'),
        ...fakeCrossCharacter('hyjal', 'thrall', crossCharacterPayloads(100, 200, 2600, 900)),
    ]);

    $result = mergeCrossCharacters([
        ['name' => 'Renamed', 'realmSlug' => 'hyjal'],
        ['name' => 'Thrall', 'realmSlug' => 'hyjal'],
    ]);

    expect($result['questOwners'])->toBe([100 => 'Thrall', 101 => 'Thrall'])
        ->and($result['recipeOwners'])->toBe([900 => 'Thrall']);
    Http::assertSentCount(8);
    Sleep::assertNeverSlept();
});

test('it retries a failing endpoint with an exponential backoff', function (): void {
    $payloads = crossCharacterPayloads(100, 200, 2600, 900);
    Http::fake([
        ...fakeCrossCharacter('hyjal', 'thrall', $payloads),
        crossCharacterUrl('hyjal', 'thrall', 'quests/completed') => Http::sequence()
            ->push(status: 500)
            ->push(status: 503)
            ->push($payloads['quests/completed']),
    ]);

    $result = mergeCrossCharacters([['name' => 'Thrall', 'realmSlug' => 'hyjal']]);

    expect($result['questOwners'])->toBe([100 => 'Thrall', 101 => 'Thrall']);
    Sleep::assertSequence([Sleep::for(5)->seconds(), Sleep::for(10)->seconds()]);
});

test('an endpoint that keeps failing is given up after four attempts without losing the others', function (): void {
    $connectionAttempts = 0;
    Http::fake([
        ...fakeCrossCharacter('hyjal', 'thrall', crossCharacterPayloads(100, 200, 2600, 900)),
        crossCharacterUrl('hyjal', 'thrall', 'quests/completed') => Http::response(status: 500),
        crossCharacterUrl('hyjal', 'thrall', 'achievements') => function () use (&$connectionAttempts): never {
            $connectionAttempts++;

            throw new ConnectionException('timeout');
        },
    ]);

    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $messageLogged) use (&$logged): void {
        $logged[] = $messageLogged->message;
    });

    $result = mergeCrossCharacters([['name' => 'Thrall', 'realmSlug' => 'hyjal']]);

    expect($logged)->toContain(
        'Cross-character fetch error: HTTP 500 for https://eu.api.blizzard.com/profile/wow/character/hyjal/thrall/quests/completed',
        'Cross-character fetch error: timeout for https://eu.api.blizzard.com/profile/wow/character/hyjal/thrall/achievements',
    )
        ->and($result['questOwners'])->toBe([])
        ->and($result['achievementOwners'])->toBe([])
        ->and($result['recipeOwners'])->toBe([900 => 'Thrall']);
    Http::assertSentCount(4 + 1 + 1);
    expect($connectionAttempts)->toBe(4);
    Sleep::assertSequence([
        Sleep::for(5)->seconds(), Sleep::for(10)->seconds(), Sleep::for(20)->seconds(),
        Sleep::for(5)->seconds(), Sleep::for(10)->seconds(), Sleep::for(20)->seconds(),
    ]);
});

test('empty responses merge into nothing', function (): void {
    Http::fake(fakeCrossCharacter('hyjal', 'thrall', array_fill_keys(crossCharacterEndpoints(), [])));

    $result = mergeCrossCharacters([['name' => 'Thrall', 'realmSlug' => 'hyjal']]);

    expect($result['completedQuestIds'])->toBe([])
        ->and($result['completedAchievementIds'])->toBe([])
        ->and($result['bestFactionStandings'])->toBe([])
        ->and($result['skillPointOwners'])->toBe([]);
});

// ─── mergeCurrentCharacter ──────────────────────────────────

test('mergeCurrentCharacter writes nothing without a Battle.net user', function (): void {
    resolve(CrossCharacterService::class)->mergeCurrentCharacter(crossCharacterProfile('Thrall', [100]));

    expect(CrossCharacterData::query()->count())->toBe(0);
});

test('mergeCurrentCharacter starts the account data from the viewed character', function (): void {
    signInCrossCharacterUser();

    resolve(CrossCharacterService::class)->mergeCurrentCharacter(crossCharacterProfile('Thrall', [100]));

    $crossCharacterData = CrossCharacterData::query()->findOrFail('42');
    expect($crossCharacterData->data['questOwners'])->toBe([100 => 'Thrall'])
        ->and($crossCharacterData->character_count)->toBe(1)
        ->and($crossCharacterData->fetched_at)->toBeNull();
});

test('mergeCurrentCharacter adds the viewed character to the stored data without losing it', function (): void {
    signInCrossCharacterUser();
    $fetchedAt = now()->subHours(3)->startOfSecond();
    $standing = ['character_name' => 'Jaina', 'tier' => 7, 'raw' => 42000, 'renown_level' => 0, 'standing_name' => 'Exalté', 'completed' => true];
    $skill = ['character_name' => 'Jaina', 'skill_points' => 100, 'max_skill_points' => 100];
    CrossCharacterData::query()->create([
        'bnet_user_id' => '42',
        'data' => [
            'questOwners' => [100 => 'Jaina'],
            'achievementOwners' => [200 => 'Jaina'],
            'completedRecipeIds' => [900],
            'recipeOwners' => [900 => 'Jaina'],
            'bestFactionStandings' => [2600 => $standing],
            'skillPointOwners' => [164 => [2822 => $skill]],
        ],
        'character_count' => 3,
        'fetched_at' => $fetchedAt,
    ]);

    resolve(CrossCharacterService::class)->mergeCurrentCharacter(crossCharacterProfile('Thrall', [100, 101], [[
        'profession_id' => 171,
        'expansions' => [2823 => ['skill_points' => 20, 'max_skill_points' => 100, 'categories' => []]],
    ]]));

    $crossCharacterData = CrossCharacterData::query()->findOrFail('42');
    expect($crossCharacterData->data['questOwners'])->toBe([100 => 'Jaina', 101 => 'Thrall'])
        ->and($crossCharacterData->data['completedQuestIds'])->toBe([100, 101])
        ->and($crossCharacterData->data['achievementOwners'])->toBe([200 => 'Jaina'])
        ->and($crossCharacterData->data['completedRecipeIds'])->toBe([900])
        ->and($crossCharacterData->data['recipeOwners'])->toBe([900 => 'Jaina'])
        ->and($crossCharacterData->data['bestFactionStandings'])->toEqual([2600 => $standing])
        ->and($crossCharacterData->data['skillPointOwners'])->toEqual([164 => [2822 => $skill], 171 => [2823 => ['character_name' => 'Thrall', 'skill_points' => 20, 'max_skill_points' => 100]]])
        ->and($crossCharacterData->character_count)->toBe(3)
        ->and($crossCharacterData->fetched_at?->equalTo($fetchedAt))->toBeTrue();
});

test('mergeCurrentCharacter reads the former format without owners', function (): void {
    signInCrossCharacterUser();
    CrossCharacterData::query()->create([
        'bnet_user_id' => '42',
        'data' => ['completedQuestIds' => [100], 'completedAchievementIds' => [200]],
        'character_count' => 2,
        'fetched_at' => null,
    ]);

    resolve(CrossCharacterService::class)->mergeCurrentCharacter(crossCharacterProfile('Thrall', [101]));

    $crossCharacterData = CrossCharacterData::query()->findOrFail('42');
    expect($crossCharacterData->data['questOwners'])->toBe([100 => '', 101 => 'Thrall'])
        ->and($crossCharacterData->data['achievementOwners'])->toBe([200 => '']);
});

test('mergeCurrentCharacter prefers the owners over the former lists when both are stored', function (): void {
    signInCrossCharacterUser();
    CrossCharacterData::query()->create([
        'bnet_user_id' => '42',
        'data' => [
            'questOwners' => [100 => 'Jaina'],
            'completedQuestIds' => [100, 555],
            'achievementOwners' => [200 => 'Jaina'],
            'completedAchievementIds' => [200, 666],
        ],
        'character_count' => 2,
        'fetched_at' => null,
    ]);

    resolve(CrossCharacterService::class)->mergeCurrentCharacter(crossCharacterProfile('Thrall'));

    $crossCharacterData = CrossCharacterData::query()->findOrFail('42');
    expect($crossCharacterData->data['questOwners'])->toBe([100 => 'Jaina'])
        ->and($crossCharacterData->data['achievementOwners'])->toBe([200 => 'Jaina']);
});

test('the account data round trip is unchanged', function (): void {
    signInCrossCharacterUser();
    $jaina = crossCharacterPayloads(300, 400, 2600, 901);
    $jaina['reputations'] = ['reputations' => [
        ['faction' => ['id' => 2600], 'standing' => ['raw' => 900, 'tier' => 6, 'name' => 'Révéré', 'max' => 3000]],
        ['faction' => ['id' => 2590], 'standing' => ['raw' => 100, 'tier' => 0, 'renown_level' => 20, 'max' => 0, 'name' => 'Renom 20']],
        ['faction' => [], 'standing' => ['raw' => 1]],
    ]];
    $jaina['professions'] = ['primaries' => [[
        'profession' => ['id' => 164],
        'tiers' => [
            ['tier' => ['id' => 2822], 'skill_points' => 80, 'max_skill_points' => 100, 'known_recipes' => [['id' => 900], ['id' => 902]]],
            ['tier' => ['id' => 2823], 'skill_points' => 0, 'known_recipes' => []],
        ],
    ]], 'secondaries' => [['profession' => ['id' => 356], 'tiers' => [['tier' => ['id' => 1], 'skill_points' => 10, 'max_skill_points' => 300]]]]];
    Http::fake([
        ...fakeCrossCharacter('hyjal', 'thrall', crossCharacterPayloads(100, 200, 2600, 900)),
        ...fakeCrossCharacter('ysondre', 'jaina', $jaina),
        ...fakeMissingCharacter('hyjal', 'arthas'),
    ]);

    $computed = mergeCrossCharacters([
        ['name' => 'Thrall', 'realmSlug' => 'hyjal'],
        ['name' => 'Jaina', 'realmSlug' => 'ysondre'],
        ['name' => 'Arthas', 'realmSlug' => 'hyjal'],
    ]);
    CrossCharacterData::query()->create(['bnet_user_id' => '42', 'data' => $computed, 'character_count' => 3, 'fetched_at' => now()]);

    $stored = resolve(CrossCharacterService::class)->getStoredData();

    resolve(CrossCharacterService::class)->mergeCurrentCharacter(crossCharacterProfile('Sylvanas', [100, 500], [[
        'profession_id' => 164,
        'expansions' => [
            10 => ['skill_points' => 95, 'max_skill_points' => 100, 'categories' => [['items' => [['id' => 903, 'is_completed' => true], ['id' => 904, 'is_completed' => false]]]]],
        ],
    ]]));

    $normalise = function (array $value) use (&$normalise): array {
        ksort($value);

        return array_map(fn ($entry) => is_array($entry) ? $normalise($entry) : $entry, $value);
    };

    expect(json_encode([
        'computed' => $computed,
        'stored' => $normalise($stored ?? []),
        'merged' => $normalise(CrossCharacterData::query()->find('42')?->data ?? []),
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))->toMatchSnapshot();
});
