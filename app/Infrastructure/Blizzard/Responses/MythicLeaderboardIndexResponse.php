<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses;

/**
 * Mythic+ leaderboard index of a connected realm,
 * `connected-realm/{id}/mythic-leaderboard/index`.
 *
 * The only response that gives the season's rotation: `mythic-keystone/dungeon/index` lists
 * every dungeon the mode has ever had, and the season itself only carries its periods.
 */
final readonly class MythicLeaderboardIndexResponse
{
    /**
     * @param  list<MythicDungeon>  $dungeons
     */
    public function __construct(public array $dungeons) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $dungeons = [];

        foreach ($responsePayload->objectList('current_leaderboards') as $leaderboard) {
            $id = $leaderboard->optionalInt('id');
            $name = $leaderboard->optionalString('name');
            if ($id === null) {
                continue;
            }

            if ($name === null) {
                continue;
            }

            if ($name === '') {
                continue;
            }

            $dungeons[] = new MythicDungeon($id, $name);
        }

        return new self($dungeons);
    }
}
