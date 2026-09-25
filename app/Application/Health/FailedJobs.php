<?php

declare(strict_types=1);

namespace App\Application\Health;

use App\Application\Import\CurrentImport;
use App\Application\Import\ImportAlreadyRunningException;
use App\Infrastructure\Logging\AdminAudit;
use App\Jobs\Contracts\DescribedJob;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Facades\Artisan;

/**
 * Les jobs que le worker a abandonnés, et ce que le panneau peut en faire.
 *
 * Relancer remet le job dans sa queue par `queue:retry`, et c'est le worker qui l'exécute :
 * jamais la requête HTTP, qui n'a ni le temps ni la mémoire d'un import.
 */
final readonly class FailedJobs
{
    /** Au-delà, la liste ne diagnostique plus rien : c'est la cause commune qu'il faut chercher. */
    public const LISTED = 50;

    private const string UNREADABLE_JOB = 'Job illisible';

    public function __construct(
        private FailedJobProviderInterface $failedJobProvider,
        private CurrentImport $currentImport,
        private AdminAudit $adminAudit,
    ) {}

    /**
     * @return list<array{uuid: string, queue: string, job: string, exception: string, failed_at: string}>
     */
    public function all(): array
    {
        $failed = [];
        foreach ($this->failedJobProvider->all() as $row) {
            throw_unless(is_object($row), \UnexpectedValueException::class, 'A failed job row is not a record.');
            $failed[] = $this->describe($row);
        }

        usort($failed, static fn (array $left, array $right): int => strcmp($right['failed_at'], $left['failed_at']));

        return array_slice($failed, 0, self::LISTED);
    }

    /**
     * Relancer un import pendant qu'un autre tourne violerait la règle d'un seul import à
     * la fois, et le job relancé ne sait pas prendre le verrou : le refus se fait ici.
     *
     * `queue:retry` relit le job pour recalculer son `retryUntil`, et laisse échapper
     * l'erreur quand la charge utile ne se relit plus. Le job reste alors inscrit parmi
     * les échecs, ce qui est juste : le refus se fait ici, avec sa raison.
     *
     * @throws ImportAlreadyRunningException
     * @throws FailedJobNotFoundException
     * @throws FailedJobNotRetryableException
     */
    public function retry(string $uuid, string $actor): void
    {
        $running = $this->currentImport->jobId();

        if ($running !== null) {
            throw ImportAlreadyRunningException::since($running, (int) $this->currentImport->startedAt(), now()->getTimestamp());
        }

        $this->requireKnown($uuid);

        try {
            Artisan::call('queue:retry', ['id' => [$uuid]]);
        } catch (\Throwable $throwable) {
            throw FailedJobNotRetryableException::because($uuid, $throwable);
        }

        $this->adminAudit->record('Failed job retried from the admin panel', $actor, ['uuid' => $uuid]);
    }

    /**
     * @throws FailedJobNotFoundException
     */
    public function forget(string $uuid, string $actor): void
    {
        $this->requireKnown($uuid);

        $this->failedJobProvider->forget($uuid);

        $this->adminAudit->record('Failed job forgotten from the admin panel', $actor, ['uuid' => $uuid]);
    }

    private function requireKnown(string $uuid): void
    {
        throw_if($this->failedJobProvider->find($uuid) === null, FailedJobNotFoundException::forUuid($uuid));
    }

    /**
     * La ligne vient de la table des échecs, non typée : elle est rétrécie ici, dès sa
     * lecture. Un job qui se décrit est nommé par son étiquette publique, à défaut par sa
     * classe. Seule la première ligne de l'exception est gardée — la trace complète
     * reste en base pour qui la cherche, la page n'a besoin que du motif.
     *
     * @return array{uuid: string, queue: string, job: string, exception: string, failed_at: string}
     */
    private function describe(object $row): array
    {
        $fields = get_object_vars($row);

        $uuid = $fields['uuid'] ?? $fields['id'] ?? null;
        $queue = $fields['queue'] ?? null;
        $exception = $fields['exception'] ?? null;
        $failedAt = $fields['failed_at'] ?? null;
        $payload = json_decode(is_string($fields['payload'] ?? null) ? $fields['payload'] : '', true);
        $described = is_array($payload) ? ($payload[DescribedJob::PAYLOAD_KEY] ?? null) : null;
        $label = is_array($described) ? ($described['label'] ?? null) : null;
        $displayName = is_array($payload) ? ($payload['displayName'] ?? null) : null;
        $job = match (true) {
            is_string($label) => $label,
            is_string($displayName) => $displayName,
            default => self::UNREADABLE_JOB,
        };

        throw_unless(
            is_string($uuid) && is_string($queue) && is_string($exception) && is_string($failedAt),
            \UnexpectedValueException::class,
            'A failed job row is missing its uuid, queue, exception or date.',
        );

        return [
            'uuid' => $uuid,
            'queue' => $queue,
            'job' => $job,
            'exception' => strtok($exception, "\n") ?: $exception,
            'failed_at' => $failedAt,
        ];
    }
}
