<?php

declare(strict_types=1);

namespace App\Application\Health;

use App\Application\Import\CurrentImport;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Queue\RedisQueue;

/**
 * Ce que porte la queue des imports : jobs en attente, différés ou pris par le worker,
 * l'import en cours, et les échecs qui attendent qu'on décide de leur sort.
 *
 * Les différés comptent à part : un import qui attend la libération du quota se
 * redispatche avec un délai, et le confondre avec un job en souffrance ferait croire à
 * un worker arrêté.
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
     * @return array{status: string, issue: string|null, queue: string, pending: int, delayed: int, reserved: int, current: array{job_id: string, started_at: int}|null, failed: list<array{uuid: string, queue: string, job: string, exception: string, failed_at: string}>}
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
            'failed' => $failed,
        ];
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
