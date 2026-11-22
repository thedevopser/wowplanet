<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Services\WeeklyActivityService;
use App\Jobs\Contracts\DescribedJob;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Chiffré en file comme dans `failed_jobs` : la charge utile porte l'identifiant Battle.net et le
 * BattleTag du compte. L'étiquette publique, recopiée hors de `data`, reste lisible par la page Santé.
 *
 * @phpstan-import-type WeeklyCharacterReference from WeeklyActivityService
 */
class ComputeWeeklyActivityJob implements DescribedJob, ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    // @pest-mutate-ignore
    public int $timeout = 300;

    /** Un échec part dans « Jobs échoués » ; le joueur en relance un depuis la page. */
    public int $tries = 1; // @pest-mutate-ignore

    /**
     * @param  list<WeeklyCharacterReference>  $characters
     */
    public function __construct(
        public readonly string $jobId,
        public readonly string $bnetUserId,
        public readonly array $characters,
        public readonly string $battleTag,
        public readonly string $locale = 'fr',
    ) {
        $this->queue = 'imports';
    }

    public function handle(WeeklyActivityService $weeklyActivityService): void
    {
        app()->setLocale($this->locale);
        $weeklyActivityService->markJob($this->jobId, 'running');
        $weeklyActivityService->computeAndStore($this->bnetUserId, $this->characters, now()->toDateTimeImmutable());
        $weeklyActivityService->markJob($this->jobId, 'completed');
    }

    /**
     * Appelé par le worker pour une exception comme pour un dépassement de `timeout` : la page ne
     * reste jamais sur « en cours ».
     */
    public function failed(\Throwable $throwable): void
    {
        Log::error('Weekly activity job failed', ['jobId' => $this->jobId, 'error' => $throwable->getMessage()]);

        resolve(WeeklyActivityService::class)->markJob($this->jobId, 'failed');
    }

    public function label(): string
    {
        return 'Semaine de jeu du compte';
    }

    public function account(): string
    {
        return $this->battleTag;
    }
}
