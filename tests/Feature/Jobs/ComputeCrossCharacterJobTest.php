<?php

declare(strict_types=1);

use App\Application\DTOs\CrossCharacterProgress;
use App\Application\DTOs\FetchedCharacterProgress;
use App\Application\Health\FailedJobs;
use App\Application\Services\CrossCharacterService;
use App\Application\Services\ExpiredBlizzardTokenException;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterProfessionsResponse;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterReputationsResponse;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;
use App\Jobs\ComputeCrossCharacterJob;
use App\Models\ApplicationError;
use App\Models\CrossCharacterData;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

beforeEach(function (): void {
    Cache::flush();
    $this->memoryLimit = ini_get('memory_limit');
});

afterEach(function (): void {
    ini_set('memory_limit', (string) $this->memoryLimit);
});

/**
 * @return list<array<string, mixed>>
 */
function crossCharacterJobCharacters(): array
{
    return [
        ['name' => 'Thrall', 'realmSlug' => 'hyjal'],
        ['name' => 'Jaina', 'realmSlug' => 'ysondre'],
    ];
}

function crossCharacterJob(): ComputeCrossCharacterJob
{
    return new ComputeCrossCharacterJob('job-1', '42', crossCharacterJobCharacters(), 'app-token', 'Thrall#1234');
}

/**
 * Le service fusionne un seul personnage, avec une quête, un haut-fait, une réputation et
 * une recette : de quoi relire chaque famille de clés entières après le passage en jsonb.
 */
function stubCrossCharacterMerge(?\Closure $observe = null): void
{
    test()->mock(CrossCharacterService::class)
        ->shouldReceive('fetchAndMergeCharacters')
        ->once()
        ->withArgs(fn (array $characters, CrossCharacterProgress $crossCharacterProgress, ?string $token): bool => $characters === crossCharacterJobCharacters() && $token === 'app-token')
        ->andReturnUsing(function (array $characters, CrossCharacterProgress $crossCharacterProgress) use ($observe): void {
            if ($observe instanceof \Closure) {
                $observe();
            }

            $crossCharacterProgress->mergeCharacter('Thrall', new FetchedCharacterProgress(
                questIds: [100],
                achievementIds: [200],
                reputations: CharacterReputationsResponse::fromPayload(ResponsePayload::forEndpoint('reputations', ['reputations' => [['faction' => ['id' => 2600], 'standing' => ['raw' => 500, 'tier' => 5, 'name' => 'Honoré', 'max' => 3000]]]])),
                professions: CharacterProfessionsResponse::fromPayload(ResponsePayload::forEndpoint('professions', ['primaries' => [['profession' => ['id' => 164], 'tiers' => [['tier' => ['id' => 2822], 'skill_points' => 50, 'max_skill_points' => 100, 'known_recipes' => [['id' => 900]]]]]]])),
            ));
        });
}

function handleCrossCharacterJob(): void
{
    crossCharacterJob()->handle(resolve(CrossCharacterService::class));
}

/**
 * Le job passe par la vraie file Redis de test et par un worker : c'est lui qui décide de
 * l'échec, de la relance et de l'inscription dans `failed_jobs`.
 */
function runCrossCharacterJobOnWorker(): void
{
    useRedisQueue();
    dispatch(crossCharacterJob());
    Artisan::call('queue:work', ['connection' => 'redis', '--queue' => 'imports', '--once' => true]);
}

function failCrossCharacterMerge(): void
{
    test()->mock(CrossCharacterService::class)
        ->shouldReceive('fetchAndMergeCharacters')
        ->andThrow(new \RuntimeException('Blizzard is down'));
}

test('it runs on the imports queue with a ten minute timeout', function (): void {
    $computeCrossCharacterJob = crossCharacterJob();

    expect($computeCrossCharacterJob->queue)->toBe('imports')
        ->and($computeCrossCharacterJob->timeout)->toBe(600);
});

test('it publishes the running status before merging the characters', function (): void {
    $statusDuringMerge = null;
    stubCrossCharacterMerge(function () use (&$statusDuringMerge): void {
        $statusDuringMerge = Cache::get('cross_character:job-1');
    });

    handleCrossCharacterJob();

    expect($statusDuringMerge)->toBe(['status' => 'running']);
});

test('it raises the memory limit for the merge', function (): void {
    stubCrossCharacterMerge();

    handleCrossCharacterJob();

    expect(ini_get('memory_limit'))->toBe('256M');
});

test('it stores the account data and publishes its completion', function (): void {
    $this->freezeSecond();
    stubCrossCharacterMerge();

    handleCrossCharacterJob();

    $crossCharacterData = CrossCharacterData::query()->findOrFail('42');
    expect(Cache::get('cross_character:job-1'))->toBe(['status' => 'completed'])
        ->and($crossCharacterData->character_count)->toBe(2)
        ->and($crossCharacterData->fetched_at?->equalTo(now()))->toBeTrue()
        ->and($crossCharacterData->data['completedQuestIds'])->toBe([100])
        ->and($crossCharacterData->data['questOwners'])->toBe([100 => 'Thrall'])
        ->and($crossCharacterData->data['achievementOwners'])->toBe([200 => 'Thrall'])
        ->and($crossCharacterData->data['recipeOwners'])->toBe([900 => 'Thrall'])
        ->and($crossCharacterData->data['bestFactionStandings'][2600]['character_name'])->toBe('Thrall')
        ->and($crossCharacterData->data['skillPointOwners'][164][2822]['skill_points'])->toBe(50);
});

