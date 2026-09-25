<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Profile;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * L'état d'une réputation : palier, progression dans le palier et niveau de renom.
 *
 * `max` vaut zéro quand la progression est au cap, quel que soit le système de réputation.
 */
final readonly class ReputationStanding
{
    public function __construct(
        public ?int $factionId,
        public ?string $factionName,
        public ?string $standingName,
        public ?int $tier,
        public ?int $value,
        public ?int $max,
        public ?int $raw,
        public ?int $renownLevel,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $faction = $responsePayload->optionalObject('faction');
        $standing = $responsePayload->optionalObject('standing');

        return new self(
            factionId: $faction?->optionalInt('id'),
            factionName: $faction?->optionalString('name'),
            standingName: $standing?->optionalString('name'),
            tier: $standing?->optionalInt('tier'),
            value: $standing?->optionalInt('value'),
            max: $standing?->optionalInt('max'),
            raw: $standing?->optionalInt('raw'),
            renownLevel: $standing?->optionalInt('renown_level'),
        );
    }
}
