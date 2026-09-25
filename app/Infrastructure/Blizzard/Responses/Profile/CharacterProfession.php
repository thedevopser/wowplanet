<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Profile;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Un métier appris : son niveau global, et un palier par extension où il a été pratiqué.
 */
final readonly class CharacterProfession
{
    /**
     * @param  list<ProfessionTier>  $tiers
     */
    public function __construct(
        public int $professionId,
        public ?string $professionName,
        public ?int $skillPoints,
        public ?int $maxSkillPoints,
        public array $tiers,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $profession = $responsePayload->requiredObject('profession');

        return new self(
            professionId: $profession->requiredInt('id'),
            professionName: $profession->optionalString('name'),
            skillPoints: $responsePayload->optionalInt('skill_points'),
            maxSkillPoints: $responsePayload->optionalInt('max_skill_points'),
            tiers: array_map(ProfessionTier::fromPayload(...), $responsePayload->objectList('tiers')),
        );
    }
}
