<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

final readonly class CompletionScore
{
    /**
     * @param  list<ScoreDimension>  $dimensions  Applicables ou non, dans l'ordre d'affichage
     */
    public function __construct(
        public int $version,
        public float $global,
        public string $rank,
        public array $dimensions,
    ) {}
}
