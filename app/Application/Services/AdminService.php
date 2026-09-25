<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Import\CurrentImport;
use App\Application\Import\ImportAlreadyRunningException;
use App\Application\Import\ImportControl;
use App\Application\Import\ImportLog;
use App\Application\Import\ImportNotRunningException;
use App\Application\Import\ImportProgressStore;
use App\Application\Import\ImportSignal;
use App\Application\Import\ImportStage;
use App\Application\Reference\ReferencePurge;
use App\Infrastructure\Logging\AdminAudit;
use App\Jobs\RunImportJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * @phpstan-import-type SimpleJobStatus from ImportProgressStore
 * @phpstan-import-type ImportRunStatus from ImportProgressStore
 */
class AdminService
{
    /**
     * La seule commande que le panneau sait lancer. Elle n'est jamais nommée par la
     * requête : le panneau envoie des entités et un mode, la traduction se fait ici.
     */
    private const string IMPORT_COMMAND = 'app:wow-data-import';

    /**
     * La seconde commande que le panneau sait lancer, par le chemin générique du job.
     * Comme l'import, elle n'est jamais nommée par la requête.
     */
    private const string REFERENCE_SYNC_COMMAND = 'app:wow-reference-sync';

    public function __construct(
        private readonly ImportProgressStore $importProgressStore,
        private readonly ImportLog $importLog,
        private readonly CurrentImport $currentImport,
        private readonly ImportControl $importControl,
        private readonly ReferencePurge $referencePurge,
        private readonly AdminAudit $adminAudit,
    ) {}

    /**
     * Retire du magasin de référence les fichiers désignés, et rend ce qui a été libéré.
     *
     * La purge prend le même verrou que les imports et la synchronisation du socle, pour
     * la raison qui les lie déjà : une synchronisation écrit dans ce répertoire au moment
     * même où la purge le balaie, et le classement obsolète/en service serait calculé sur
     * un état en train de changer.
     *
     * @param  list<string>  $filenames  Noms déjà confrontés au disque par la validation
     * @return array{files: int, bytes: int}
     *
     * @throws ImportAlreadyRunningException
     */
    public function purgeReferenceFiles(array $filenames, string $actor): array
    {
        $running = $this->currentImport->jobId();

        if ($running !== null) {
            throw ImportAlreadyRunningException::since(
                $running,
                (int) $this->currentImport->startedAt(),
                now()->getTimestamp(),
            );
        }

        return $this->referencePurge->purge($filenames, $actor);
    }

    /**
     * Pose un ordre sur l'import en cours : il sera lu et agi par le job, à la frontière
     * de tranche suivante. Rien n'est interrompu ici, et c'est ce qui garantit qu'une
     * annulation ne tombe jamais au milieu d'une écriture.
     *
     * @throws ImportNotRunningException
     */
    public function steerImport(string $jobId, ImportSignal $importSignal, string $actor): void
    {
        $this->requireRunning($jobId);

        $this->importControl->request($jobId, $importSignal, $actor, now()->getTimestamp());

        $this->auditImport($jobId, $importSignal->value, $actor);
    }

    /**
     * Reprendre, c'est lever l'ordre posé : le battement du job repart de lui-même, avec
     * la charge qu'il porte depuis le lancement.
     *
     * @throws ImportNotRunningException
     */
    public function resumeImport(string $jobId, string $actor): void
    {
        $this->requireRunning($jobId);

        $this->importControl->clear($jobId);

        $this->auditImport($jobId, 'resume', $actor);
    }

    /**
     * @throws ImportNotRunningException
     */
    private function requireRunning(string $jobId): void
    {
        $running = $this->currentImport->jobId();

        throw_if($running !== $jobId, ImportNotRunningException::forJob($jobId, $running));
    }

    /**
     * Le journal d'import vit une journée ; la piste d'audit de qui a interrompu quoi doit
     * survivre plus longtemps, et part donc aussi sur le canal d'audit.
     */
    private function auditImport(string $jobId, string $signal, string $actor): void
    {
        $this->adminAudit->record('Import steered from the admin panel', $actor, [
            'jobId' => $jobId,
            'signal' => $signal,
        ]);
    }

    /**
     * Met un import en file et rend son identifiant de suivi.
     *
     * Le verrou est pris ici, et non quand le worker prend le job : entre le clic et la
     * prise du job il s'écoule assez de temps pour qu'un second onglet en lance un autre.
     *
     * @param  list<string>  $stages  Entités demandées, ignorées quand tout est demandé
     * @param  string  $actor  L'administrateur qui lance, que l'historique retiendra
     *
     * @throws ImportAlreadyRunningException
     */
    public function startImport(bool $everything, array $stages, bool $force, string $actor): string
    {
        $jobId = Str::uuid()->toString();
        $startedAt = now()->getTimestamp();

        if (! $this->currentImport->tryMark($jobId, $startedAt)) {
            throw ImportAlreadyRunningException::since(
                (string) $this->currentImport->jobId(),
                (int) $this->currentImport->startedAt(),
                $startedAt,
            );
        }

        $parameters = ['--type' => $everything ? 'all' : $this->normalise($stages)];

        if ($force) {
            $parameters['--force'] = true;
        }

        try {
            Cache::put('admin_import:'.$jobId, ['status' => 'pending', 'output' => null], 3600);
            dispatch(new RunImportJob($jobId, self::IMPORT_COMMAND, $parameters, $actor));
        } catch (\Throwable $throwable) {
            // Un verrou laissé posé sur un import qui n'a jamais démarré bloquerait le
            // panneau jusqu'à son expiration.
            $this->currentImport->clear();

            throw $throwable;
        }

        return $jobId;
    }

