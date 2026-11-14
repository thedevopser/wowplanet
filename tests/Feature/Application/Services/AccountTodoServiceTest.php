<?php

declare(strict_types=1);

use App\Application\DTOs\CrossCharacterProgress;
use App\Application\Services\AccountScoreService;
use App\Application\Services\AccountTodoService;
use App\Application\Services\CrossCharacterService;
use App\Infrastructure\Reference\FactionReference;
use App\Models\WowQuest;
use Illuminate\Support\Facades\Session;

/**
 * The account score result of an account of two characters, one Horde and one Alliance, with one
 * quest zone in progress.
 *
 * @return array{collections: array<int, array{quests: array{total: int, completed: int, zones: list<array{name: string, total: int, completed: int, items: list<array{id: int, name: string, is_completed: bool}>}>}, achievements: array{total: int, completed: int, categories: list<never>}, reputations: array{completed: int, total: int}}>, mounts: list<never>, pets: list<never>, decor: list<never>, professions: list<never>, mountsCount: int, petsCount: int, decorCount: int, bestProfessionStats: null, appearances: list<never>, raids: null, characterCount: int, characters: list<array{name: string, faction: string}>, errors: list<string>, cachedAt: string|null}
 */
function todoAccountScoreData(?string $cachedAt = '2026-11-14T10:00:00Z'): array
{
    return [
        'collections' => [10 => [
            'quests' => ['total' => 3, 'completed' => 1, 'zones' => [[
                'name' => 'Comté-du-Nord',
                'total' => 3,
                'completed' => 1,
                'items' => [
                    ['id' => 1, 'name' => 'Faite', 'is_completed' => true],
                    ['id' => 2, 'name' => 'Pour la Horde', 'is_completed' => false],
                    ['id' => 3, 'name' => 'Neutre', 'is_completed' => false],
                ],
            ]]],
            'achievements' => ['total' => 0, 'completed' => 0, 'categories' => []],
            'reputations' => ['completed' => 0, 'total' => 0],
        ]],
        'mounts' => [],
        'pets' => [],
        'decor' => [],
        'professions' => [],
        'mountsCount' => 0,
        'petsCount' => 0,
        'decorCount' => 0,
        'bestProfessionStats' => null,
        'appearances' => [],
        'raids' => null,
        'characterCount' => 2,
        'characters' => [['name' => 'Apery', 'faction' => 'Horde'], ['name' => 'Jaina', 'faction' => 'Alliance']],
        'errors' => [],
        'cachedAt' => $cachedAt,
    ];
}

/**
 * Wires the service on mocked account score and cross-character computations.
 *
 * @param  array{status: 'ready', data: array{collections: array<int, array{quests: array{total: int, completed: int, zones: list<array{name: string, total: int, completed: int, items: list<array{id: int, name: string, is_completed: bool}>}>}, achievements: array{total: int, completed: int, categories: list<never>}, reputations: array{completed: int, total: int}}>, mounts: list<never>, pets: list<never>, decor: list<never>, professions: list<never>, mountsCount: int, petsCount: int, decorCount: int, bestProfessionStats: null, appearances: list<never>, raids: null, characterCount: int, characters: list<array{name: string, faction: string}>, errors: list<string>, cachedAt: string|null}|null}|array{status: 'computing', progress: array{loaded: int, errors: int, total: int, current: string}}  $accountScore
 * @param  array{result: array{completedQuestIds: list<int>, completedAchievementIds: list<int>, completedRecipeIds: list<int>, questOwners: array<int, string>, achievementOwners: array<int, string>, bestFactionStandings: array<int, array{character_name: string, tier: int, raw: int, renown_level: int, standing_name: string, completed: bool}>, recipeOwners: array<int, string>, skillPointOwners: array<int, array<int, array{character_name: string, skill_points: int, max_skill_points: int}>>}, fetchedAt: string}|null  $storedCrossCharacter
 * @param  array{status: string, jobId?: string}  $crossCharacterCompute
 * @param  array{status: string}  $jobStatus
 */
