<?php

declare(strict_types=1);

namespace App\Application\Health;

use App\Jobs\Contracts\DescribedJob;

/**
 * Ce qu'une charge utile de la file dit publiquement d'elle-même : son étiquette, à
 * défaut son nom de classe, et sa date de mise en file. La clé `data`, où un job peut
 * porter le jeton Blizzard d'un joueur, n'est jamais lue.
 */
final readonly class QueuedJob
{
    private function __construct(
        public string $label,
        public ?string $account,
        public int $createdAt,
    ) {}

    /**
     * @throws UnreadableQueuedJobException
     */
    public static function fromPayload(string $payload): self
    {
        $decoded = json_decode($payload, true);

        throw_unless(is_array($decoded), UnreadableQueuedJobException::because('not a JSON object'));

        $createdAt = $decoded['createdAt'] ?? null;

        throw_unless(is_int($createdAt), UnreadableQueuedJobException::because('no creation date'));

        if (array_key_exists(DescribedJob::PAYLOAD_KEY, $decoded)) {
            $described = $decoded[DescribedJob::PAYLOAD_KEY];
            $label = is_array($described) ? ($described['label'] ?? null) : null;
            $account = is_array($described) ? ($described['account'] ?? null) : null;

            throw_unless(is_string($label) && ($account === null || is_string($account)), UnreadableQueuedJobException::because('an unreadable public label'));

            return new self($label, $account, $createdAt);
        }

        $displayName = $decoded['displayName'] ?? null;

        throw_unless(is_string($displayName) && $displayName !== '', UnreadableQueuedJobException::because('no job name'));

        return new self(class_basename($displayName), null, $createdAt);
    }
}
