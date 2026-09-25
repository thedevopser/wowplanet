<?php

declare(strict_types=1);

use App\Application\Services\AccountScoreService;
use App\Application\Services\CharacterProfileService;
use App\Application\Services\UserCharacterService;
use App\Domain\ValueObjects\ScoreWeights;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

test('it returns unauthenticated when no session', function (): void {
    $mockUserCharService = $this->mock(UserCharacterService::class);
    /** @var \Mockery\Expectation $exp */
    $exp = $mockUserCharService->shouldReceive('isAuthenticated');
    $exp->once()->andReturn(false);

    $this->mock(CharacterProfileService::class);

    $accountScoreService = resolve(AccountScoreService::class);
    $result = $accountScoreService->getOrCompute();

    expect($result)->toHaveKey('status', 'unauthenticated');
});

test('it returns cached result when available', function (): void {
    $mockUserCharService = $this->mock(UserCharacterService::class);
    /** @var \Mockery\Expectation $exp */
    $exp = $mockUserCharService->shouldReceive('isAuthenticated');
    $exp->once()->andReturn(true);

    $this->mock(CharacterProfileService::class);

    Session::shouldReceive('getId')->andReturn('test-session-id');

    $cachedData = [
        'collections' => [],
        'mounts' => [],
        'pets' => [],
        'decor' => [],
        'professions' => [],
        'mountsCount' => 5,
        'petsCount' => 3,
        'decorCount' => 2,
        'characterCount' => 1,
        'errors' => [],
        'cachedAt' => now()->toISOString(),
    ];

    Cache::put('account_score:v'.ScoreWeights::VERSION.':test-session-id', $cachedData, 86400);

    $accountScoreService = resolve(AccountScoreService::class);
    $result = $accountScoreService->getOrCompute();

    expect($result)->toHaveKey('status', 'ready')
        ->and($result)->toHaveKey('data')
        ->and($result['data']['mountsCount'])->toBe(5);
});

test('it starts computing when no cache', function (): void {
    $mockUserCharService = $this->mock(UserCharacterService::class);
    /** @var \Mockery\Expectation $authExp */
    $authExp = $mockUserCharService->shouldReceive('isAuthenticated');
    $authExp->once()->andReturn(true);

    /** @var \Mockery\Expectation $charsExp */
    $charsExp = $mockUserCharService->shouldReceive('getUserCharacters');
    $charsExp->once()->andReturn([
        ['realmSlug' => 'hyjal', 'name' => 'Thrall'],
        ['realmSlug' => 'hyjal', 'name' => 'Jaina'],
    ]);

    $mockProfileService = $this->mock(CharacterProfileService::class);
    /** @var \Mockery\Expectation $profileExp */
    $profileExp = $mockProfileService->shouldReceive('getProfile');
    $profileExp->once()->andReturn(new App\Application\DTOs\CharacterProfileDTO(
        name: 'Thrall',
        realm: 'Hyjal',
        race: 'Orc',
        class: 'Chaman',
        classId: 7,
        level: 80,
        ilvl: 600,
        faction: 'Horde',
        avatarUrl: '',
        classIconUrl: '',
        collections: [],
        mountsCount: 0,
        petsCount: 0,
    ));

    Session::shouldReceive('getId')->andReturn('test-session-id');

    $accountScoreService = resolve(AccountScoreService::class);
    $result = $accountScoreService->getOrCompute();

    expect($result)->toHaveKey('status', 'computing')
        ->and($result)->toHaveKey('progress')
        ->and($result['progress']['total'])->toBe(2)
        ->and($result['progress']['loaded'])->toBe(1);
});

test('it invalidates cache', function (): void {
    $this->mock(UserCharacterService::class);
    $this->mock(CharacterProfileService::class);

    Session::shouldReceive('getId')->andReturn('test-session-id');

    Cache::put('account_score:v'.ScoreWeights::VERSION.':test-session-id', ['some' => 'data'], 86400);
    Cache::put('account_score:v'.ScoreWeights::VERSION.':test-session-id:progress', ['some' => 'progress'], 3600);

    $accountScoreService = resolve(AccountScoreService::class);
    $accountScoreService->invalidate();

    expect(Cache::get('account_score:v'.ScoreWeights::VERSION.':test-session-id'))->toBeNull()
        ->and(Cache::get('account_score:v'.ScoreWeights::VERSION.':test-session-id:progress'))->toBeNull();
});

