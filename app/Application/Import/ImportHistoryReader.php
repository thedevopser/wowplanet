<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Models\ImportHistoryEntry;
use App\Models\ImportHistoryStep;

/**
 * Ce que le panneau lit de l'historique : la liste chronologique, le détail d'un import
 * situé face aux précédents, et la comparaison de deux imports.
 *
 * Une chute de volumétrie se juge toujours entité par entité, contre le dernier import
 * qui a mesuré la même entité : un import partiel ne dit rien des tables qu'il n'a pas
 * touchées, et les comparer à zéro inventerait des chutes.
 *
 * @phpstan-type HistoryHeader array{job_id: string, trigger: string, mode: string, status: string, started_at: string, finished_at: string|null, budget_used: int}
 * @phpstan-type StageName array{stage: string, label: string}
 * @phpstan-type HistoryListing array{job_id: string, trigger: string, mode: string, status: string, started_at: string, finished_at: string|null, budget_used: int, stages: list<StageName>, shrunk: list<StageName>}
 * @phpstan-type HistoryStep array{stage: string, label: string, status: string, created: int, updated: int, deleted: int, api_calls: int, duration_ms: int, rows_after: int|null, error: string|null}
 * @phpstan-type HistoryStepDetail array{stage: string, label: string, status: string, created: int, updated: int, deleted: int, api_calls: int, duration_ms: int, rows_after: int|null, error: string|null, previous_rows: int|null, previous_job_id: string|null, delta: int|null, shrunk: bool}
 * @phpstan-type Precedents array<string, array<string, array{rows: int, job_id: string}>>
 */