    /**
     * Met en file une synchronisation du socle de référence, sur une table ou sur tout.
     *
     * Elle prend le même verrou que les imports, et c'est délibéré : un socle qui change
     * sous un import produirait des résultats incohérents, et les deux sens du refus
     * découlent du seul pointeur.
     *
     * @param  string|null  $source  Nom de table DB2, déjà validé contre le catalogue
     *
     * @throws ImportAlreadyRunningException
     */
    public function startReferenceSync(?string $source): string
    {
        $jobId = Str::uuid()->toString();
        $startedAt = now()->getTimestamp();

        if (! $this->currentImport->tryMark($jobId, $startedAt)) {
            throw ImportAlreadyRunningException::since(
                (string) $this->currentImport->jobId(),
                (int) $this->currentImport->startedAt(),
                $startedAt,
            );
        }

        $parameters = $source === null ? [] : ['--table' => $source];

        try {
            Cache::put('admin_import:'.$jobId, ['status' => 'pending', 'output' => null], 3600);
            $this->importLog->note($jobId, $source === null
                ? 'Synchronisation du socle de référence — toutes les tables.'
                : 'Synchronisation du socle de référence — '.$source.'.');
            dispatch(new RunImportJob($jobId, self::REFERENCE_SYNC_COMMAND, $parameters));
        } catch (\Throwable $throwable) {
            $this->currentImport->clear();

            throw $throwable;
        }

        return $jobId;
    }

    public function clearCaches(): string
    {
        $output = '';
        foreach (['config:clear', 'route:clear', 'view:clear', 'cache:clear'] as $cmd) {
            Artisan::call($cmd);
            $output .= Artisan::output();
        }

        return $output;
    }

    public function toggleMaintenance(bool $enable, ?string $secret = null): string
    {
        if ($enable) {
            $params = [];
            if ($secret !== null && $secret !== '') {
                $params['--secret'] = $secret;
            }

            Artisan::call('down', $params);
        } else {
            Artisan::call('up');
        }

        return Artisan::output();
    }

    public function isInMaintenance(): bool
    {
        return app()->isDownForMaintenance();
    }

    /**
     * @param  array{title: string, description: string, color?: int, fields?: list<array{name: string, value: string, inline?: bool}>}  $embed
     */
    public function sendDiscordEmbed(string $channel, array $embed): bool
    {
        /** @var string $changelogUrl */
        $changelogUrl = config('services.discord.webhook_changelog', '');
        /** @var string $discussionUrl */
        $discussionUrl = config('services.discord.webhook_discussion', '');

        $webhookUrl = match ($channel) {
            'changelog' => $changelogUrl,
            'discussion' => $discussionUrl,
            default => throw new \InvalidArgumentException('Unknown channel: '.$channel),
        };

        throw_if($webhookUrl === '', \RuntimeException::class, 'Discord webhook URL not configured for channel: '.$channel);

        $response = Http::timeout(10)->post($webhookUrl, [
            'embeds' => [$embed],
        ]);

        return $response->successful();
    }

    /**
     * L'avancement d'un import, augmenté des lignes de journal apparues depuis la
     * position que le panneau annonce. Le curseur rendu est celui à présenter au
     * prochain appel : c'est ce qui évite de retélécharger le journal entier chaque
     * seconde.
     *
     * @return SimpleJobStatus|ImportRunStatus
     */
    public function getImportJobStatus(string $jobId, int $cursor = 0): array
    {
        $status = $this->importProgressStore->payload($jobId);
        $status['log'] = $this->importLog->since($jobId, $cursor)->toArray();

        return $status;
    }

    /**
     * L'import que le panneau doit suivre s'il en trouve un, qu'il l'ait lancé ou non.
     */
    public function currentImportJobId(): ?string
    {
        return $this->currentImport->jobId();
    }

    /**
     * Une sélection part dans l'ordre de la chaîne, pas dans celui où elle a été cochée :
     * c'est l'ordre dans lequel elle s'exécutera, et celui qu'on relira dans le job.
     *
     * @param  list<string>  $stages
     */
    private function normalise(array $stages): string
    {
        return implode(',', array_map(
            static fn (ImportStage $importStage): string => $importStage->value,
            ImportStage::requested(implode(',', $stages)),
        ));
    }
}
