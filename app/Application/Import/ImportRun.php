<?php

declare(strict_types=1);

namespace App\Application\Import;

/**
 * L'état complet d'un import, de son lancement à son rapport de fin.
 *
 * Immuable, et sérialisable de bout en bout : c'est ce que le suivi publie sous la clé
 * du job, ce que l'endpoint de progression rend, et ce que le rapport final archive.
 * Le résumé textuel qu'elle sait rendre est ce que le panneau d'administration affiche
 * déjà, ce qui enrichit le suivi sans toucher au front.
 *
 * @phpstan-type ImportStepPayload array{stage: string, status: string, created: int, updated: int, deleted: int, api_calls: int, duration_ms: int, offset: int, total: int, error: string|null}
 * @phpstan-type ImportRunPayload array{job_id: string, started_at: int, budget_used: int, wait: array{reason: string, seconds: int, count: int}|null, steps: list<ImportStepPayload>, interruption?: string|null, interrupted_at?: int|null}
 */
final readonly class ImportRun
{
    /**
     * L'interruption prend le pas sur ce que disent les étapes : `Paused` et `Cancelled`
     * ne sont atteignables par aucun enchaînement d'étapes, seulement par une décision
     * humaine, et son horodatage les accompagne toujours.
     *
     * @param  list<ImportStep>  $steps
     */
    public function __construct(
        public string $jobId,
        public int $startedAt,
        public array $steps,
        public int $budgetUsed,
        public ?ImportWait $wait,
        public ?ImportRunState $interruption = null,
        public ?int $interruptedAt = null,
    ) {
        throw_if(
            $interruption instanceof ImportRunState && ! $interruption->isInterruption(),
            \InvalidArgumentException::class,
            'An import run can only be interrupted as paused or cancelled: '.$interruption?->value,
        );

        throw_if(
            ($interruption instanceof ImportRunState) !== ($interruptedAt !== null),
            \InvalidArgumentException::class,
            'An interruption and its timestamp go together.',
        );
    }

    /**
     * @param  list<ImportStage>  $stages
     */
    public static function start(string $jobId, array $stages, int $startedAt): self
    {
        return new self(
            $jobId,
            $startedAt,
            array_map(ImportStep::pending(...), $stages),
            0,
            null,
        );
    }

    public function step(ImportStage $importStage): ImportStep
    {
        foreach ($this->steps as $step) {
            if ($step->stage === $importStage) {
                return $step;
            }
        }

        throw new \InvalidArgumentException('Stage outside this run: '.$importStage->value);
    }

    public function withStep(ImportStep $importStep): self
    {
        return new self(
            $this->jobId,
            $this->startedAt,
            array_map(
                static fn (ImportStep $existing): ImportStep => $existing->stage === $importStep->stage ? $importStep : $existing,
                $this->steps,
            ),
            $this->budgetUsed,
            $this->wait,
            $this->interruption,
            $this->interruptedAt,
        );
    }

    public function waitingOn(?ImportWait $importWait): self
    {
        return new self($this->jobId, $this->startedAt, $this->steps, $this->budgetUsed, $importWait, $this->interruption, $this->interruptedAt);
    }

    public function withBudgetUsed(int $budgetUsed): self
    {
        return new self($this->jobId, $this->startedAt, $this->steps, $budgetUsed, $this->wait, $this->interruption, $this->interruptedAt);
    }

    public function paused(int $at): self
    {
        return new self($this->jobId, $this->startedAt, $this->steps, $this->budgetUsed, $this->wait, ImportRunState::Paused, $at);
    }

    /**
     * L'annulation s'impose à une pause en cours : dans les deux cas l'import ne repartira
     * pas, et garder la pause ferait croire au panneau qu'une reprise reste possible.
     */
    public function cancelled(int $at): self
    {
        return new self($this->jobId, $this->startedAt, $this->steps, $this->budgetUsed, null, ImportRunState::Cancelled, $at);
    }

    public function resumed(): self
    {
        return new self($this->jobId, $this->startedAt, $this->steps, $this->budgetUsed, null);
    }

    /**
     * Un échec d'étape ne clôt pas l'import : il n'est visible qu'une fois toutes les
     * étapes terminées, faute de quoi le front cesserait de suivre un import qui tourne.
     */
    public function status(): ImportRunState
    {
        if ($this->interruption instanceof ImportRunState) {
            return $this->interruption;
        }

        $terminal = array_filter($this->steps, static fn (ImportStep $importStep): bool => $importStep->isTerminal());

        if (count($terminal) === count($this->steps)) {
            return array_any($this->steps, static fn (ImportStep $importStep): bool => $importStep->status === ImportStepStatus::Failed)
                ? ImportRunState::Failed
                : ImportRunState::Completed;
        }

        // Un import retenu par le plafond horaire avant même sa première étape tourne
        // déjà : le dire en attente le ferait passer pour n'avoir jamais démarré.
        $untouched = $terminal === []
            && ! $this->currentStage() instanceof ImportStage
            && ! $this->wait instanceof ImportWait;

        return $untouched ? ImportRunState::Pending : ImportRunState::Running;
    }

    /**
     * Depuis combien de temps l'import attend une décision. Nul quand il n'attend rien,
     * ce que le panneau distingue de « en pause depuis zéro seconde ».
     */
    public function interruptedForSeconds(int $now): ?int
    {
        return $this->interruptedAt === null ? null : max(0, $now - $this->interruptedAt);
    }

    public function currentStage(): ?ImportStage
    {
        foreach ($this->steps as $step) {
            if ($step->status === ImportStepStatus::Running) {
                return $step->stage;
            }
        }

        return null;
    }

    /**
     * Chaque étape pèse autant que les autres. C'est grossier — la garde-robe dure plus
     * qu'un index de montures — mais honnête : pondérer demanderait des durées de
     * référence que rien ne garantit d'un patch à l'autre.
     */
    public function fraction(): float
    {
        if ($this->steps === []) {
            return 1.0;
        }

        $done = array_sum(array_map(static fn (ImportStep $importStep): float => $importStep->fraction(), $this->steps));

        return $done / count($this->steps);
    }

    public function elapsedSeconds(int $now): int
    {
        return max(0, $now - $this->startedAt);
    }

    /**
     * Temps restant estimé : la durée moyenne des étapes déjà faites, multipliée par le
     * nombre d'étapes restantes.
     *
     * L'estimation se rafraîchit à l'étape et pas à la seconde, et c'est délibéré. Sur
     * une chaîne aux étapes très inégales — vingt secondes pour le socle, deux minutes
     * pour les hauts faits — extrapoler à la seconde enfle tant qu'une étape longue
     * n'aboutit pas, puis tombe à zéro alors qu'il reste tout à faire : les deux se
     * lisent comme une panne. Une étape ignorée par la porte de build ne pèse pas dans
     * la moyenne, n'ayant rien coûté.
     */
    public function etaSeconds(): ?int
    {
        $measured = array_filter(
            $this->steps,
            static fn (ImportStep $importStep): bool => $importStep->isTerminal() && $importStep->durationMs > 0,
        );
        if ($measured === []) {
            return null;
        }

        $remaining = count(array_filter($this->steps, static fn (ImportStep $importStep): bool => ! $importStep->isTerminal()));
        if ($remaining === 0) {
            return 0;
        }

        $meanSeconds = array_sum(array_map(static fn (ImportStep $importStep): int => $importStep->durationMs, $measured))
            / count($measured) / 1000;

        return (int) round($meanSeconds * $remaining);
    }

    /**
     * Le rapport lisible : ce qui est en cours, ce qui est attendu, puis une ligne par
     * étape. C'est le texte que le panneau affiche et celui qu'on archive.
     */
    public function summary(int $now): string
    {
        $lines = [$this->headline($now), ...$this->contextLines($now), ''];

        foreach ($this->steps as $step) {
            $lines[] = $this->describeStep($step);
        }

        return implode(PHP_EOL, $lines);
    }

    private function headline(int $now): string
    {
        return match ($this->status()) {
            ImportRunState::Pending => 'Import en attente de démarrage.',
            ImportRunState::Completed => sprintf('Import terminé en %s.', $this->duration($this->elapsedSeconds($now) * 1000)),
            ImportRunState::Failed => sprintf('Import terminé en %s, avec des échecs.', $this->duration($this->elapsedSeconds($now) * 1000)),
            ImportRunState::Paused => sprintf('Import en pause depuis %s.', $this->duration(($this->interruptedForSeconds($now) ?? 0) * 1000)),
            ImportRunState::Cancelled => $this->interruptedHeadline(),
            default => sprintf(
                'Import en cours — %s (%d %%).',
                $this->currentStage()?->label() ?? 'démarrage',
                (int) round($this->fraction() * 100),
            ),
        };
    }

    /**
     * Le rapport d'un import annulé doit se distinguer au premier mot de celui d'un import
     * complet : c'est la seule chose qui empêche de lire plus tard un catalogue partiel
     * comme un catalogue à jour.
     */
    private function interruptedHeadline(): string
    {
        $handled = count(array_filter($this->steps, static fn (ImportStep $importStep): bool => $importStep->isTerminal()));

        return sprintf(
            'Import interrompu après %s — %d %s sur %d %s.',
            $this->duration($this->elapsedSeconds($this->interruptedAt ?? 0) * 1000),
            $handled,
            $this->plural($handled, 'étape', 'étapes'),
            count($this->steps),
            $this->plural($handled, 'traitée', 'traitées'),
        );
    }

    /**
     * @return list<string>
     */
    private function contextLines(int $now): array
    {
        $lines = [];

        if ($this->wait instanceof ImportWait) {
            $lines[] = 'En attente : '.$this->wait->describe().'.';
        }

        $eta = $this->etaSeconds();

        $lines[] = sprintf(
            'Budget horaire : %s %s consommés · écoulé %s%s',
            $this->count($this->budgetUsed),
            $this->plural($this->budgetUsed, 'appel', 'appels'),
            $this->duration($this->elapsedSeconds($now) * 1000),
            $eta === null || $eta === 0 ? '' : sprintf(' · reste ~%s', $this->duration($eta * 1000)),
        );

        return $lines;
    }

    private function describeStep(ImportStep $importStep): string
    {
        $label = $importStep->stage->label();

        if ($importStep->status === ImportStepStatus::Running && $this->status() === ImportRunState::Cancelled) {
            return sprintf('⊘  %s — abandonnée en cours, à %d %%%s', $label, (int) round($importStep->fraction() * 100), $this->stepFacts($importStep));
        }

        return match ($importStep->status) {
            ImportStepStatus::Pending => sprintf('·  %s — à faire', $label),
            ImportStepStatus::Running => sprintf('⏳ %s — %d %%%s', $label, (int) round($importStep->fraction() * 100), $this->stepFacts($importStep)),
            ImportStepStatus::Completed => sprintf('✓  %s — %s', $label, ltrim($this->stepFacts($importStep), ' ·')),
            ImportStepStatus::Failed => sprintf('✗  %s — échec : %s', $label, $importStep->error ?? 'raison inconnue'),
            ImportStepStatus::Skipped => sprintf('⏭  %s — déjà à jour pour ce build', $label),
        };
    }

    /**
     * Les lignes ne sont rapportées que pour les étapes qui en comptent : le socle de
     * référence est chargé par `COPY` dans des tables sans horodatages, et trois zéros
     * s'y liraient « rien ne s'est passé » au lieu de « sans objet ».
     */
    private function stepFacts(ImportStep $importStep): string
    {
        $facts = [];

        if ($importStep->stage->tables() !== []) {
            $facts[] = sprintf(
                '%s %s, %s %s, %s %s',
                $this->count($importStep->rows->created),
                $this->plural($importStep->rows->created, 'créée', 'créées'),
                $this->count($importStep->rows->updated),
                $this->plural($importStep->rows->updated, 'mise à jour', 'mises à jour'),
                $this->count($importStep->rows->deleted),
                $this->plural($importStep->rows->deleted, 'supprimée', 'supprimées'),
            );
        }

        $facts[] = sprintf('%s %s', $this->count($importStep->apiCalls), $this->plural($importStep->apiCalls, 'appel', 'appels'));
        $facts[] = $this->duration($importStep->durationMs);

        return ' · '.implode(' · ', $facts);
    }

    private function count(int $value): string
    {
        return number_format($value, 0, ',', ' ');
    }

    private function plural(int $value, string $singular, string $plural): string
    {
        return $value > 1 ? $plural : $singular;
    }

    private function duration(int $milliseconds): string
    {
        $seconds = intdiv($milliseconds, 1000);

        if ($milliseconds < 10_000) {
            return number_format($milliseconds / 1000, 1, ',', ' ').' s';
        }

        if ($seconds < 60) {
            return $seconds.' s';
        }

        if ($seconds < 3600) {
            return sprintf('%d min %02d s', intdiv($seconds, 60), $seconds % 60);
        }

        return sprintf('%d h %02d min', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
    }

    /**
     * @return ImportRunPayload
     */
    public function toArray(): array
    {
        return [
            'job_id' => $this->jobId,
            'started_at' => $this->startedAt,
            'budget_used' => $this->budgetUsed,
            'wait' => $this->wait?->toArray(),
            'steps' => array_map(static fn (ImportStep $importStep): array => $importStep->toArray(), $this->steps),
            'interruption' => $this->interruption?->value,
            'interrupted_at' => $this->interruptedAt,
        ];
    }

    /**
     * Une interruption qu'on ne sait pas relire est traitée comme absente : un import
     * republié sans elle repart, là où une valeur refusée par le constructeur ferait
     * échouer toute lecture du suivi.
     *
     * @param  ImportRunPayload  $payload
     */
    public static function fromArray(array $payload): self
    {
        $interruption = ImportRunState::tryFrom($payload['interruption'] ?? '');
        $interrupted = $interruption instanceof ImportRunState && $interruption->isInterruption();

        return new self(
            $payload['job_id'],
            $payload['started_at'],
            array_map(ImportStep::fromArray(...), $payload['steps']),
            $payload['budget_used'],
            $payload['wait'] === null ? null : ImportWait::fromArray($payload['wait']),
            $interrupted ? $interruption : null,
            $interrupted ? ($payload['interrupted_at'] ?? 0) : null,
        );
    }
}
