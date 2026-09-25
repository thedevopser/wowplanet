<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Pvp;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * L'index des classements d'une saison, `data/wow/pvp-season/{id}/pvp-leaderboard/index` : les
 * brackets classés, nommés par leur slug.
 */
final readonly class PvpLeaderboardIndexResponse
{
    /**
     * @param  list<string>  $slugs
     */
    public function __construct(public array $slugs) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $slugs = [];
        foreach ($responsePayload->objectList('leaderboards') as $leaderboard) {
            $name = $leaderboard->optionalString('name');
            if ($name !== null && $name !== '') {
                $slugs[] = $name;
            }
        }

        return new self($slugs);
    }
}
