<?php

declare(strict_types=1);

use App\Application\DTOs\CrossCharacterProgress;
use App\Application\DTOs\FetchedCharacterProgress;
use App\Application\Services\CrossCharacterService;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterProfessionsResponse;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterReputationsResponse;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;
use App\Jobs\ComputeCrossCharacterJob;
use App\Models\ApplicationError;
use App\Models\CrossCharacterData;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

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
    test()->mock(CrossCharacterService::class)
        ->shouldReceive('fetchAndMergeCharacters')
        ->andThrow(new \RuntimeException('Blizzard is down'));

    handleCrossCharacterJob();

    expect(Cache::get('cross_character:job-1'))->toBe(['status' => 'failed'])
        ->and(CrossCharacterData::query()->count())->toBe(0)
        ->and(ApplicationError::query()->sole()->message)->toBe('Cross-character job failed');
});

test('the failure log names the job and the cause', function (): void {
    $context = null;
    Event::listen(MessageLogged::class, function (MessageLogged $messageLogged) use (&$context): void {
        $context = $messageLogged->context;
    });
    test()->mock(CrossCharacterService::class)
        ->shouldReceive('fetchAndMergeCharacters')
        ->andThrow(new \RuntimeException('Blizzard is down'));

    handleCrossCharacterJob();

    expect($context)->toBe(['jobId' => 'job-1', 'error' => 'Blizzard is down']);
});

test('the final status stays readable for an hour so the page can poll it', function (bool $fails, string $status): void {
    $this->freezeSecond();
    $merge = test()->mock(CrossCharacterService::class)->shouldReceive('fetchAndMergeCharacters');
    $fails ? $merge->andThrow(new \RuntimeException('Blizzard is down')) : $merge->andReturnNull();

    handleCrossCharacterJob();

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
        ->label()->toBe('Calcul du score de compte')
        ->account()->toBe('Thrall#1234');
});
