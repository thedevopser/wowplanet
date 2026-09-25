<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Pvp;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Un bracket PvP d'un personnage, `…/pvp-bracket/{slug}`. Un bracket qu'on n'a pas pu lire est vide.
 */
final readonly class PvpBracketResponse
{
    public function __construct(
        public bool $isEmpty,
        public int $seasonId,
        public int $rating,
        public PvpMatchStatistics $seasonStatistics,
        public PvpMatchStatistics $weeklyStatistics,
        public ?int $tierId,
        public ?string $specializationName,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        return new self(
            isEmpty: $responsePayload->isEmpty(),
            seasonId: $responsePayload->optionalObject('season')?->optionalInt('id') ?? 0,
            rating: $responsePayload->lenientInt('rating') ?? 0,
            seasonStatistics: PvpMatchStatistics::fromPayload($responsePayload->optionalObject('season_match_statistics')),
            weeklyStatistics: PvpMatchStatistics::fromPayload($responsePayload->optionalObject('weekly_match_statistics')),
            tierId: $responsePayload->optionalObject('tier')?->optionalInt('id'),
            specializationName: $responsePayload->optionalObject('specialization')?->optionalString('name'),
        );
    }
}