final readonly class ImportHistoryReader
{
    public const PER_PAGE = 25;

    /**
     * Un import resté « en cours » alors que le panneau n'en suit plus aucun, ou en suit
     * un autre, a perdu son worker en route : il n'atteindra jamais sa clôture.
     */
    public const string ABANDONED = 'abandoned';

    /** @var list<string> */
    private const array UNFINISHED = ['pending', 'running', 'paused'];

    public function __construct(
        private CurrentImport $currentImport,
        private ImportLog $importLog,
    ) {}

    /**
     * @return array{entries: list<HistoryListing>, page: int, pages: int}
     */
    public function page(int $page): array
    {
        throw_if($page < 1, \InvalidArgumentException::class, 'History pages start at one.');

        $precedents = $this->precedents();
        $current = $this->currentImport->jobId();

        $entries = ImportHistoryEntry::query()
            ->with('steps')
            ->latest('started_at')
            ->orderByDesc('job_id')
            ->forPage($page, self::PER_PAGE)
            ->get()
            ->map(fn (ImportHistoryEntry $importHistoryEntry): array => [
                ...$this->header($importHistoryEntry, $current),
                'stages' => $this->stages($importHistoryEntry->steps->all()),
                'shrunk' => $this->shrunkStages($importHistoryEntry, $precedents[$importHistoryEntry->job_id] ?? []),
            ])
            ->all();

        return [
            'entries' => array_values($entries),
            'page' => $page,
            'pages' => max(1, (int) ceil(ImportHistoryEntry::query()->count() / self::PER_PAGE)),
        ];
    }

    /**
     * Le journal n'est rendu que tant qu'il vit : au-delà d'une journée, Redis l'a effacé
     * et le détail dit qu'il a expiré, le rapport restant intact.
     *
     * @return array{job_id: string, trigger: string, mode: string, status: string, started_at: string, finished_at: string|null, budget_used: int, steps: list<HistoryStepDetail>, journal: list<string>|null}
     */
    public function entry(string $jobId): array
    {
        $importHistoryEntry = $this->find($jobId);
        $precedents = $this->precedents()[$jobId] ?? [];
        $journal = $this->importLog->since($jobId, 0)->lines;

        return [
            ...$this->header($importHistoryEntry, $this->currentImport->jobId()),
            'steps' => array_values($importHistoryEntry->steps->map(function (ImportHistoryStep $importHistoryStep) use ($precedents): array {
                $previous = $precedents[$importHistoryStep->stage] ?? null;

                return [
                    ...$this->step($importHistoryStep),
                    'previous_rows' => $previous['rows'] ?? null,
                    'previous_job_id' => $previous['job_id'] ?? null,
                    'delta' => $this->delta($previous['rows'] ?? null, $importHistoryStep->rows_after),
                    'shrunk' => $this->hasShrunk($previous['rows'] ?? null, $importHistoryStep->rows_after),
                ];
            })->all()),
            'journal' => $journal === [] ? null : $journal,
        ];
    }

    /**
     * Les deux imports sont remis dans l'ordre chronologique, quel que soit l'ordre dans
     * lequel on les a désignés : une chute se lit de l'ancien vers le récent.
     *
     * @return array{older: HistoryHeader, newer: HistoryHeader, stages: list<array{stage: string, label: string, older_rows: int|null, newer_rows: int|null, delta: int|null, shrunk: bool, older: HistoryStep, newer: HistoryStep}>}
     */
    public function compare(string $firstJobId, string $secondJobId): array
    {
        throw_if($firstJobId === $secondJobId, \InvalidArgumentException::class, 'An import cannot be compared with itself.');

        [$older, $newer] = collect([$this->find($firstJobId), $this->find($secondJobId)])
            ->sortBy(static fn (ImportHistoryEntry $importHistoryEntry): int => $importHistoryEntry->started_at->getTimestamp())
            ->values()
            ->all();

        $olderSteps = $older->steps->keyBy('stage');
        $current = $this->currentImport->jobId();
        $stages = [];

        foreach ($newer->steps as $newerStep) {
            $olderStep = $olderSteps->get($newerStep->stage);

            if (! $olderStep instanceof ImportHistoryStep) {
                continue;
            }

            $stages[] = [
                'stage' => $newerStep->stage,
                'label' => $this->label($newerStep->stage),
                'older_rows' => $olderStep->rows_after,
                'newer_rows' => $newerStep->rows_after,
                'delta' => $this->delta($olderStep->rows_after, $newerStep->rows_after),
                'shrunk' => $this->hasShrunk($olderStep->rows_after, $newerStep->rows_after),
                'older' => $this->step($olderStep),
                'newer' => $this->step($newerStep),
            ];
        }

        return [
            'older' => $this->header($older, $current),
            'newer' => $this->header($newer, $current),
            'stages' => $stages,
        ];
    }

    private function find(string $jobId): ImportHistoryEntry
    {
        return ImportHistoryEntry::query()
            ->with('steps')
            ->findOrFail($jobId);
    }

    /**
     * Pour chaque import et chaque étape, le dernier volume mesuré avant lui sur la même
     * étape. Tout l'historique est relu d'un coup : douze mois d'imports tiennent en
     * quelques centaines de lignes, et une requête par étape affichée coûterait davantage.
     *
     * @return Precedents
     */
    private function precedents(): array
    {
        $measured = ImportHistoryStep::query()
            ->join('import_history', 'import_history.job_id', '=', 'import_history_steps.job_id')
            ->whereNotNull('import_history_steps.rows_after')
            ->oldest('import_history.started_at')
            ->orderBy('import_history.job_id')
            ->get(['import_history_steps.job_id', 'import_history_steps.stage', 'import_history_steps.rows_after']);

        $last = [];
        $precedents = [];

        foreach ($measured as $importHistoryStep) {
            $rows = (int) $importHistoryStep->rows_after;

            if (isset($last[$importHistoryStep->stage])) {
                $precedents[$importHistoryStep->job_id][$importHistoryStep->stage] = $last[$importHistoryStep->stage];
            }

            $last[$importHistoryStep->stage] = ['rows' => $rows, 'job_id' => $importHistoryStep->job_id];
        }

        return $precedents;
    }

    /**
     * @param  array<string, array{rows: int, job_id: string}>  $precedents
     * @return list<StageName>
     */
    private function shrunkStages(ImportHistoryEntry $importHistoryEntry, array $precedents): array
    {
        return $this->stages(array_filter(
            $importHistoryEntry->steps->all(),
            fn (ImportHistoryStep $importHistoryStep): bool => $this->hasShrunk($precedents[$importHistoryStep->stage]['rows'] ?? null, $importHistoryStep->rows_after),
        ));
    }

    /**
     * @param  array<int, ImportHistoryStep>  $steps
     * @return list<StageName>
     */
    private function stages(array $steps): array
    {
        return array_values(array_map(fn (ImportHistoryStep $importHistoryStep): array => [
            'stage' => $importHistoryStep->stage,
            'label' => $this->label($importHistoryStep->stage),
        ], $steps));
    }

    private function hasShrunk(?int $previous, ?int $current): bool
    {
        return $previous !== null && $current !== null && VolumeShrink::between($previous, $current);
    }

    private function delta(?int $previous, ?int $current): ?int
    {
        return $previous === null || $current === null ? null : $current - $previous;
    }

    /**
     * @return HistoryHeader
     */
    private function header(ImportHistoryEntry $importHistoryEntry, ?string $current): array
    {
        $abandoned = in_array($importHistoryEntry->status, self::UNFINISHED, true) && $importHistoryEntry->job_id !== $current;

        return [
            'job_id' => $importHistoryEntry->job_id,
            'trigger' => $importHistoryEntry->trigger,
            'mode' => $importHistoryEntry->mode,
            'status' => $abandoned ? self::ABANDONED : $importHistoryEntry->status,
            'started_at' => $importHistoryEntry->started_at->toIso8601String(),
            'finished_at' => $importHistoryEntry->finished_at?->toIso8601String(),
            'budget_used' => $importHistoryEntry->budget_used,
        ];
    }

    /**
     * @return HistoryStep
     */
    private function step(ImportHistoryStep $importHistoryStep): array
    {
        return [
            'stage' => $importHistoryStep->stage,
            'label' => $this->label($importHistoryStep->stage),
            'status' => $importHistoryStep->status,
            'created' => $importHistoryStep->created,
            'updated' => $importHistoryStep->updated,
            'deleted' => $importHistoryStep->deleted,
            'api_calls' => $importHistoryStep->api_calls,
            'duration_ms' => $importHistoryStep->duration_ms,
            'rows_after' => $importHistoryStep->rows_after,
            'error' => $importHistoryStep->error,
        ];
    }

    /**
     * Une étape disparue de la chaîne depuis l'import reste lisible sous son nom brut.
     */
    private function label(string $stage): string
    {
        return ImportStage::tryFrom($stage)?->label() ?? $stage;
    }
}
