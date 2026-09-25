<?php

declare(strict_types=1);

namespace App\Application\Health;

use App\Infrastructure\Blizzard\HourlyBudgetGuard;

/**
 * Le quota Blizzard consommé sur la fenêtre glissante d'une heure, situé face aux trois
 * plafonds qui le bornent : celui que Blizzard publie, celui que le client s'impose, et
 * celui, plus bas, que les imports se réservent pour laisser passer le trafic du site.
 */
final readonly class BlizzardQuota
{
    /**
     * Au-delà de cette part du plafond des imports, un import lancé maintenant passerait
     * l'essentiel de son temps à attendre que la fenêtre se libère.
     */
    public const NEAR_CEILING_RATIO = 0.9;

    public function __construct(
        public int $used,
        public int $importCeiling,
    ) {
        throw_if($used < 0, \InvalidArgumentException::class, 'A consumed quota cannot be negative.');
        throw_if(
            $importCeiling < 1 || $importCeiling > HourlyBudgetGuard::HOURLY_LIMIT,
            \InvalidArgumentException::class,
            'The import ceiling must sit between 1 and the enforced hourly limit.',
        );
    }

    public function status(): HealthStatus
    {
        if ($this->used >= HourlyBudgetGuard::HOURLY_LIMIT) {
            return HealthStatus::Critical;
        }

        if ($this->used >= $this->importCeiling * self::NEAR_CEILING_RATIO) {
            return HealthStatus::Warning;
        }

        return HealthStatus::Ok;
    }

    public function issue(): ?string
    {
        return match ($this->status()) {
            HealthStatus::Critical => 'Quota Blizzard atteint : les appels sont suspendus.',
            HealthStatus::Warning => 'Quota proche du plafond réservé aux imports.',
            default => null,
        };
    }

    /**
     * @return array{status: string, issue: string|null, used: int, import_ceiling: int, enforced_limit: int, published_quota: int}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status()->value,
            'issue' => $this->issue(),
            'used' => $this->used,
            'import_ceiling' => $this->importCeiling,
            'enforced_limit' => HourlyBudgetGuard::HOURLY_LIMIT,
            'published_quota' => HourlyBudgetGuard::PUBLISHED_HOURLY_QUOTA,
        ];
    }
}