test('a new computation replaces the stored account data', function (): void {
    CrossCharacterData::query()->create([
        'bnet_user_id' => '42',
        'data' => ['questOwners' => [555 => 'Ancien']],
        'character_count' => 7,
        'fetched_at' => now()->subDays(3),
    ]);
    stubCrossCharacterMerge();

    handleCrossCharacterJob();

    $crossCharacterData = CrossCharacterData::query()->sole();
    expect($crossCharacterData->data['questOwners'])->toBe([100 => 'Thrall'])
        ->and($crossCharacterData->character_count)->toBe(2);
});

test('a failing merge publishes the failure, logs it and stores nothing', function (): void {
    failCrossCharacterMerge();

    runCrossCharacterJobOnWorker();

    expect(Cache::get('cross_character:job-1'))->toBe(['status' => 'failed'])
        ->and(CrossCharacterData::query()->count())->toBe(0)
        ->and(ApplicationError::query()->where('message', 'Cross-character job failed')->exists())->toBeTrue();
});

test('the failure log names the job and the cause', function (): void {
    $contexts = [];
    Event::listen(MessageLogged::class, function (MessageLogged $messageLogged) use (&$contexts): void {
        if ($messageLogged->message === 'Cross-character job failed') {
            $contexts[] = $messageLogged->context;
        }
    });
    failCrossCharacterMerge();

    runCrossCharacterJobOnWorker();

    expect($contexts)->toBe([['jobId' => 'job-1', 'error' => 'Blizzard is down']]);
});

test('a failing computation lands in the failed jobs after a single attempt', function (): void {
    failCrossCharacterMerge();

    runCrossCharacterJobOnWorker();

    expect(crossCharacterJob()->tries)->toBe(1)
        ->and(DB::table('failed_jobs')->count())->toBe(1)
        ->and(Queue::connection('redis')->size('imports'))->toBe(0)
        ->and(resolve(FailedJobs::class)->all()[0]['job'])->toBe('Données des autres personnages');
});

test('a computation killed without an exception still tells the hub it failed', function (): void {
    crossCharacterJob()->failed(new \RuntimeException('Timed out'));

    expect(Cache::get('cross_character:job-1'))->toBe(['status' => 'failed']);
});

test('the token never sits in clear in the queue nor in the failed jobs', function (): void {
    useRedisQueue();
    dispatch(crossCharacterJob());
    $queued = (string) Redis::connection('queue')->lindex('queues:imports', 0);
    failCrossCharacterMerge();

    Artisan::call('queue:work', ['connection' => 'redis', '--queue' => 'imports', '--once' => true]);

    expect($queued)->not->toContain('app-token')
        ->and(json_decode($queued, true)['described'])->toBe(['label' => 'Données des autres personnages', 'account' => 'Thrall#1234'])
        ->and((string) DB::table('failed_jobs')->value('payload'))->not->toContain('app-token');
});

test('a failed computation retried from the health page runs again', function (): void {
    failCrossCharacterMerge();
    runCrossCharacterJobOnWorker();
    $uuid = (string) DB::table('failed_jobs')->value('uuid');
    stubCrossCharacterMerge();

    resolve(FailedJobs::class)->retry($uuid, 'admin');
    Artisan::call('queue:work', ['connection' => 'redis', '--queue' => 'imports', '--once' => true]);

    expect(Cache::get('cross_character:job-1'))->toBe(['status' => 'completed'])
        ->and(CrossCharacterData::query()->count())->toBe(1)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});

test('a computation retried after its token expired fails again and says why', function (): void {
    test()->mock(CrossCharacterService::class)
        ->shouldReceive('fetchAndMergeCharacters')
        ->andThrow(ExpiredBlizzardTokenException::forUrl('https://eu.api.blizzard.com/profile/wow/character/hyjal/thrall/quests/completed'));

    runCrossCharacterJobOnWorker();

    expect(resolve(FailedJobs::class)->all()[0]['exception'])->toStartWith(ExpiredBlizzardTokenException::class.': The Blizzard token of this computation has expired')
        ->and(CrossCharacterData::query()->count())->toBe(0);
});

test('the final status stays readable for an hour so the page can poll it', function (bool $fails, string $status): void {
    $this->freezeSecond();
    $merge = test()->mock(CrossCharacterService::class)->shouldReceive('fetchAndMergeCharacters');
    $fails ? $merge->andThrow(new \RuntimeException('Blizzard is down')) : $merge->andReturnNull();

    runCrossCharacterJobOnWorker();

    $this->travel(3599)->seconds();
    expect(Cache::get('cross_character:job-1'))->toBe(['status' => $status]);

    $this->travel(1)->seconds();
    expect(Cache::get('cross_character:job-1'))->toBeNull();
})->with([
    'completed' => [false, 'completed'],
    'failed' => [true, 'failed'],
]);

test('the account computation is shown under a readable label and the BattleTag of its account', function (): void {
    expect(crossCharacterJob())
        ->label()->toBe('Données des autres personnages')
        ->account()->toBe('Thrall#1234');
});
