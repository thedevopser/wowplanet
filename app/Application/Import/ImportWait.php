<?php

declare(strict_types=1);

namespace App\Application\Import;

/**
 * L'attente en cours d'un import : sa raison, sa durée annoncée, et de quoi la situer.
 */
final readonly class ImportWait
{
    private function __construct(
        public ImportWaitReason $reason,
        public int $seconds,
        public int $count,
    ) {}

    /** Plafond horaire réservé aux imports atteint : rien ne repartira avant la fenêtre. */
    public static function hourlyBudget(int $seconds): self
    {
        return new self(ImportWaitReason::HourlyBudget, $seconds, 0);
    }

    /** Recul après un 429 : l'API a refusé, le lot repart plus lentement. */
    public static function rateLimitBackoff(int $seconds): self
    {
        return new self(ImportWaitReason::RateLimitBackoff, $seconds, 0);
    }

    /** Lot de requêtes en vol : l'import travaille, il ne bloque pas. */
    public static function batch(int $requests): self
    {
        return new self(ImportWaitReason::Batch, 0, $requests);
    }

    /** Pause demandée depuis le panneau : l'import attend une décision, pas un délai. */
    public static function paused(int $sinceSeconds): self
    {
        return new self(ImportWaitReason::Paused, $sinceSeconds, 0);
    }

    public function describe(): string
    {
        return match ($this->reason) {
            ImportWaitReason::HourlyBudget => sprintf('plafond horaire atteint, reprise dans %d s', $this->seconds),
            ImportWaitReason::RateLimitBackoff => sprintf('recul après un 429, nouvelle tentative dans %d s', $this->seconds),
            ImportWaitReason::Batch => sprintf('lot de %d requêtes en vol', $this->count),
            ImportWaitReason::Paused => sprintf("en pause depuis %d s, en attente d'une reprise", $this->seconds),
        };
    }

    /**
     * @return array{reason: string, seconds: int, count: int}
     */
    public function toArray(): array
    {
        return [
            'reason' => $this->reason->value,
            'seconds' => $this->seconds,
            'count' => $this->count,
        ];
    }

    /**
     * @param  array{reason?: string, seconds?: int, count?: int}  $payload
     */
    public static function fromArray(array $payload): ?self
    {
        $reason = ImportWaitReason::tryFrom($payload['reason'] ?? '');

        if (! $reason instanceof ImportWaitReason) {
            return null;
        }

        return new self($reason, $payload['seconds'] ?? 0, $payload['count'] ?? 0);
    }
}
