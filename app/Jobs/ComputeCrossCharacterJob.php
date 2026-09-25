<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\DTOs\CrossCharacterProgress;
use App\Application\Services\CrossCharacterService;
use App\Jobs\Contracts\DescribedJob;
use App\Models\CrossCharacterData;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ComputeCrossCharacterJob implements DescribedJob, ShouldQueue
{
    use Queueable;

    // @pest-mutate-ignore
    public int $timeout = 600;

    /**
     * @param  list<array{name: string, realmSlug: string}>  $characters
     */
    public function __construct(
        public readonly string $jobId,
        public readonly string $bnetUserId,
        public readonly array $characters,
        public readonly string $accessToken,
        public readonly string $battleTag,
    ) {
        $this->queue = 'imports';
    }

    public function handle(CrossCharacterService $crossCharacterService): void
    {
        ini_set('memory_limit', '256M');
        Cache::put($this->cacheKey(), ['status' => 'running'], 3600);

        try {
            $crossCharacterProgress = new CrossCharacterProgress;

            $crossCharacterService->fetchAndMergeCharacters($this->characters, $crossCharacterProgress, $this->accessToken);

            $result = $crossCharacterProgress->buildResult();

            CrossCharacterData::query()->updateOrCreate(['bnet_user_id' => $this->bnetUserId], [
                'data' => $result,
                'character_count' => count($this->characters),
                'fetched_at' => now(),
            ]);

            Cache::put($this->cacheKey(), ['status' => 'completed'], 3600);
        } catch (\Throwable $throwable) {
            Log::error('Cross-character job failed', [
                'jobId' => $this->jobId,
                'error' => $throwable->getMessage(),
            ]);

            Cache::put($this->cacheKey(), ['status' => 'failed'], 3600);
        }
    }

    public function label(): string
    {
        return 'Calcul du score de compte';
    }

    public function account(): string
    {
        return $this->battleTag;
    }

    private function cacheKey(): string
    {
        return 'cross_character:'.$this->jobId;
    }
}
