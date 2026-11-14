<?php

declare(strict_types=1);

use App\Application\Services\MissingBattleTagException;
use App\Application\Services\UserCharacterService;
use App\Application\Services\WeeklyActivityService;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Responses\MythicDungeon;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;
use App\Jobs\ComputeWeeklyActivityJob;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Session;

const WEEK_STARTED = '2026-11-14 20:00:00';

beforeEach(function (): void {
    Cache::flush();
    Queue::fake();
    $this->travelTo(new DateTimeImmutable(WEEK_STARTED, new DateTimeZone('UTC')));
});

/**
 * @return array{name: string, realm: string, realmSlug: string, level: int, classId: int, className: string, raceId: int, raceName: string, faction: string, avatarUrl: string}
 */
function accountCharacter(string $name, int $level): array
{
    return ['name' => $name, 'realm' => 'Dalaran', 'realmSlug' => 'dalaran', 'level' => $level, 'classId' => 2, 'className' => 'Paladin', 'raceId' => 3, 'raceName' => 'Nain', 'faction' => 'Alliance', 'avatarUrl' => ''];
}

/**
 * @param  list<array{name: string, realm: string, realmSlug: string, level: int, classId: int, className: string, raceId: int, raceName: string, faction: string, avatarUrl: string}>  $characters
 */
function signedInWith(array $characters, string $bnetUserId = '42'): void
{
    Session::put('bnet_user_id', $bnetUserId);

    $userCharacters = Mockery::mock(UserCharacterService::class);
    $userCharacters->allows('isAuthenticated')->andReturnTrue();
    $userCharacters->allows('getUserCharacters')->andReturn($characters);
    app()->instance(UserCharacterService::class, $userCharacters);
}

/**
 * The account plays one raid boss this week and one key; a character that Blizzard no longer
 * knows answers 404. The profile endpoints asked for are recorded.
 *
 * @return ArrayObject<int, string>
 */
function blizzardWeek(): ArrayObject
{
    $profileRequests = new ArrayObject;
    $client = Mockery::mock(BlizzardApiClient::class);
    $client->allows('getRegion')->andReturn('eu');
    $client->allows('getCurrentMythicDungeons')->andReturn([new MythicDungeon(585, 'Arène de la Cicatrice du Vide'), new MythicDungeon(249, 'Repaire de la Nexus-reine')]);
    $client->allows('get')->with('data/wow/journal-instance/1307', Mockery::any())->andReturn([
        'name' => 'La Flèche du Vide',
        'encounters' => [['id' => 11, 'name' => 'Imperator Averzian'], ['id' => 12, 'name' => 'Vorasius']],
    ]);
    $client->allows('getResponse')->andReturnUsing(function (string $endpoint) use ($profileRequests): ResponsePayload {
        $profileRequests->append($endpoint);
        throw_if(str_contains($endpoint, '/disparu/'), ClientException::class, 'Not Found', new Request('GET', $endpoint), new Response(404));

        if (str_ends_with($endpoint, '/encounters/raids')) {
            return ResponsePayload::forEndpoint($endpoint, ['expansions' => [['expansion' => ['id' => 505], 'instances' => [[
                'instance' => ['id' => 1307, 'name' => 'The Voidspire'],
                'modes' => [['difficulty' => ['type' => 'HEROIC'], 'progress' => ['completed_count' => 1, 'total_count' => 2, 'encounters' => [
                    ['encounter' => ['id' => 11, 'name' => 'Imperator Averzian'], 'last_kill_timestamp' => new DateTimeImmutable('2026-11-12 21:00:00', new DateTimeZone('UTC'))->getTimestamp() * 1000],
                ]]]],
            ]]]]]);
        }

        return ResponsePayload::forEndpoint($endpoint, ['current_period' => ['period' => ['id' => 1089], 'best_runs' => [
            ['dungeon' => ['id' => 585, 'name' => 'Arène de la Cicatrice du Vide'], 'keystone_level' => 12, 'completed_timestamp' => 1_794_400_000_000],
        ]]]);
    });
    app()->instance(BlizzardApiClient::class, $client);

    return $profileRequests;
}

/**
 * Runs the job the last call queued, as the worker would.
 */
function runQueuedWeek(): void
{
    Queue::assertPushed(ComputeWeeklyActivityJob::class, function (ComputeWeeklyActivityJob $computeWeeklyActivityJob): bool {
        $computeWeeklyActivityJob->handle(resolve(WeeklyActivityService::class));

        return true;
    });
}

test('a visitor who is not signed in gets no week', function (): void {
    $userCharacters = Mockery::mock(UserCharacterService::class);
    $userCharacters->allows('isAuthenticated')->andReturnFalse();
    app()->instance(UserCharacterService::class, $userCharacters);

    expect(resolve(WeeklyActivityService::class)->getOrCompute('Gothdroid#2855'))->toBe(['status' => 'unauthenticated']);
    Queue::assertNothingPushed();
});

