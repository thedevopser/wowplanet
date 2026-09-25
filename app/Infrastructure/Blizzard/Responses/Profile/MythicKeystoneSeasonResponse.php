<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Profile;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * La saison Mythique+ d'un personnage, `…/mythic-keystone-profile/season/{id}` : sa cote et ses
 * meilleures clés.
 */
final readonly class MythicKeystoneSeasonResponse
{
    /**
     * @param  list<MythicRun>  $bestRuns
     */
    public function __construct(
        public ?int $seasonId,
        public ?float $rating,
        public ?RatingColor $ratingColor,
        public array $bestRuns,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $mythicRating = $responsePayload->optionalObject('mythic_rating');

        return new self(
            seasonId: $responsePayload->optionalObject('season')?->optionalInt('id'),
            rating: $mythicRating?->optionalFloat('rating'),
            ratingColor: RatingColor::fromRating($mythicRating),
            bestRuns: array_map(MythicRun::fromPayload(...), $responsePayload->objectList('best_runs')),
        );
    }
}
