<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

/**
 * Une dimension notée du score.
 *
 * `applicable` distingue « aucune donnée » (poids redistribué) de « progression nulle ».
 */
final readonly class ScoreDimension
{
    public function __construct(
        public string $key,
        public string $label,
        public float $weight,
        public float $completed,
        public int $total,
        public float $score,
        public bool $applicable,
    ) {}
}
