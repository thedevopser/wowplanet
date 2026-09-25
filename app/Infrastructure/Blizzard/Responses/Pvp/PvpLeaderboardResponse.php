<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Pvp;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Le classement officiel d'un bracket, `data/wow/pvp-season/{id}/pvp-leaderboard/{slug}`.
 */
final readonly class PvpLeaderboardResponse
{
    /**
     * @param  list<PvpLeaderboardEntry>  $entries
     */
    public function __construct(public array $entries) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        return new self(array_map(PvpLeaderboardEntry::fromPayload(...), $responsePayload->objectList('entries')));
    }
}
