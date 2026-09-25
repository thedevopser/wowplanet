<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Pvp;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Une ligne de classement, réduite aux colonnes affichées : la réponse brute pèse plusieurs Mo.
 */
final readonly class PvpLeaderboardEntry
{
    public function __construct(
        public int $rank,
        public ?string $characterName,
        public ?string $realmSlug,
        public ?string $factionType,
        public int $rating,
        public PvpMatchStatistics $statistics,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $character = $responsePayload->optionalObject('character');

        return new self(
            rank: $responsePayload->lenientInt('rank') ?? 0,
            characterName: $character?->optionalString('name'),
            realmSlug: $character?->optionalObject('realm')?->optionalString('slug'),
            factionType: $responsePayload->optionalObject('faction')?->optionalString('type'),
            rating: $responsePayload->lenientInt('rating') ?? 0,
            statistics: PvpMatchStatistics::fromPayload($responsePayload->optionalObject('season_match_statistics')),
        );
    }
}
