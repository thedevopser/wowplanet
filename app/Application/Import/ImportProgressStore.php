<?php

declare(strict_types=1);

namespace App\Application\Import;

use Illuminate\Support\Facades\Cache;

/**
 * Publie et relit l'avancement d'un import sous la clé de suivi du job.
 *
 * La valeur stockée garde `status` et `output`, que le panneau d'administration lit
 * déjà, et range l'état structuré à côté : le front continue de fonctionner sans une
 * ligne de changement, et l'endpoint de progression a de quoi détailler.
 *
 * Ce magasin n'est pas l'autorité de reprise. Il vit dans le cache, que le bouton
 * « Vider les caches » efface : la reprise s'appuie sur la charge du job en file et sur
 * la porte de build, toutes deux durables.
 *
 * @phpstan-import-type ImportRunPayload from ImportRun
 *
 * La tranche de journal (`log`) n'est pas tenue ici : `AdminService` l'ajoute en répondant.
 *
 * @phpstan-type ImportLogSliceArray array{cursor: int, lines: list<string>}
 * @phpstan-type SimpleJobStatus array{status: string, output: string|null, log?: ImportLogSliceArray}
 * @phpstan-type ImportRunStatus array{status: string, output: string, stage: string|null, stage_label: string|null, percent: int, started_at: int, elapsed_seconds: int, eta_seconds: int|null, budget: array{used: int, ceiling: int}, interrupted_for: int|null, abandoned_in: int|null, waiting: array{reason: string, seconds: int, message: string}|null, steps: list<array{stage: string, label: string, status: string, percent: int, created: int, updated: int, deleted: int, api_calls: int, duration_ms: int, error: string|null}>, log?: ImportLogSliceArray}
 */
final readonly class ImportProgressStore
{
    private const KEY_PREFIX = 'admin_import:';

    private const TTL_S = 3600;

    public function save(ImportRun $importRun): void
    {
        Cache::put(self::KEY_PREFIX.$importRun->jobId, [
            'status' => $importRun->status()->value,
            'output' => $importRun->summary(now()->getTimestamp()),
            'run' => $importRun->toArray(),
        ], self::TTL_S);
    }

    /**
     * Combien de temps il reste à une pause avant d'être tenue pour abandonnée, ce qui
     * relâchera le verrou. Nul dès que la question ne se pose pas : un import qui tourne
     * ne compte pas à rebours, et un import annulé est déjà fini.
     */
    private function abandonedIn(ImportRun $importRun, int $now): ?int
    {
        if ($importRun->status() !== ImportRunState::Paused) {
            return null;
        }

        return max(0, ImportRequest::ABANDON_AFTER_S - ($importRun->interruptedForSeconds($now) ?? 0));
    }

    public function find(string $jobId): ?ImportRun
    {
        /** @var array{run?: ImportRunPayload}|null $stored */
        $stored = Cache::get(self::KEY_PREFIX.$jobId);

        if ($stored === null || ! isset($stored['run'])) {
            return null;
        }

        return ImportRun::fromArray($stored['run']);
    }

    /**
     * Réponse de l'endpoint de progression. Un job suivi par le chemin des commandes
     * simples — celles qui ne sont pas l'import complet — est rendu tel qu'il l'a
     * toujours été, pour que le panneau ne distingue pas les deux.
     *
     * @return SimpleJobStatus|ImportRunStatus
     */
    public function payload(string $jobId): array
    {
        $importRun = $this->find($jobId);

        if (! $importRun instanceof ImportRun) {
            $stored = Cache::get(self::KEY_PREFIX.$jobId);

            if (! is_array($stored) || ! is_string($stored['status'] ?? null)) {
                return ['status' => 'not_found', 'output' => null];
            }

            return ['status' => $stored['status'], 'output' => is_string($stored['output'] ?? null) ? $stored['output'] : null];
        }

        $now = now()->getTimestamp();

        /** @var int $ceiling */
        $ceiling = config('services.blizzard.import_hourly_ceiling', 30000);

        return [
            'status' => $importRun->status()->value,
            'output' => $importRun->summary($now),
            'stage' => $importRun->currentStage()?->value,
            'stage_label' => $importRun->currentStage()?->label(),
            'percent' => (int) round($importRun->fraction() * 100),
            'started_at' => $importRun->startedAt,
            'elapsed_seconds' => $importRun->elapsedSeconds($now),
            'eta_seconds' => $importRun->etaSeconds(),
            'budget' => [
                'used' => $importRun->budgetUsed,
                'ceiling' => $ceiling,
            ],
            'interrupted_for' => $importRun->interruptedForSeconds($now),
            'abandoned_in' => $this->abandonedIn($importRun, $now),
            'waiting' => $importRun->wait instanceof ImportWait ? [
                'reason' => $importRun->wait->reason->value,
                'seconds' => $importRun->wait->seconds,
                'message' => $importRun->wait->describe(),
            ] : null,
            'steps' => array_map(static fn (ImportStep $importStep): array => [
                'stage' => $importStep->stage->value,
                'label' => $importStep->stage->label(),
                'status' => $importStep->status->value,
                'percent' => (int) round($importStep->fraction() * 100),
                'created' => $importStep->rows->created,
                'updated' => $importStep->rows->updated,
                'deleted' => $importStep->rows->deleted,
                'api_calls' => $importStep->apiCalls,
                'duration_ms' => $importStep->durationMs,
                'error' => $importStep->error,
            ], $importRun->steps),
        ];
    }
}
