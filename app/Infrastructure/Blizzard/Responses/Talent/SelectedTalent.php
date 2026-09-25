<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Talent;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Un nœud retenu dans un loadout : son rang, et le talent choisi quand le nœud est un choix.
 */
final readonly class SelectedTalent
{
    public function __construct(
        public ?int $nodeId,
        public ?int $rank,
        public ?int $talentId,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        return new self(
            nodeId: $responsePayload->optionalInt('id'),
            rank: $responsePayload->optionalInt('rank'),
            talentId: $responsePayload->optionalObject('tooltip')?->optionalObject('talent')?->optionalInt('id'),
        );
    }
}
