<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\CharacterProfileDTO;
use App\Application\DTOs\CrossCharacterProgress;
use App\Application\DTOs\FetchedCharacterProgress;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterAchievementsResponse;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterProfessionsResponse;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterReputationsResponse;
use App\Infrastructure\Blizzard\Responses\Profile\CompletedQuestsResponse;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;
use App\Jobs\ComputeCrossCharacterJob;
use App\Models\CrossCharacterData;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

/**
 * @phpstan-import-type StoredCrossCharacterData from CrossCharacterData
 */
class CrossCharacterService
{
    // @pest-mutate-ignore
    private const CACHE_TTL_HOURS = 24;

    // @pest-mutate-ignore
    private const MAX_RETRIES = 3;

    // @pest-mutate-ignore
    private const RETRY_BASE_DELAY_S = 5;

    public function __construct(
        private readonly BlizzardApiClient $blizzardApiClient,
        private readonly UserCharacterService $userCharacterService,
    ) {}

    /**
     * Dispatch cross-character computation as a background job.
     * Returns cached data if fresh, otherwise queues the computation.
     *
     * @return array{status: string, data?: StoredCrossCharacterData|null, characterCount?: int, jobId?: string}
     *
     * @throws MissingBattleTagException
     */
    public function compute(string $battleTag): array
    {
        if (! $this->userCharacterService->isAuthenticated()) {
            return ['status' => 'unauthenticated'];
        }

        $bnetUserId = $this->getBnetUserId();
        if ($bnetUserId === '') {
            return ['status' => 'unauthenticated'];
        }

        $stored = $this->getStoredData();
        if ($stored !== null) {
            return ['status' => 'ready', 'data' => $stored['data'], 'characterCount' => $stored['character_count']];
        }

        $characters = $this->userCharacterService->getUserCharacters();
        if ($characters === []) {
            return ['status' => 'ready', 'data' => null];
        }

        throw_if($battleTag === '', MissingBattleTagException::forAccount($bnetUserId));

        $jobId = Str::uuid()->toString();
        $token = $this->blizzardApiClient->getAccessToken();

        Cache::put('cross_character:'.$jobId, ['status' => 'pending'], 3600);
        // Seuls le royaume et le nom partent dans la file : c'est tout ce que le calcul lit.
        $characterReferences = array_map(static fn (array $character): array => [
            'name' => $character['name'],
            'realmSlug' => $character['realmSlug'],
        ], $characters);

        dispatch(new ComputeCrossCharacterJob($jobId, $bnetUserId, $characterReferences, $token, $battleTag));

        return ['status' => 'computing', 'jobId' => $jobId];
    }

    /**
     * @return array{status: string}
     */
    public function getJobStatus(string $jobId): array
    {
        /** @var array{status: string} $result */
        $result = Cache::get('cross_character:'.$jobId, ['status' => 'not_found']);

        return $result;
    }

    /**
     * Read stored cross-character data from DB (instant).
     *
     * Les données sont servies telles qu'elles ont été stockées, ancien format compris.
     *
     * @return array{data: StoredCrossCharacterData, character_count: int}|null
     */
    public function getStoredData(): ?array
    {
        $bnetUserId = $this->getBnetUserId();
        if ($bnetUserId === '') {
            return null;
        }

        /** @var CrossCharacterData|null $record */
        $record = CrossCharacterData::query()->find($bnetUserId);

        if ($record === null || $record->fetched_at === null) {
            return null;
        }

        if ($record->fetched_at->diffInHours(now()) >= self::CACHE_TTL_HOURS) {
            return null;
        }

        return ['data' => $record->data, 'character_count' => $record->character_count];
    }

    /**
     * Merge data from a single character profile into existing cross-character data (piggyback).
     */
    public function mergeCurrentCharacter(CharacterProfileDTO $characterProfileDTO): void
    {
        $bnetUserId = $this->getBnetUserId();
        if ($bnetUserId === '') {
            return;
        }

        /** @var CrossCharacterData|null $record */
        $record = CrossCharacterData::query()->find($bnetUserId);

        $crossCharacterProgress = $record !== null && $record->data !== [] ? $this->storedProgress($record) : new CrossCharacterProgress;

        $crossCharacterProgress->mergeFromProfile($characterProfileDTO->name, $characterProfileDTO);

        $result = $crossCharacterProgress->buildResult();

        CrossCharacterData::query()->updateOrCreate(['bnet_user_id' => $bnetUserId], [
            'data' => $result,
            'character_count' => $record !== null ? $record->character_count : 1,
            'fetched_at' => $record?->fetched_at,
        ]);
    }

