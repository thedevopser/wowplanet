<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\AccountScoreProgress;
use App\Domain\Services\ScoreCalculator;
use App\Domain\ValueObjects\ScoreInput;
use App\Domain\ValueObjects\ScoreWeights;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

/**
 * @phpstan-import-type AccountScoreResult from AccountScoreProgress
 */
class AccountScoreService
{
    // @pest-mutate-ignore
    private const BATCH_SIZE = 1;

    // @pest-mutate-ignore
    private const CACHE_TTL = 86400;

    // @pest-mutate-ignore
    private const PROGRESS_TTL = 3600;

    public function __construct(
        private readonly CharacterProfileService $characterProfileService,
        private readonly UserCharacterService $userCharacterService,
        private readonly ScoreCalculator $scoreCalculator,
    ) {}

    /**
     * @return array{status: string, data?: AccountScoreResult|null, progress?: array{loaded: int, errors: int, total: int, current: string}}
     */
    public function getOrCompute(): array
    {
        if (! $this->userCharacterService->isAuthenticated()) {
            return ['status' => 'unauthenticated'];
        }

        $cacheKey = $this->getCacheKey();

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            /** @var AccountScoreResult $cached written only by finalize(), below */
            return ['status' => 'ready', 'data' => $cached];
        }

        $progressKey = $cacheKey.':progress';
        $progress = Cache::get($progressKey);

        if (! $progress instanceof AccountScoreProgress) {
            $characters = $this->userCharacterService->getUserCharacters();
            if ($characters === []) {
                return ['status' => 'ready', 'data' => null];
            }

            $charList = array_map(static fn (array $c): array => [
                'realmSlug' => $c['realmSlug'],
                'name' => $c['name'],
            ], $characters);

            $progress = new AccountScoreProgress($charList);
        }

        $total = count($progress->characters);
        $current = $progress->processed + count($progress->errors);

        if ($current >= $total) {
            return $this->finalize($progress, $cacheKey, $progressKey);
        }

        /** @var list<array{realmSlug: string, name: string}> $batch */
        $batch = array_slice($progress->characters, $current, self::BATCH_SIZE);

        foreach ($batch as $char) {
            try {
                $profile = $this->characterProfileService->getProfile(
                    $char['realmSlug'],
                    mb_strtolower($char['name']),
                );
                $progress->mergeProfile($profile);
            } catch (\Exception $exception) {
                Log::warning('Account score: failed to load character', [
                    'character' => $char['name'],
                    'realm' => $char['realmSlug'],
                    'error' => $exception->getMessage(),
                ]);
                $progress->errors[] = $char['name'];
            }
        }

        $current = $progress->processed + count($progress->errors);

        if ($current >= $total) {
            return $this->finalize($progress, $cacheKey, $progressKey);
        }

        Cache::put($progressKey, $progress, self::PROGRESS_TTL);

        $nextChar = $progress->characters[$current] ?? null;

        return [
            'status' => 'computing',
            'progress' => [
                'loaded' => $progress->processed,
                'errors' => count($progress->errors),
                'total' => $total,
                'current' => $nextChar['name'] ?? '',
            ],
        ];
    }

    public function invalidate(): void
    {
        $cacheKey = $this->getCacheKey();
        Cache::forget($cacheKey);
        Cache::forget($cacheKey.':progress');
    }

    /**
     * @return array{status: string, data: AccountScoreResult}
     */
    private function finalize(AccountScoreProgress $accountScoreProgress, string $cacheKey, string $progressKey): array
    {
        $result = $accountScoreProgress->buildResult();
        $result['score'] = $this->scoreCalculator->compute($this->toScoreInput($result));

        Cache::put($cacheKey, $result, self::CACHE_TTL);
        Cache::forget($progressKey);

        return ['status' => 'ready', 'data' => $result];
    }

    /**
     * @param  AccountScoreResult  $result
     */
    private function toScoreInput(array $result): ScoreInput
    {
        return new ScoreInput(
            collections: $result['collections'],
            mounts: $result['mounts'],
            pets: $result['pets'],
            decor: $result['decor'],
            professions: $result['professions'],
            appearances: $result['appearances'],
            raids: $result['raids'],
            bestProfessionStats: $result['bestProfessionStats'],
        );
    }

    /** La version de la formule est dans la clé : un changement de barème purge le cache. */
    private function getCacheKey(): string
    {
        return 'account_score:v'.ScoreWeights::VERSION.':'.Session::getId();
    }
}