test('it attaches the computed score to the finalized payload', function (): void {
    $mockUserCharService = $this->mock(UserCharacterService::class);
    /** @var \Mockery\Expectation $authExp */
    $authExp = $mockUserCharService->shouldReceive('isAuthenticated');
    $authExp->once()->andReturn(true);

    /** @var \Mockery\Expectation $charsExp */
    $charsExp = $mockUserCharService->shouldReceive('getUserCharacters');
    $charsExp->once()->andReturn([['realmSlug' => 'hyjal', 'name' => 'Thrall']]);

    $mockProfileService = $this->mock(CharacterProfileService::class);
    /** @var \Mockery\Expectation $profileExp */
    $profileExp = $mockProfileService->shouldReceive('getProfile');
    $profileExp->once()->andReturn(new App\Application\DTOs\CharacterProfileDTO(
        name: 'Thrall',
        realm: 'Hyjal',
        race: 'Orc',
        class: 'Chaman',
        classId: 7,
        level: 80,
        ilvl: 600,
        faction: 'Horde',
        avatarUrl: '',
        classIconUrl: '',
        collections: [],
        mountsCount: 0,
        petsCount: 0,
        mounts: [
            ['id' => 1, 'name' => 'A', 'is_completed' => true],
            ['id' => 2, 'name' => 'B', 'is_completed' => false],
        ],
        appearances: [['slot' => 'HEAD', 'category' => 'armor', 'total' => 100, 'completed' => 25]],
    ));

    Session::shouldReceive('getId')->andReturn('test-session-id');

    $result = resolve(AccountScoreService::class)->getOrCompute();

    /** @var \App\Domain\ValueObjects\CompletionScore $score */
    $score = $result['data']['score'];

    // Seules montures (50 %) et garde-robe (25 %) sont applicables.
    $weights = ScoreWeights::WEIGHTS;
    $expected = round(
        (50 * $weights['mounts'] + 25 * $weights['transmog']) / ($weights['mounts'] + $weights['transmog']),
        1,
    );

    expect($result['status'])->toBe('ready')
        ->and($score->version)->toBe(ScoreWeights::VERSION)
        ->and($score->global)->toBe($expected)
        ->and($score->dimensions)->toHaveCount(count($weights));
});

function accountScoreProfile(string $name, int $mountsDone = 0): App\Application\DTOs\CharacterProfileDTO
{
    return new App\Application\DTOs\CharacterProfileDTO(
        name: $name, realm: 'Hyjal', race: 'Orc', class: 'Chaman', classId: 7, level: 80, ilvl: 600,
        faction: 'Horde', avatarUrl: '', classIconUrl: '', collections: [], mountsCount: 0, petsCount: 0,
        mounts: [['id' => 1, 'name' => 'Loup', 'is_completed' => $mountsDone > 0]],
    );
}

/**
 * @param  list<array{realmSlug: string, name: string}>  $characters
 * @param  array<string, App\Application\DTOs\CharacterProfileDTO|Throwable>  $profilesByName
 */
function accountScoreServiceFor(array $characters, array $profilesByName): AccountScoreService
{
    $mock = Mockery::mock(UserCharacterService::class);
    app()->instance(UserCharacterService::class, $mock);
    $mock->allows('isAuthenticated')->andReturn(true);
    $mock->allows('getUserCharacters')->andReturn($characters);

    $profileService = Mockery::mock(CharacterProfileService::class);
    app()->instance(CharacterProfileService::class, $profileService);
    $profileService->allows('getProfile')->andReturnUsing(function (string $realm, string $name) use ($profilesByName): App\Application\DTOs\CharacterProfileDTO {
        $profile = $profilesByName[$name];
        throw_if($profile instanceof Throwable, $profile);

        return $profile;
    });

    Session::shouldReceive('getId')->andReturn('test-session-id');

    return resolve(AccountScoreService::class);
}

function accountScoreCacheKey(): string
{
    return 'account_score:v'.ScoreWeights::VERSION.':test-session-id';
}

test('an account without character is ready with no data', function (): void {
    $result = accountScoreServiceFor([], [])->getOrCompute();

    expect($result)->toBe(['status' => 'ready', 'data' => null]);
});

test('each call loads one more character and resumes where the previous one stopped', function (): void {
    $accountScoreService = accountScoreServiceFor([
        ['realmSlug' => 'hyjal', 'name' => 'Thrall'],
        ['realmSlug' => 'hyjal', 'name' => 'Jaina'],
        ['realmSlug' => 'hyjal', 'name' => 'Varian'],
    ], [
        'thrall' => accountScoreProfile('Thrall', 1),
        'jaina' => accountScoreProfile('Jaina'),
        'varian' => accountScoreProfile('Varian'),
    ]);

    $first = $accountScoreService->getOrCompute();
    $second = $accountScoreService->getOrCompute();
    $third = $accountScoreService->getOrCompute();

    expect($first)->toBe(['status' => 'computing', 'progress' => ['loaded' => 1, 'errors' => 0, 'total' => 3, 'current' => 'Jaina']])
        ->and($second)->toBe(['status' => 'computing', 'progress' => ['loaded' => 2, 'errors' => 0, 'total' => 3, 'current' => 'Varian']])
        ->and($third['status'])->toBe('ready')
        ->and($third['data']['characterCount'])->toBe(3)
        ->and($third['data']['mountsCount'])->toBe(1);
});

