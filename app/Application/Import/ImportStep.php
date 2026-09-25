<?php

declare(strict_types=1);

namespace App\Application\Import;

/**
 * Ce qu'une étape d'import a fait jusqu'ici : son état, ses lignes, ses appels API,
 * son temps, et son avancement quand elle se compte en fenêtres.
 *
 * Immuable : chaque passe rend une nouvelle étape qui cumule la précédente, ce qui
 * permet de porter l'état d'un job au suivant sans jamais muter ce qui est déjà publié.
 */
final readonly class ImportStep
{
    public function __construct(
        public ImportStage $stage,
        public ImportStepStatus $status,
        public RowTally $rows,
        public int $apiCalls,
        public int $durationMs,
        public int $offset,
        public int $total,
        public ?string $error,
    ) {}

    public static function pending(ImportStage $importStage): self
    {
        return new self($importStage, ImportStepStatus::Pending, RowTally::none(), 0, 0, 0, 0, null);
    }

    /**
     * Marque l'étape en cours avant qu'elle n'ait quoi que ce soit à rapporter : sans
     * cela, une étape qui dure deux minutes n'apparaîtrait au suivi qu'une fois finie.
     */
    public function started(): self
    {
        return new self($this->stage, ImportStepStatus::Running, $this->rows, $this->apiCalls, $this->durationMs, $this->offset, $this->total, null);
    }

    /**
     * Passe rendue sans que l'étape soit finie : l'offset désigne où la reprendre.
     */
    public function advanced(RowTally $rowTally, int $apiCalls, int $durationMs, int $offset, int $total): self
    {
        return new self(
            $this->stage,
            ImportStepStatus::Running,
            $this->rows->plus($rowTally),
            $this->apiCalls + $apiCalls,
            $this->durationMs + $durationMs,
            $offset,
            $total,
            null,
        );
    }

    public function finished(RowTally $rowTally, int $apiCalls, int $durationMs): self
    {
        return new self(
            $this->stage,
            ImportStepStatus::Completed,
            $this->rows->plus($rowTally),
            $this->apiCalls + $apiCalls,
            $this->durationMs + $durationMs,
            $this->total,
            $this->total,
            null,
        );
    }

    public function failed(string $error, int $durationMs): self
    {
        return new self(
            $this->stage,
            ImportStepStatus::Failed,
            $this->rows,
            $this->apiCalls,
            $this->durationMs + $durationMs,
            $this->offset,
            $this->total,
            $error,
        );
    }

    public function skipped(): self
    {
        return new self($this->stage, ImportStepStatus::Skipped, $this->rows, $this->apiCalls, $this->durationMs, $this->offset, $this->total, null);
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    /**
     * Part de l'étape déjà faite, entre 0 et 1. Une étape terminée vaut 1 quoi qu'aient
     * dit ses fenêtres : c'est l'aboutissement qui compte, pas le compte des fenêtres.
     */
    public function fraction(): float
    {
        if ($this->isTerminal()) {
            return 1.0;
        }

        if ($this->total <= 0) {
            return 0.0;
        }

        return min(1.0, $this->offset / $this->total);
    }

    /**
     * @return array{stage: string, status: string, created: int, updated: int, deleted: int, api_calls: int, duration_ms: int, offset: int, total: int, error: string|null}
     */
    public function toArray(): array
    {
        return [
            'stage' => $this->stage->value,
            'status' => $this->status->value,
            'created' => $this->rows->created,
            'updated' => $this->rows->updated,
            'deleted' => $this->rows->deleted,
            'api_calls' => $this->apiCalls,
            'duration_ms' => $this->durationMs,
            'offset' => $this->offset,
            'total' => $this->total,
            'error' => $this->error,
        ];
    }

    /**
     * @param  array{stage: string, status: string, created: int, updated: int, deleted: int, api_calls: int, duration_ms: int, offset: int, total: int, error: string|null}  $payload
     */
    public static function fromArray(array $payload): self
    {
        $stage = ImportStage::tryFrom($payload['stage']);

        throw_unless($stage instanceof ImportStage, \InvalidArgumentException::class, 'Unknown import stage: '.$payload['stage']);

        $status = ImportStepStatus::tryFrom($payload['status']);

        throw_unless($status instanceof ImportStepStatus, \InvalidArgumentException::class, 'Unknown import step status: '.$payload['status']);

        return new self(
            $stage,
            $status,
            new RowTally($payload['created'], $payload['updated'], $payload['deleted']),
            $payload['api_calls'],
            $payload['duration_ms'],
            $payload['offset'],
            $payload['total'],
            $payload['error'],
        );
    }
}
