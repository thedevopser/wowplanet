<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Application\Reference\LiveReferenceBuild;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\HourlyBudgetGuard;
use App\Infrastructure\Blizzard\ImportBuildGate;

/**
 * Enchaîne les étapes d'un import, une passe à la fois, et publie ce qu'elles font.
 *
 * Une passe et une seule par appel : c'est ce qui permet à un job de relâcher le worker
 * entre deux étapes, et à une commande de boucler sans que le déroulé diffère. L'état
 * rendu est complet, donc rejouable — une reprise repart du même point que le suivi.
 *
 * Une étape terminée est retenue par la porte de build : un worker redémarré, un
 * conteneur relancé, et l'import reprend à l'étape qui n'a pas abouti plutôt que tout
 * refaire. C'est la base durable de la reprise, le suivi n'étant qu'un affichage.
 *
 * C'est aussi ici que se joue la reprise en main par l'administrateur : `interrupt()` et
 * `resume()` appliquent l'ordre qu'il a laissé, à la frontière où le job l'a lu. Le
 * contrôleur ne fait que poser un drapeau, et rien n'est jamais coupé en pleine écriture.
 */
final readonly class ImportPipeline
{
    public function __construct(
        private ImportStageRunner $importStageRunner,
        private ImportProgressStore $importProgressStore,
        private ImportBuildGate $importBuildGate,
        private StageFreshness $stageFreshness,
        private LiveReferenceBuild $liveReferenceBuild,
        private BlizzardApiClient $blizzardApiClient,
        private HourlyBudgetGuard $hourlyBudgetGuard,
        private ImportWaitReporter $importWaitReporter,
        private ImportLog $importLog,
        private CurrentImport $currentImport,
        private ImportControl $importControl,
        private ImportHistory $importHistory,
    ) {}

    /**
     * Applique l'ordre que l'administrateur a laissé, à la frontière où le job l'a lu.
     *
     * C'est le seul endroit où un import s'interrompt : le contrôleur ne fait que poser
     * un drapeau, et rien n'est jamais interrompu au milieu d'une écriture.
     */
    public function interrupt(ImportRun $importRun, ImportRequest $importRequest): ImportRun
    {
        $now = now()->getTimestamp();

        if ($importRequest->signal === ImportSignal::Cancel) {
            return $this->close($importRun, sprintf('Import annulé par %s.', $importRequest->actor), $now);
        }

        if ($importRequest->isAbandoned($now)) {
            return $this->close($importRun, sprintf(
                'Import abandonné : en pause depuis plus de %d min sans reprise, le verrou est relâché.',
                intdiv(ImportRequest::ABANDON_AFTER_S, 60),
            ), $now);
        }

        return $this->pause($importRun, $importRequest, $now);
    }

    /**
     * Une pause est republiée à chaque battement du job, pour que l'écran sache depuis
     * quand elle dure, mais n'est journalisée qu'à la transition : une ligne toutes les
     * cinq secondes noierait le journal qu'on a mis l'import en pause pour lire.
     */
    private function pause(ImportRun $importRun, ImportRequest $importRequest, int $now): ImportRun
    {
        if ($importRun->status() !== ImportRunState::Paused) {
            $this->note($importRun->jobId, sprintf('Import mis en pause par %s.', $importRequest->actor));
        }

        return $this->publish(
            $importRun->paused($importRequest->requestedAt)
                ->waitingOn(ImportWait::paused($importRequest->waitedSeconds($now))),
        );
    }

    public function resume(ImportRun $importRun): ImportRun
    {
        $this->note($importRun->jobId, 'Import repris, reprise à l\'étape en cours.');

        return $this->publish($importRun->resumed());
    }

    /**
     * Clôt un import qui ne repartira pas : son rapport part au journal comme celui d'un
     * import abouti, mais sous son propre titre, et le verrou est rendu au panneau.
     */
    private function close(ImportRun $importRun, string $reason, int $now): ImportRun
    {
        $this->note($importRun->jobId, $reason);

        $importRun = $this->publish($importRun->cancelled($now));

        $this->report($importRun, $now);
        $this->importHistory->close($importRun, $now);
        $this->importControl->clear($importRun->jobId);
        $this->currentImport->clear();

        return $importRun;
    }

    /**
     * @param  list<ImportStage>  $stages
     * @param  string  $trigger  Qui a lancé l'import : un administrateur, ou la console
     */
    public function begin(string $jobId, array $stages, bool $force, string $trigger): ImportRun
    {
        $importRun = ImportRun::start($jobId, $stages, now()->getTimestamp());

        $this->currentImport->mark($jobId, $importRun->startedAt);
        $this->note($jobId, sprintf(
            'Import démarré — %d %s : %s.',
            count($stages),
            count($stages) > 1 ? 'étapes' : 'étape',
            implode(', ', array_map(static fn (ImportStage $importStage): string => $importStage->label(), $stages)),
        ));

        if (! $force) {
            $blizzardBuild = $this->blizzardApiClient->currentBuild();
            // wago n'est interrogé que si le socle est de la partie : un import de
            // catalogue n'a rien à comparer à son build, et la requête serait perdue.
            $wagoBuild = in_array(ImportStage::Reference, $stages, true)
                ? $this->liveReferenceBuild->current()
                : null;

            foreach ($stages as $stage) {
                if ($this->stageFreshness->isUpToDate($stage, $blizzardBuild, $wagoBuild)) {
                    $importRun = $importRun->withStep($importRun->step($stage)->skipped());
                    $this->note($jobId, sprintf(
                        '%s — déjà à jour pour le build %s, étape sautée.',
                        $stage->label(),
                        $this->upstreamBuildFor($stage, $blizzardBuild, $wagoBuild) ?? 'courant',
                    ));
                }
            }
        }

        // L'entrée d'historique s'ouvre avant toute clôture : un import dont chaque étape
        // est déjà à jour se referme ici même, et sa clôture doit trouver l'entrée à remplir.
        $this->importHistory->open($importRun, $force, $trigger);

        $this->importProgressStore->save($importRun);
        $this->closeIfDone($importRun);

        return $importRun;
    }

    /**
     * Le build auquel une étape vient d'être comparée, pour que le journal nomme celui
     * qui a réellement décidé du saut.
     */
    private function upstreamBuildFor(ImportStage $importStage, ?string $blizzardBuild, ?string $wagoBuild): ?string
    {
        return $importStage === ImportStage::Reference ? $wagoBuild : $blizzardBuild;
    }

    /**
     * Exécute une passe de la première étape qui n'a pas abouti, et republie l'import.
     */
    public function advance(ImportRun $importRun, bool $full, ?int $limit): ImportRun
    {
        $importStep = $this->nextStep($importRun);

        if (! $importStep instanceof ImportStep) {
            return $importRun;
        }

        $ceilingWait = $this->ceilingWait($importStep->stage);

        if ($ceilingWait instanceof ImportWait) {
            $this->note($importRun->jobId, 'En attente : '.$ceilingWait->describe().'.');

            return $this->publish($importRun->waitingOn($ceilingWait));
        }

        $this->importProgressStore->save($importRun->withStep($importStep->started()));
        $this->importWaitReporter->follow($importRun->jobId);
        $this->note($importRun->jobId, $importStep->stage->label().' — démarrage.');

        try {
            $importStageResult = $this->importStageRunner->run($importRun->jobId, $importStep, $full, $limit);
        } finally {
            $this->importWaitReporter->release();
        }

        if ($importStageResult->step->status === ImportStepStatus::Completed) {
            $this->remember($importStageResult->step->stage);
        }

        $this->noteOutcome($importRun->jobId, $importStageResult);

        $importRun = $this->publish(
            $importRun->withStep($importStageResult->step)->waitingOn($importStageResult->wait),
        );

        $this->closeIfDone($importRun);

        return $importRun;
    }

    /**
     * Ce qu'une passe a produit, en une ligne de journal : un rapport pour une étape
     * aboutie, la raison pour une étape en échec, l'avancement pour une étape qui rend
     * la main sans avoir fini.
     */
    private function noteOutcome(string $jobId, ImportStageResult $importStageResult): void
    {
        $step = $importStageResult->step;
        $label = $step->stage->label();

        $this->note($jobId, match ($step->status) {
            ImportStepStatus::Completed => sprintf(
                '%s — terminée · %d créées, %d mises à jour, %d supprimées · %d appels · %d ms.',
                $label,
                $step->rows->created,
                $step->rows->updated,
                $step->rows->deleted,
                $step->apiCalls,
                $step->durationMs,
            ),
            ImportStepStatus::Failed => sprintf('%s — échec : %s', $label, $step->error ?? 'raison inconnue'),
            default => sprintf('%s — %d %% · reprise à la passe suivante.', $label, (int) round($step->fraction() * 100)),
        });

        if ($importStageResult->wait instanceof ImportWait) {
            $this->note($jobId, 'En attente : '.$importStageResult->wait->describe().'.');
        }
    }

    /**
     * Un import qui n'a plus d'étape devant lui cesse d'être celui que le panneau suit,
     * et son journal se referme sur le rapport que l'historique archivera.
     */
    private function closeIfDone(ImportRun $importRun): void
    {
        if (! $this->isDone($importRun)) {
            return;
        }

        $now = now()->getTimestamp();

        $this->report($importRun, $now);
        $this->importHistory->close($importRun, $now);

        $this->currentImport->clear();
    }

    private function report(ImportRun $importRun, int $now): void
    {
        // Le rapport aère ses sections d'une ligne vide, qui n'a pas de sens horodatée
        // dans un journal : on garde les lignes qui disent quelque chose.
        foreach (explode(PHP_EOL, $importRun->summary($now)) as $reportLine) {
            if (trim($reportLine) !== '') {
                $this->note($importRun->jobId, $reportLine);
            }
        }
    }

    private function note(string $jobId, string $line): void
    {
        $this->importLog->note($jobId, $line);
    }

    /**
     * Une étape n'est pas lancée si le plafond réservé aux imports est déjà consommé :
     * sans ce garde-fou, seule la garde-robe le consultait et les autres entraient dans
     * le mur des 429 au lieu d'attendre que la fenêtre se libère.
     */
    private function ceilingWait(ImportStage $importStage): ?ImportWait
    {
        if (! $importStage->usesBlizzardApi()) {
            return null;
        }

        /** @var int $ceiling */
        $ceiling = config('services.blizzard.import_hourly_ceiling', 30000);

        $seconds = $this->hourlyBudgetGuard->secondsUntilAvailable(1, $ceiling);

        return $seconds > 0 ? ImportWait::hourlyBudget($seconds) : null;
    }

    private function publish(ImportRun $importRun): ImportRun
    {
        $importRun = $importRun->withBudgetUsed($this->hourlyBudgetGuard->usedInWindow());

        $this->importProgressStore->save($importRun);

        return $importRun;
    }

    public function isDone(ImportRun $importRun): bool
    {
        if ($importRun->status()->isTerminal()) {
            return true;
        }

        return ! $this->nextStep($importRun) instanceof ImportStep;
    }

    private function nextStep(ImportRun $importRun): ?ImportStep
    {
        foreach ($importRun->steps as $importStep) {
            if (! $importStep->isTerminal()) {
                return $importStep;
            }
        }

        return null;
    }

    /**
     * Un build indéterminé n'est jamais bloquant : on n'inscrit rien plutôt que de
     * retenir une étape sous un build qu'on ne sait pas nommer.
     */
    private function remember(ImportStage $importStage): void
    {
        $build = $this->blizzardApiClient->currentBuild();

        if ($build !== null) {
            $this->importBuildGate->remember($importStage->value, $build);
        }
    }
}
