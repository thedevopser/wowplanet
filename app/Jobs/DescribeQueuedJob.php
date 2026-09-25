<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Jobs\Contracts\DescribedJob;

/**
 * Hook `Queue::createPayloadUsing` : à la création de la charge utile, `data.command` est
 * encore l'objet job, pas sa forme sérialisée.
 */
final class DescribeQueuedJob
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array{label: string, account: string|null}>
     */
    public function __invoke(string $connection, ?string $queue, array $payload): array
    {
        $data = $payload['data'] ?? null;
        $job = is_array($data) ? ($data['command'] ?? null) : null;

        if (! $job instanceof DescribedJob) {
            return [];
        }

        return [DescribedJob::PAYLOAD_KEY => ['label' => $job->label(), 'account' => $job->account()]];
    }
}
