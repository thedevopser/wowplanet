<?php

declare(strict_types=1);

namespace App\Application\Health;

use App\Application\Import\CurrentImport;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Queue\RedisQueue;
use Illuminate\Support\Facades\Log;

/**
 * Ce que porte la queue des imports : jobs en attente, différés ou pris par le worker,
 * l'import en cours, et les échecs qui attendent qu'on décide de leur sort.
 *
 * Les différés comptent à part : un import qui attend la libération du quota se
 * redispatche avec un délai, et le confondre avec un job en souffrance ferait croire à
 * un worker arrêté.
 *
 * Les jobs listés sont lus dans la file Redis elle-même, et non dans un registre tenu par
 * les jobs : un job tué par une erreur fatale ou un redémarrage du worker ne laisse pas de
 * ligne fantôme, puisque la file le perd avec lui.
 *
 * @phpstan-type ListedJob array{label: string, account: string|null, since: int}
 */
final readonly class QueueState
{
    /** La seule queue qu'écoute le worker. */
    public const string QUEUE = 'imports';

    public function __construct(
        private QueueFactory $queueFactory,
        private CurrentImport $currentImport,
        private FailedJobs $failedJobs,
    ) {}

    /**
     * @return array{status: string, issue: string|null, queue: string, pending: int, delayed: int, reserved: int, current: array{job_id: string, started_at: int}|null, running: list<ListedJob>, waiting: list<ListedJob>, failed: list<array{uuid: string, queue: string, job: string, exception: string, failed_at: string}>}
     */
    public function snapshot(): array
    {
        $queue = $this->queueFactory->connection();

        throw_unless($queue instanceof RedisQueue, \UnexpectedValueException::class, 'The health page only measures a Redis queue.');

        $failed = $this->failedJobs->all();
        $jobId = $this->currentImport->jobId();
        $startedAt = $this->currentImport->startedAt();

        return [
            'status' => ($failed === [] ? HealthStatus::Ok : HealthStatus::Warning)->value,
            'issue' => $this->issue(count($failed)),
            'queue' => self::QUEUE,
            'pending' => $queue->pendingSize(self::QUEUE),
            'delayed' => $queue->delayedSize(self::QUEUE),
            'reserved' => $queue->reservedSize(self::QUEUE),
            'current' => $jobId === null || $startedAt === null ? null : ['job_id' => $jobId, 'started_at' => $startedAt],
            'running' => $this->running($queue),
            'waiting' => $this->waiting($queue),
            'failed' => $failed,
        ];
    }

    /**
     * Le score d'un job réservé est l'instant où sa réservation expire : sa prise par le
     * worker plus le `retry_after` de la connexion.
     *
     * @return list<ListedJob>
     */
    private function running(RedisQueue $redisQueue): array
    {
        $reserved = $redisQueue->getConnection()->zrange($redisQueue->getQueue(self::QUEUE).':reserved', 0, -1, ['withscores' => true]);
        $retryAfter = $this->retryAfter($redisQueue);

        $running = [];
        foreach (is_array($reserved) ? $reserved : [] as $payload => $expiresAt) {
            $job = $this->read((string) $payload);

            if ($job instanceof QueuedJob && is_numeric($expiresAt)) {
                $running[] = $this->listed($job, (int) $expiresAt - $retryAfter);
            }
        }

        return $running;
    }

    /**
     * @return list<ListedJob>
     */
    private function waiting(RedisQueue $redisQueue): array
    {
        $pending = $redisQueue->getConnection()->lrange($redisQueue->getQueue(self::QUEUE), 0, -1);

        $waiting = [];
        foreach (is_array($pending) ? $pending : [] as $payload) {
            $job = is_string($payload) ? $this->read($payload) : null;

            if ($job instanceof QueuedJob) {
                $waiting[] = $this->listed($job, $job->createdAt);
            }
        }

        return $waiting;
    }

    /**
     * Une charge utile illisible ne doit pas priver l'administrateur de la page qui sert
     * justement à diagnostiquer la file.
     */
    private function read(string $payload): ?QueuedJob
    {
        try {
            return QueuedJob::fromPayload($payload);
        } catch (UnreadableQueuedJobException $unreadableQueuedJobException) {
            Log::warning('An unreadable job was left out of the queue listing', ['reason' => $unreadableQueuedJobException->getMessage()]);

            return null;
        }
    }

    /**
     * @return ListedJob
     */
    private function listed(QueuedJob $queuedJob, int $since): array
    {
        return ['label' => $queuedJob->label, 'account' => $queuedJob->account, 'since' => $since];
    }

    private function retryAfter(RedisQueue $redisQueue): int
    {
        $retryAfter = config(sprintf('queue.connections.%s.retry_after', $redisQueue->getConnectionName()));

        throw_unless(is_int($retryAfter), \UnexpectedValueException::class, 'The Redis queue has no retry_after.');

        return $retryAfter;
    }

    private function issue(int $failed): ?string
    {
        return match (true) {
            $failed === 0 => null,
            $failed === 1 => '1 job échoué en attente de décision.',
            default => sprintf('%d jobs échoués en attente de décision.', $failed),
        };
    }
}
