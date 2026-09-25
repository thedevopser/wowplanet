<?php

declare(strict_types=1);

namespace App\Infrastructure\Coverage;

/**
 * Une méthode telle que le clover de PHPUnit la mesure.
 */
final readonly class MethodRisk
{
    /**
     * @param  float  $coverage  Part des instructions de la méthode exécutées, en pourcentage
     */
    public function __construct(
        public string $className,
        public string $methodName,
        public int $complexity,
        public float $coverage,
        public float $crap,
    ) {}
}