function accountTodoService(array $accountScore, ?array $storedCrossCharacter, array $crossCharacterCompute = ['status' => 'computing', 'jobId' => 'job-1'], array $jobStatus = ['status' => 'running']): AccountTodoService
{
    Session::put('bnet_user_id', '42');

    $scores = Mockery::mock(AccountScoreService::class);
    $scores->allows('getOrCompute')->andReturn($accountScore);
    $scores->allows('invalidate');
    app()->instance(AccountScoreService::class, $scores);

    $crossCharacter = Mockery::mock(CrossCharacterService::class);
    $crossCharacter->allows('getStoredResult')->andReturn($storedCrossCharacter);
    $crossCharacter->allows('compute')->andReturn($crossCharacterCompute);
    $crossCharacter->allows('getJobStatus')->andReturn($jobStatus);
    $crossCharacter->allows('invalidate');
    app()->instance(CrossCharacterService::class, $crossCharacter);

    $factions = Mockery::mock(FactionReference::class);
    $factions->allows('expansions')->andReturn([2590 => 10, 2594 => 10]);
    $factions->allows('names')->andReturn([2590 => 'Conseil de Dornogal', 2594 => "L'Assemblée des abysses"]);
    app()->instance(FactionReference::class, $factions);

    return resolve(AccountTodoService::class);
}

/**
 * @return array{result: array{completedQuestIds: list<int>, completedAchievementIds: list<int>, completedRecipeIds: list<int>, questOwners: array<int, string>, achievementOwners: array<int, string>, bestFactionStandings: array<int, array{character_name: string, tier: int, raw: int, renown_level: int, standing_name: string, completed: bool}>, recipeOwners: array<int, string>, skillPointOwners: array<int, array<int, array{character_name: string, skill_points: int, max_skill_points: int}>>}, fetchedAt: string}
 */
function storedCrossCharacter(string $fetchedAt = '2026-11-14T09:00:00Z'): array
{
    $result = (new CrossCharacterProgress)->buildResult();
    $result['bestFactionStandings'] = [
        2590 => ['character_name' => 'Apery', 'tier' => 7, 'raw' => 0, 'renown_level' => 0, 'standing_name' => 'Exalté', 'completed' => true],
        2594 => ['character_name' => 'Jaina', 'tier' => 5, 'raw' => 0, 'renown_level' => 12, 'standing_name' => 'Renom 12', 'completed' => false],
    ];

    return ['result' => $result, 'fetchedAt' => $fetchedAt];
}

test('a visitor who is not signed in gets no list', function (): void {
    expect(accountTodoService(['status' => 'unauthenticated'], null)->getOrCompute('Gothdroid#2855'))->toBe(['status' => 'unauthenticated']);
    resolve(CrossCharacterService::class)->shouldNotHaveReceived('compute');
});

test('the cross-character job a player waits for is his own', function (): void {
    $accountTodoService = accountTodoService(['status' => 'ready', 'data' => todoAccountScoreData()], null);
    $accountTodoService->getOrCompute('Gothdroid#2855');

    Session::put('bnet_user_id', '7');
    $accountTodoService->getOrCompute('Thrall#1234');

    resolve(CrossCharacterService::class)->shouldHaveReceived('compute')->with('Thrall#1234')->once();
});

test('while the account score is still loading characters, the list waits for it and says how far it is', function (): void {
    $progress = ['loaded' => 1, 'errors' => 0, 'total' => 3, 'current' => 'Jaina'];

    $result = accountTodoService(['status' => 'computing', 'progress' => $progress], null)->getOrCompute('Gothdroid#2855');

    expect($result)->toBe(['status' => 'computing', 'step' => 'account', 'progress' => $progress]);
});

test('an account without character gets an empty list', function (): void {
    $result = accountTodoService(['status' => 'ready', 'data' => null], null)->getOrCompute('Gothdroid#2855');

    expect($result)->toBe(['status' => 'ready', 'data' => ['categories' => [], 'characters' => [], 'updatedAt' => null]]);
});

