<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Profile;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Une meilleure clé de la saison : le donjon, le niveau, le temps, les deux cotes et le groupe.
 */
final readonly class MythicRun
{
    /**
     * @param  list<MythicRunMember>  $members
     */
    public function __construct(
        public ?int $dungeonId,
        public ?string $dungeonName,
        public ?int $keystoneLevel,
        public ?int $durationMs,
        public ?int $completedTimestamp,
        public ?bool $completedWithinTime,
        public ?float $rating,
        public ?RatingColor $ratingColor,
        public ?float $mapRating,
        public ?RatingColor $mapRatingColor,
        public array $members,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $dungeon = $responsePayload->optionalObject('dungeon');
        $mythicRating = $responsePayload->optionalObject('mythic_rating');
        $mapRating = $responsePayload->optionalObject('map_rating');

        return new self(
            dungeonId: $dungeon?->optionalInt('id'),
            dungeonName: $dungeon?->optionalString('name'),
            keystoneLevel: $responsePayload->optionalInt('keystone_level'),
            durationMs: $responsePayload->optionalInt('duration'),
            completedTimestamp: $responsePayload->optionalInt('completed_timestamp'),
            completedWithinTime: $responsePayload->optionalBool('is_completed_within_time'),
            rating: $mythicRating?->optionalFloat('rating'),
            ratingColor: RatingColor::fromRating($mythicRating),
            mapRating: $mapRating?->optionalFloat('rating'),
            mapRatingColor: RatingColor::fromRating($mapRating),
            members: array_map(MythicRunMember::fromPayload(...), $responsePayload->objectList('members')),
        );
    }
}
