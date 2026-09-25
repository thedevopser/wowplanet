<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Profile;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Les hauts faits d'un personnage, `…/achievements`.
 *
 * L'endpoint liste aussi les hauts faits entamés : seul un horodatage de complétion vaut réussite.
 */
final readonly class CharacterAchievementsResponse
{
    /**
     * @param  list<int>  $completedAchievementIds
     */
    public function __construct(
        public array $completedAchievementIds,
        public ?int $totalPoints,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $completedAchievementIds = [];
        foreach ($responsePayload->objectList('achievements') as $achievement) {
            $achievementId = $achievement->optionalInt('id');
            if ($achievementId !== null && $achievement->optionalInt('completed_timestamp') !== null) {
                $completedAchievementIds[] = $achievementId;
            }
        }

        return new self($completedAchievementIds, $responsePayload->optionalInt('total_points'));
    }
}
