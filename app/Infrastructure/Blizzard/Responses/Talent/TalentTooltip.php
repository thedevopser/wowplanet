<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Talent;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Le talent et le sort qu'affiche un rang, ou l'une des options d'un nœud à choix.
 */
final readonly class TalentTooltip
{
    public function __construct(
        public ?int $talentId,
        public ?string $talentName,
        public ?int $spellId,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $talent = $responsePayload->optionalObject('talent');

        return new self(
            talentId: $talent?->optionalInt('id'),
            talentName: $talent?->optionalString('name'),
            spellId: $responsePayload->optionalObject('spell_tooltip')?->optionalObject('spell')?->optionalInt('id'),
        );
    }
}
