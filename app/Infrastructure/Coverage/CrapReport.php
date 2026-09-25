<?php

declare(strict_types=1);

namespace App\Infrastructure\Coverage;

use InvalidArgumentException;

/**
 * Les méthodes dont le CRAP dépasse un seuil, de la pire à la moins grave.
 */
final readonly class CrapReport
{
    /**
     * @param  list<MethodRisk>  $risky
     */
    private function __construct(
        public array $risky,
        public int $measured,
        public int $threshold,
    ) {}

    /**
     * Le seuil est exclusif : une méthode qui l'atteint sans le dépasser n'est pas signalée.
     *
     * @param  list<MethodRisk>  $methods
     */
    public static function of(array $methods, int $threshold): self
    {
        if ($threshold < 1) {
            throw new InvalidArgumentException(sprintf('Le seuil de CRAP doit valoir au moins 1, %d reçu.', $threshold));
        }

        $risky = array_values(array_filter(
            $methods,
            fn (MethodRisk $methodRisk): bool => $methodRisk->crap > $threshold,
        ));

        usort($risky, fn (MethodRisk $left, MethodRisk $right): int => $right->crap <=> $left->crap);

        return new self($risky, count($methods), $threshold);
    }

    public function exceedsThreshold(): bool
    {
        return $this->risky !== [];
    }
}
