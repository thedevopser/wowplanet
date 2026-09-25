<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Import\CurrentImport;
use App\Application\Import\ImportControl;
use App\Application\Import\ImportLog;
use App\Application\Import\ImportLogOutput;
use App\Application\Import\ImportPipeline;
use App\Application\Import\ImportProgressStore;
use App\Application\Import\ImportRequest;
use App\Application\Import\ImportRun;
use App\Application\Import\ImportRunState;
use App\Application\Import\ImportStage;
use App\Application\Import\ImportWaitReason;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Moteur d'un import complet : une passe par invocation, puis re-dispatch.
 *
 * Rendre la main entre deux étapes est ce qui permet à un import de plusieurs minutes
 * de ne pas confisquer le worker, et à une pause de plafond horaire de se traduire par
 * un délai de file plutôt que par un `sleep` dans un job. C'est aussi la reprise : un
 * worker redémarré reprend l'import à l'étape en cours, les étapes abouties étant
 * retenues par la porte de build.
 *
 * Une commande qui n'est pas l'import complet garde un chemin générique, un appel Artisan
 * dont la sortie est publiée telle quelle. Le panneau n'en lance aucune aujourd'hui — sa
 * liste blanche est réduite à l'import orchestré — mais le contrat du job ne présume pas
 * de cette liste.
 */
class RunImportJob implements ShouldQueue
{
    use Queueable;

    /** L'étape la plus longue est bornée par le time-box d'une passe. retry_after (config/queue.php) doit rester supérieur. */
    public int $timeout = 1800;

    /**
     * Rythme auquel un import en pause revient voir s'il peut repartir.
     *
     * Une pause n'est pas un arrêt du chaînage : le job continue de se redispatcher sans
     * exécuter de passe, ce qui garde sa charge — les étapes demandées et le mode — dans
     * la file, là où la reprise la trouve déjà. Court, parce que deux lectures Redis ne
     * coûtent rien et qu'une reprise qui se voit à l'écran vaut mieux qu'une reprise
     * exacte.
     */
    private const PAUSE_HEARTBEAT_S = 5;

    private const ORCHESTRATED_COMMAND = 'app:wow-data-import';

    /** Le nom qu'on donne à un administrateur sans identifiant Battle.net en session. */
    public const string PANEL_TRIGGER = 'administrateur';

    /**
     * @param  array<string, string|int|bool>  $parameters  Options Artisan : `--type`, `--force`, `--table`, `--limit`
     * @param  string  $trigger  L'administrateur qui a lancé l'import, pour l'historique
     */
    public function __construct(
        public readonly string $jobId,
        public readonly string $command,
        public readonly array $parameters = [],
        public readonly string $trigger = self::PANEL_TRIGGER,
    ) {
        $this->queue = 'imports';
    }

    /** Garde-fou : le chaînage de re-dispatch peut s'étaler sur plusieurs heures. */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(24);
    }

    public function handle(
        ImportPipeline $importPipeline,
        ImportProgressStore $importProgressStore,
        ImportControl $importControl,
        ImportLog $importLog,
        CurrentImport $currentImport,
    ): void {
        if ($this->command !== self::ORCHESTRATED_COMMAND) {
            $this->runPlainCommand($importLog, $currentImport);

            return;
        }

        $importRun = $importProgressStore->find($this->jobId)
            ?? $importPipeline->begin($this->jobId, $this->stages(), $this->flag('--force'), $this->trigger);

        $importRequest = $importControl->pending($this->jobId);

        if ($importRequest instanceof ImportRequest) {
            $this->obey($importPipeline->interrupt($importRun, $importRequest));

            return;
        }

        if ($importRun->status() === ImportRunState::Paused) {
            $importRun = $importPipeline->resume($importRun);
        }

        $importRun = $importPipeline->advance($importRun, $this->flag('--full'), $this->limit());

        if ($importPipeline->isDone($importRun)) {
            return;
        }

        $this->reschedule($this->delayFor($importRun));
    }

    /**
     * Une pause se rappelle à intervalle court, une annulation ne se rappelle jamais.
     */
    private function obey(ImportRun $importRun): void
    {
        if ($importRun->status() === ImportRunState::Paused) {
            $this->reschedule(self::PAUSE_HEARTBEAT_S);
        }
    }

    private function reschedule(int $seconds): void
    {
        dispatch(new self($this->jobId, $this->command, $this->parameters, $this->trigger))
            ->delay(now()->addSeconds($seconds));
    }

    /**
     * @return list<ImportStage>
     */
    private function stages(): array
    {
        $type = $this->parameters['--type'] ?? 'all';

        return ImportStage::requested(is_string($type) ? $type : 'all');
    }

    private function flag(string $option): bool
    {
        return (bool) ($this->parameters[$option] ?? false);
    }

    private function limit(): ?int
    {
        $limit = $this->parameters['--limit'] ?? null;

        return is_numeric($limit) ? (int) $limit : null;
    }

    /**
     * Seule une pause de plafond horaire retarde la reprise : un lot en vol ou un recul
     * après 429 sont déjà passés quand la passe rend la main.
     */
    private function delayFor(ImportRun $importRun): int
    {
        return $importRun->wait?->reason === ImportWaitReason::HourlyBudget
            ? $importRun->wait->seconds
            : 0;
    }

    /**
     * Une commande qui n'est pas l'import orchestré, suivie de la même façon que lui : sa
     * sortie part au journal au fil de l'eau plutôt qu'en un bloc publié à la fin, et le
     * verrou est rendu quoi qu'il arrive.
     */
    private function runPlainCommand(ImportLog $importLog, CurrentImport $currentImport): void
    {
        Cache::put('admin_import:'.$this->jobId, ['status' => 'running', 'output' => null], 3600);

        $importLogOutput = new ImportLogOutput($importLog, $this->jobId);

        try {
            Artisan::call($this->command, $this->parameters, $importLogOutput);

            $importLog->note($this->jobId, 'Terminé.');
            Cache::put('admin_import:'.$this->jobId, ['status' => 'completed', 'output' => Artisan::output()], 3600);
        } catch (\Throwable $throwable) {
            Log::error('Import job failed', [
                'jobId' => $this->jobId,
                'command' => $this->command,
                'error' => $throwable->getMessage(),
            ]);

            $importLog->note($this->jobId, 'Commande en échec : '.$throwable->getMessage());

            Cache::put('admin_import:'.$this->jobId, [
                'status' => 'failed',
                'output' => $throwable->getMessage(),
            ], 3600);
        } finally {
            $currentImport->release($this->jobId);
        }
    }
}