    /**
     * Fetch and merge characters one by one to minimize memory.
     *
     * Un personnage sans royaume ou sans nom est passé : la liste vient du compte Battle.net,
     * qui rend parfois des entrées incomplètes.
     *
     * @param  list<array<string, scalar|null>>  $characters
     */
    public function fetchAndMergeCharacters(array $characters, CrossCharacterProgress $crossCharacterProgress, ?string $accessToken = null): void
    {
        $token = $accessToken ?? $this->blizzardApiClient->getAccessToken();
        /** @var string $region */
        $region = config('services.blizzard.region', 'eu');
        $baseUrl = sprintf('https://%s.api.blizzard.com', $region);
        $namespace = 'profile-'.$region;

        foreach ($characters as $character) {
            $realm = $character['realmSlug'] ?? null;
            $charName = $character['name'] ?? null;
            if (! is_string($realm)) {
                continue;
            }

            if (! is_string($charName)) {
                continue;
            }

            if ($realm === '') {
                continue;
            }

            if ($charName === '') {
                continue;
            }

            $base = sprintf('%s/profile/wow/character/%s/%s', $baseUrl, mb_strtolower($realm), mb_strtolower($charName));
            $crossCharacterProgress->mergeCharacter($charName, $this->fetchOneCharacter($base, $namespace, $token));
        }
    }

    private function storedProgress(CrossCharacterData $crossCharacterData): CrossCharacterProgress
    {
        return CrossCharacterProgress::fromStored(ResponsePayload::forEndpoint('cross_character_data.data', $crossCharacterData->data));
    }

    /**
     * Fetch 4 endpoints for a single character one at a time to minimize memory.
     */
    private function fetchOneCharacter(string $baseUrl, string $namespace, string $token): FetchedCharacterProgress
    {
        return new FetchedCharacterProgress(
            questIds: CompletedQuestsResponse::fromPayload($this->fetchPayload($baseUrl.'/quests/completed', $namespace, $token))->questIds,
            achievementIds: CharacterAchievementsResponse::fromPayload($this->fetchPayload($baseUrl.'/achievements', $namespace, $token))->completedAchievementIds,
            reputations: CharacterReputationsResponse::fromPayload($this->fetchPayload($baseUrl.'/reputations', $namespace, $token)),
            professions: CharacterProfessionsResponse::fromPayload($this->fetchPayload($baseUrl.'/professions', $namespace, $token)),
        );
    }

    /**
     * Fetch a single endpoint with retry. A missing character (404) or an endpoint that keeps
     * failing gives an empty payload: the other characters still merge. An expired token
     * (401) stops the whole computation: every other endpoint would answer the same.
     *
     * @throws ExpiredBlizzardTokenException
     */
    private function fetchPayload(string $url, string $namespace, string $token): ResponsePayload
    {
        for ($attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++) {
            if ($attempt > 0) {
                Sleep::sleep(self::RETRY_BASE_DELAY_S * (2 ** ($attempt - 1)));
            }

            $response = $this->request($url, $namespace, $token);

            if (! $response instanceof Response) {
                continue;
            }

            throw_if($response->status() === 401, ExpiredBlizzardTokenException::forUrl($url));

            if ($response->successful()) {
                $decoded = $response->json();

                return ResponsePayload::forEndpoint($url, is_array($decoded) ? $decoded : []);
            }

            if ($response->status() === 404) {
                return ResponsePayload::forEndpoint($url, []);
            }

            Log::debug(sprintf('Cross-character fetch error: HTTP %d for %s', $response->status(), $url));
        }

        return ResponsePayload::forEndpoint($url, []);
    }

    private function request(string $url, string $namespace, string $token): ?Response
    {
        try {
            return Http::withToken($token)
                ->withHeaders(['Battlenet-Namespace' => $namespace])
                ->timeout(15)
                ->get($url, ['locale' => 'fr_FR']);
        } catch (\Throwable $throwable) {
            Log::debug(sprintf('Cross-character fetch error: %s for %s', $throwable->getMessage(), $url));

            return null;
        }
    }

    private function getBnetUserId(): string
    {
        /** @var string $userId */
        $userId = Session::get('bnet_user_id', '');

        return $userId;
    }
}