test('an account without character has an empty week', function (): void {
    signedInWith([]);

    expect(resolve(WeeklyActivityService::class)->getOrCompute('Gothdroid#2855'))->toBe([
        'status' => 'ready',
        'data' => ['characters' => [], 'belowMaxLevel' => 0, 'maxLevel' => 0, 'updatedAt' => null],
    ]);
    Queue::assertNothingPushed();
});

test('an account without battle tag cannot queue its week', function (): void {
    signedInWith([accountCharacter('Gothdroid', 90)]);

    resolve(WeeklyActivityService::class)->getOrCompute('');
})->throws(MissingBattleTagException::class);

test('the week is computed in a job queued once, followed while it runs', function (): void {
    signedInWith([accountCharacter('Gothdroid', 90), accountCharacter('Petit', 12)]);

    $first = resolve(WeeklyActivityService::class)->getOrCompute('Gothdroid#2855');
    $second = resolve(WeeklyActivityService::class)->getOrCompute('Gothdroid#2855');

    expect($first['status'])->toBe('computing')
        ->and($second)->toBe($first);
    Queue::assertPushed(ComputeWeeklyActivityJob::class, 1);
    Queue::assertPushed(ComputeWeeklyActivityJob::class, fn (ComputeWeeklyActivityJob $computeWeeklyActivityJob): bool => $computeWeeklyActivityJob->bnetUserId === '42'
        && $computeWeeklyActivityJob->battleTag === 'Gothdroid#2855'
        && $computeWeeklyActivityJob->characters === [
            ['name' => 'Gothdroid', 'realm' => 'Dalaran', 'realmSlug' => 'dalaran', 'level' => 90, 'classId' => 2],
            ['name' => 'Petit', 'realm' => 'Dalaran', 'realmSlug' => 'dalaran', 'level' => 12, 'classId' => 2],
        ]);
});

test('once the job is done, the week of each character is served', function (): void {
    signedInWith([accountCharacter('Gothdroid', 90), accountCharacter('Petit', 12)]);
    blizzardWeek();

    resolve(WeeklyActivityService::class)->getOrCompute('Gothdroid#2855');
    runQueuedWeek();
    $result = resolve(WeeklyActivityService::class)->getOrCompute('Gothdroid#2855');

    expect($result['status'])->toBe('ready')
        ->and($result['data']['belowMaxLevel'])->toBe(1)
        ->and($result['data']['updatedAt'])->toBe('2026-11-14T20:00:00Z');

    $gothdroid = $result['data']['characters'][0];
    expect($gothdroid['name'])->toBe('Gothdroid')
        ->and($gothdroid['raid']['count'])->toBe(1)
        ->and($gothdroid['raid']['remaining'][0]['name'])->toBe('La Flèche du Vide')
        ->and($gothdroid['mythicPlus']['count'])->toBe(1)
        ->and($gothdroid['mythicPlus']['missingDungeons'])->toBe(['Repaire de la Nexus-reine']);
});

test('the job reads two endpoints of each character at the highest level, and none of the others', function (): void {
    signedInWith([accountCharacter('Gothdroid', 90), accountCharacter('Petit', 12)]);
    $profileRequests = blizzardWeek();

    resolve(WeeklyActivityService::class)->getOrCompute('Gothdroid#2855');
    runQueuedWeek();

    expect($profileRequests->getArrayCopy())->toBe([
        'profile/wow/character/dalaran/gothdroid/encounters/raids',
        'profile/wow/character/dalaran/gothdroid/mythic-keystone-profile',
    ]);
});

test('a character Blizzard no longer knows stands at zero, the others stay', function (): void {
    signedInWith([accountCharacter('Disparu', 90), accountCharacter('Gothdroid', 90)]);
    blizzardWeek();

    resolve(WeeklyActivityService::class)->getOrCompute('Gothdroid#2855');
    runQueuedWeek();
    $characters = resolve(WeeklyActivityService::class)->getOrCompute('Gothdroid#2855')['data']['characters'];

    expect(array_column($characters, 'name'))->toBe(['Gothdroid', 'Disparu'])
        ->and($characters[1]['raid']['count'])->toBe(0);
});

test('a job that failed is reported, and the next call queues a new one', function (): void {
    signedInWith([accountCharacter('Gothdroid', 90)]);

    resolve(WeeklyActivityService::class)->getOrCompute('Gothdroid#2855');
    Queue::assertPushed(ComputeWeeklyActivityJob::class, fn (ComputeWeeklyActivityJob $computeWeeklyActivityJob): bool => $computeWeeklyActivityJob->failed(new RuntimeException('Blizzard down')) === null);

    expect(resolve(WeeklyActivityService::class)->getOrCompute('Gothdroid#2855'))->toBe(['status' => 'failed'])
        ->and(resolve(WeeklyActivityService::class)->getOrCompute('Gothdroid#2855')['status'])->toBe('computing');
    Queue::assertPushed(ComputeWeeklyActivityJob::class, 2);
});

