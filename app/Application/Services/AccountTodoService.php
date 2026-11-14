<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\AccountScoreProgress;
use App\Application\DTOs\CrossCharacterProgress;
use App\Application\Services\Todo\TodoListBuilder;
use App\Infrastructure\Reference\FactionReference;
use App\Models\WowQuest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

/**
 * La page « À faire » du compte : fait avancer les deux calculs dont elle dépend, puis confie la liste
 * à `TodoListBuilder`.
 *
 * Le score du compte avance d'un personnage à chaque appel ; le calcul inter-personnages tourne dans
 * un job, lancé une seule fois et suivi par son identifiant tant qu'il n'a pas abouti.
 *
 * @phpstan-import-type AccountScoreResult from AccountScoreProgress
 * @phpstan-import-type CrossCharacterResult from CrossCharacterProgress
 * @phpstan-import-type TodoCategory from TodoListBuilder
 * @phpstan-import-type TrackedFaction from TodoListBuilder
 *
 * @phpstan-type AccountScoreLoading array{loaded: int, errors: int, total: int, current: string}
 * @phpstan-type AccountTodo array{categories: list<TodoCategory>, characters: list<string>, updatedAt: string|null}
 * @phpstan-type AccountTodoReady array{status: 'ready', data: AccountTodo}
 * @phpstan-type AccountTodoAwaitingCharacters array{status: 'computing', step: 'characters', jobId: string}
 * @phpstan-type AccountTodoStatus array{status: 'unauthenticated'}|array{status: 'failed'}|array{status: 'computing', step: 'account', progress: AccountScoreLoading}|AccountTodoAwaitingCharacters|AccountTodoReady
 */
class AccountTodoService
{
    private const string PENDING_JOB_KEY = 'account_todo:cross_character_job:'; // @pest-mutate-ignore

    private const int PENDING_JOB_TTL = 3600; // @pest-mutate-ignore

    private const string FAILED_JOB = 'failed'; // @pest-mutate-ignore

    private const array RUNNING_JOB = ['pending', 'running']; // @pest-mutate-ignore

    public function __construct(
        private readonly AccountScoreService $accountScoreService,
        private readonly CrossCharacterService $crossCharacterService,
        private readonly FactionReference $factionReference,
        private readonly TodoListBuilder $todoListBuilder,
    ) {}

    /**
     * @return AccountTodoStatus
     */
    public function getOrCompute(string $battleTag): array
    {
        $accountScore = $this->accountScoreService->getOrCompute();

        if ($accountScore['status'] === 'unauthenticated') {
            return ['status' => 'unauthenticated'];
        }

        if ($accountScore['status'] === 'computing') {
            return ['status' => 'computing', 'step' => 'account', 'progress' => $accountScore['progress']];
        }

        $accountProfile = $accountScore['data'];

        if ($accountProfile === null) {
            return ['status' => 'ready', 'data' => ['categories' => [], 'characters' => [], 'updatedAt' => null]];
        }

        $storedCrossCharacter = $this->crossCharacterService->getStoredResult();

        if ($storedCrossCharacter === null) {
            return $this->awaitCrossCharacter($battleTag, $accountProfile);
        }

        return $this->ready($accountProfile, $storedCrossCharacter['result'], $storedCrossCharacter['fetchedAt']);
    }

    /**
     * Les deux calculs repartent de zéro au prochain appel.
     */
    public function refresh(): void
    {
        $this->accountScoreService->invalidate();
        $this->crossCharacterService->invalidate();
        Cache::forget($this->pendingJobKey());
    }

    /**
     * @param  AccountScoreResult  $accountProfile
     * @return AccountTodoAwaitingCharacters|AccountTodoReady|array{status: 'failed'}
     */
    private function awaitCrossCharacter(string $battleTag, array $accountProfile): array
    {
        $pendingJobId = Cache::get($this->pendingJobKey());

        if (is_string($pendingJobId)) {
            return $this->followJob($pendingJobId);
        }

        $computation = $this->crossCharacterService->compute($battleTag);

        if ($computation['status'] === 'computing') {
            Cache::put($this->pendingJobKey(), $computation['jobId'], self::PENDING_JOB_TTL);

            return ['status' => 'computing', 'step' => 'characters', 'jobId' => $computation['jobId']];
        }

        return $this->ready($accountProfile, (new CrossCharacterProgress)->buildResult(), $accountProfile['cachedAt']);
    }

    /**
     * Un job abouti a écrit ses données : le prochain appel les lit. Un job en échec est oublié, pour
     * que le suivant en relance un.
     *
     * @return AccountTodoAwaitingCharacters|array{status: 'failed'}
     */
    private function followJob(string $jobId): array
    {
        $jobStatus = $this->crossCharacterService->getJobStatus($jobId)['status'];

        if (in_array($jobStatus, self::RUNNING_JOB, true)) {
            return ['status' => 'computing', 'step' => 'characters', 'jobId' => $jobId];
        }

        Cache::forget($this->pendingJobKey());

        if ($jobStatus === self::FAILED_JOB) {
            return ['status' => 'failed'];
        }

        return ['status' => 'computing', 'step' => 'characters', 'jobId' => $jobId];
    }

    /**
     * @param  AccountScoreResult  $accountProfile
     * @param  CrossCharacterResult  $crossCharacter
     * @return AccountTodoReady
     */
    private function ready(array $accountProfile, array $crossCharacter, ?string $crossCharacterAt): array
    {
        return ['status' => 'ready', 'data' => [
            'categories' => $this->todoListBuilder->build(
                $accountProfile,
                $crossCharacter,
                $accountProfile['characters'],
                $this->questFactions(),
                $this->trackedFactions(),
            ),
            'characters' => array_column($accountProfile['characters'], 'name'),
            'updatedAt' => $this->oldest($accountProfile['cachedAt'], $crossCharacterAt),
        ]];
    }

    /**
     * @return array<int, string>
     */
    private function questFactions(): array
    {
        $factions = [];

        foreach (WowQuest::query()->whereNotNull('faction')->get(['id', 'faction']) as $quest) {
            $factions[$quest->id] = (string) $quest->faction;
        }

        return $factions;
    }

    /**
     * @return list<TrackedFaction>
     */
    private function trackedFactions(): array
    {
        $names = $this->factionReference->names();
        $trackedFactions = [];

        foreach ($this->factionReference->expansions() as $factionId => $expansionId) {
            $trackedFactions[] = ['id' => $factionId, 'name' => $names[$factionId] ?? '', 'expansion_id' => $expansionId];
        }

        return $trackedFactions;
    }

    private function oldest(?string $first, ?string $second): ?string
    {
        $dates = array_values(array_filter([$first, $second], is_string(...)));

        if ($dates === []) {
            return null;
        }

        usort($dates, static fn (string $earlier, string $later): int => CarbonImmutable::parse($earlier) <=> CarbonImmutable::parse($later));

        return $dates[0];
    }

    private function pendingJobKey(): string
    {
        /** @var string $bnetUserId */
        $bnetUserId = Session::get('bnet_user_id', '');

        return self::PENDING_JOB_KEY.$bnetUserId;
    }
}
