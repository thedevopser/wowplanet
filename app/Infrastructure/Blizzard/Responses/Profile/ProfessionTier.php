<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Profile;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Le palier d'un métier dans une extension : son niveau, et les recettes connues. L'extension
 * ne se lit que dans le nom du palier.
 */
final readonly class ProfessionTier
{
    /**
     * @param  list<int>  $knownRecipeIds
     */
    public function __construct(
        public ?int $id,
        public ?string $name,
        public ?int $skillPoints,
        public ?int $maxSkillPoints,
        public array $knownRecipeIds,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $tier = $responsePayload->optionalObject('tier');

        return new self(
            id: $tier?->optionalInt('id'),
            name: $tier?->optionalString('name'),
            skillPoints: $responsePayload->optionalInt('skill_points'),
            maxSkillPoints: $responsePayload->optionalInt('max_skill_points'),
            knownRecipeIds: array_map(
                static fn (ResponsePayload $responsePayload): int => $responsePayload->requiredInt('id'),
                $responsePayload->objectList('known_recipes'),
            ),
        );
    }
}
