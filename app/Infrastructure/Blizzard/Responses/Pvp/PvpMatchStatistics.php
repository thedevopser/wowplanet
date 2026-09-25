<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Pvp;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Parties jouées, gagnées et perdues. Une statistique absente ou illisible compte pour zéro.
 */
final readonly class PvpMatchStatistics
{
    public function __construct(
        public int $played,
        public int $won,
        public int $lost,
    ) {}

    public static function fromPayload(?ResponsePayload $responsePayload): self
    {
        return new self(
            played: $responsePayload?->lenientInt('played') ?? 0,
            won: $responsePayload?->lenientInt('won') ?? 0,
            lost: $responsePayload?->lenientInt('lost') ?? 0,
        );
    }
}