test('the week is kept fifteen minutes', function (): void {
    signedInWith([accountCharacter('Gothdroid', 90)]);
    blizzardWeek();

    resolve(WeeklyActivityService::class)->getOrCompute('Gothdroid#2855');
    runQueuedWeek();

    $this->travel(14)->minutes();
    expect(resolve(WeeklyActivityService::class)->getOrCompute('Gothdroid#2855')['status'])->toBe('ready');

    $this->travel(2)->minutes();
    expect(resolve(WeeklyActivityService::class)->getOrCompute('Gothdroid#2855')['status'])->toBe('computing');
});

test('the week is never kept past the next reset', function (): void {
    $this->travelTo(new DateTimeImmutable('2026-11-18 03:55:00', new DateTimeZone('UTC')));
    signedInWith([accountCharacter('Gothdroid', 90)]);
    blizzardWeek();

    resolve(WeeklyActivityService::class)->getOrCompute('Gothdroid#2855');
    runQueuedWeek();

    $this->travel(4)->minutes();
    expect(resolve(WeeklyActivityService::class)->getOrCompute('Gothdroid#2855')['status'])->toBe('ready');

    $this->travel(2)->minutes();
    expect(resolve(WeeklyActivityService::class)->getOrCompute('Gothdroid#2855')['status'])->toBe('computing');
});

test('refreshing drops the week and the job it was waiting for', function (): void {
    signedInWith([accountCharacter('Gothdroid', 90)]);
    blizzardWeek();

    resolve(WeeklyActivityService::class)->getOrCompute('Gothdroid#2855');
    runQueuedWeek();

    resolve(WeeklyActivityService::class)->refresh();

    expect(resolve(WeeklyActivityService::class)->getOrCompute('Gothdroid#2855')['status'])->toBe('computing');
    Queue::assertPushed(ComputeWeeklyActivityJob::class, 2);
});

test('each player waits for his own job and reads his own week', function (): void {
    signedInWith([accountCharacter('Gothdroid', 90)]);
    blizzardWeek();
    resolve(WeeklyActivityService::class)->getOrCompute('Gothdroid#2855');
    runQueuedWeek();

    signedInWith([accountCharacter('Thrall', 90)], '7');

    expect(resolve(WeeklyActivityService::class)->getOrCompute('Thrall#1234')['status'])->toBe('computing');
    Queue::assertPushed(ComputeWeeklyActivityJob::class, 2);
});

test('any other Blizzard error fails the job rather than showing a false week', function (): void {
    signedInWith([accountCharacter('Gothdroid', 90)]);
    $client = Mockery::mock(BlizzardApiClient::class);
    $client->allows('getResponse')->andThrow(new ServerException('Bad Gateway', new Request('GET', 'x'), new Response(502)));
    app()->instance(BlizzardApiClient::class, $client);

    resolve(WeeklyActivityService::class)->computeAndStore('42', [['name' => 'Gothdroid', 'realm' => 'Dalaran', 'realmSlug' => 'dalaran', 'level' => 90, 'classId' => 2]], now()->toDateTimeImmutable());
})->throws(ServerException::class);

test('a signed-in player whose Battle.net account is unknown gets no week', function (): void {
    signedInWith([accountCharacter('Gothdroid', 90)]);
    Session::forget('bnet_user_id');

    expect(resolve(WeeklyActivityService::class)->getOrCompute('Gothdroid#2855'))->toBe(['status' => 'unauthenticated']);
    Queue::assertNothingPushed();
});

test('refreshing while a job runs queues a new one instead of waiting for the old one', function (): void {
    signedInWith([accountCharacter('Gothdroid', 90)]);

    $first = resolve(WeeklyActivityService::class)->getOrCompute('Gothdroid#2855');
    resolve(WeeklyActivityService::class)->refresh();
    $second = resolve(WeeklyActivityService::class)->getOrCompute('Gothdroid#2855');

    expect($second['jobId'])->not->toBe($first['jobId']);
    Queue::assertPushed(ComputeWeeklyActivityJob::class, 2);
});

test('a job whose state is lost is reported failed, and the next call queues a new one', function (): void {
    signedInWith([accountCharacter('Gothdroid', 90)]);

    $computing = resolve(WeeklyActivityService::class)->getOrCompute('Gothdroid#2855');
    Cache::forget('weekly_activity_job:'.$computing['jobId']);

    expect(resolve(WeeklyActivityService::class)->getOrCompute('Gothdroid#2855'))->toBe(['status' => 'failed'])
        ->and(resolve(WeeklyActivityService::class)->getOrCompute('Gothdroid#2855')['status'])->toBe('computing');
});