test('without fresh cross-character data, it launches their computation once and waits for that job', function (): void {
    $accountTodoService = accountTodoService(['status' => 'ready', 'data' => todoAccountScoreData()], null);

    $calls = [
        $accountTodoService->getOrCompute('Gothdroid#2855'),
        $accountTodoService->getOrCompute('Gothdroid#2855'),
        $accountTodoService->getOrCompute('Gothdroid#2855'),
    ];

    expect($calls)->each->toBe(['status' => 'computing', 'step' => 'characters', 'jobId' => 'job-1']);
    resolve(CrossCharacterService::class)->shouldHaveReceived('compute')->once()->with('Gothdroid#2855');
});

test('a cross-character job that failed is reported, and the next call starts a new one', function (): void {
    $accountTodoService = accountTodoService(['status' => 'ready', 'data' => todoAccountScoreData()], null, jobStatus: ['status' => 'failed']);

    $accountTodoService->getOrCompute('Gothdroid#2855');

    $failed = $accountTodoService->getOrCompute('Gothdroid#2855');
    $retried = $accountTodoService->getOrCompute('Gothdroid#2855');

    expect($failed)->toBe(['status' => 'failed'])
        ->and($retried)->toBe(['status' => 'computing', 'step' => 'characters', 'jobId' => 'job-1']);
    resolve(CrossCharacterService::class)->shouldHaveReceived('compute')->twice();
});

test('once both computations are there, it lists what is left with who can do it', function (): void {
    WowQuest::factory()->create(['id' => 2, 'faction' => 'Horde']);

    $result = accountTodoService(['status' => 'ready', 'data' => todoAccountScoreData()], storedCrossCharacter())->getOrCompute('Gothdroid#2855');

    expect($result['status'])->toBe('ready')
        ->and(array_column($result['data']['categories'], 'key'))->toEqualCanonicalizing(['quests:10:Comté-du-Nord', 'reputations:10']);

    $quests = collect($result['data']['categories'])->firstWhere('key', 'quests:10:Comté-du-Nord');
    $reputations = collect($result['data']['categories'])->firstWhere('key', 'reputations:10');

    expect(array_column($quests['missingItems'], 'doers', 'id'))->toBe([
        2 => ['scope' => 'characters', 'characters' => ['Apery']],
        3 => ['scope' => 'characters', 'characters' => ['Apery', 'Jaina']],
    ])->and($reputations['missingItems'][0])->toMatchArray(['id' => 2594, 'detail' => 'Renom 12']);
});

test('the list names the characters it knows and dates itself by the older of its two computations', function (?string $scoreAt, string $crossAt, string $expected): void {
    $result = accountTodoService(['status' => 'ready', 'data' => todoAccountScoreData($scoreAt)], storedCrossCharacter($crossAt))->getOrCompute('Gothdroid#2855');

    expect($result['data']['characters'])->toBe(['Apery', 'Jaina'])
        ->and($result['data']['updatedAt'])->toBe($expected);
})->with([
    'the score is older' => ['2026-11-14T08:00:00Z', '2026-11-14T09:00:00Z', '2026-11-14T08:00:00Z'],
    'the cross-character data is older' => ['2026-11-14T10:00:00Z', '2026-11-14T09:00:00Z', '2026-11-14T09:00:00Z'],
    'the score carries no date' => [null, '2026-11-14T09:00:00Z', '2026-11-14T09:00:00Z'],
]);

test('refreshing drops both computations and the job it was waiting for', function (): void {
    $accountTodoService = accountTodoService(['status' => 'ready', 'data' => todoAccountScoreData()], null);
    $accountTodoService->getOrCompute('Gothdroid#2855');

    $accountTodoService->refresh();
    $accountTodoService->getOrCompute('Gothdroid#2855');

    resolve(AccountScoreService::class)->shouldHaveReceived('invalidate')->once();
    resolve(CrossCharacterService::class)->shouldHaveReceived('invalidate')->once();
    resolve(CrossCharacterService::class)->shouldHaveReceived('compute')->twice();
});