test('the finished result is cached and the progress is dropped', function (): void {
    $accountScoreService = accountScoreServiceFor([['realmSlug' => 'hyjal', 'name' => 'Thrall']], ['thrall' => accountScoreProfile('Thrall')]);

    $result = $accountScoreService->getOrCompute();

    expect(Cache::get(accountScoreCacheKey()))->toBe($result['data'])
        ->and(Cache::has(accountScoreCacheKey().':progress'))->toBeFalse()
        ->and($accountScoreService->getOrCompute())->toBe(['status' => 'ready', 'data' => $result['data']]);
});

test('a character that fails to load is counted as an error and the next one is announced', function (): void {
    Log::spy();

    $accountScoreService = accountScoreServiceFor([
        ['realmSlug' => 'hyjal', 'name' => 'Arthas'],
        ['realmSlug' => 'hyjal', 'name' => 'Thrall'],
        ['realmSlug' => 'hyjal', 'name' => 'Jaina'],
    ], [
        'arthas' => new RuntimeException('Blizzard 503'),
        'thrall' => accountScoreProfile('Thrall'),
        'jaina' => accountScoreProfile('Jaina'),
    ]);

    expect($accountScoreService->getOrCompute())->toBe(['status' => 'computing', 'progress' => ['loaded' => 0, 'errors' => 1, 'total' => 3, 'current' => 'Thrall']])
        ->and($accountScoreService->getOrCompute())->toBe(['status' => 'computing', 'progress' => ['loaded' => 1, 'errors' => 1, 'total' => 3, 'current' => 'Jaina']]);

    Log::shouldHaveReceived('warning')->once()->with('Account score: failed to load character', [
        'character' => 'Arthas',
        'realm' => 'hyjal',
        'error' => 'Blizzard 503',
    ]);
});

test('a last character that fails still finishes the account, with the error listed', function (): void {
    $accountScoreService = accountScoreServiceFor([
        ['realmSlug' => 'hyjal', 'name' => 'Thrall'],
        ['realmSlug' => 'hyjal', 'name' => 'Arthas'],
    ], [
        'thrall' => accountScoreProfile('Thrall'),
        'arthas' => new RuntimeException('Blizzard 503'),
    ]);

    $accountScoreService->getOrCompute();

    $result = $accountScoreService->getOrCompute();

    expect($result['status'])->toBe('ready')
        ->and($result['data']['characterCount'])->toBe(1)
        ->and($result['data']['errors'])->toBe(['Arthas']);
});

test('a progress found in cache is resumed without asking the character list again', function (): void {
    $progress = new App\Application\DTOs\AccountScoreProgress([
        ['realmSlug' => 'hyjal', 'name' => 'Thrall'],
        ['realmSlug' => 'hyjal', 'name' => 'Jaina'],
    ]);
    $progress->mergeProfile(accountScoreProfile('Thrall'));

    $accountScoreService = accountScoreServiceFor([], ['jaina' => accountScoreProfile('Jaina')]);
    Cache::put(accountScoreCacheKey().':progress', $progress, 3600);

    $result = $accountScoreService->getOrCompute();

    expect($result['status'])->toBe('ready')
        ->and($result['data']['characterCount'])->toBe(2);
});

test('a finished progress left in cache is finalized without loading anyone', function (): void {
    $progress = new App\Application\DTOs\AccountScoreProgress([['realmSlug' => 'hyjal', 'name' => 'Thrall']]);
    $progress->mergeProfile(accountScoreProfile('Thrall'));

    $accountScoreService = accountScoreServiceFor([], []);
    Cache::put(accountScoreCacheKey().':progress', $progress, 3600);

    $result = $accountScoreService->getOrCompute();

    expect($result['status'])->toBe('ready')
        ->and($result['data']['characterCount'])->toBe(1)
        ->and(Cache::has(accountScoreCacheKey().':progress'))->toBeFalse();
});

test('a resumed progress skips the characters already failed as well as those already loaded', function (): void {
    $progress = new App\Application\DTOs\AccountScoreProgress([
        ['realmSlug' => 'hyjal', 'name' => 'Arthas'],
        ['realmSlug' => 'hyjal', 'name' => 'Thrall'],
        ['realmSlug' => 'hyjal', 'name' => 'Jaina'],
    ]);
    $progress->errors[] = 'Arthas';
    $progress->mergeProfile(accountScoreProfile('Thrall'));

    $accountScoreService = accountScoreServiceFor([], [
        'arthas' => new RuntimeException('Blizzard 503'),
        'thrall' => new RuntimeException('already loaded'),
        'jaina' => accountScoreProfile('Jaina'),
    ]);
    Cache::put(accountScoreCacheKey().':progress', $progress, 3600);

    $result = $accountScoreService->getOrCompute();

    expect($result['status'])->toBe('ready')
        ->and($result['data']['characterCount'])->toBe(2)
        ->and($result['data']['errors'])->toBe(['Arthas']);
});
