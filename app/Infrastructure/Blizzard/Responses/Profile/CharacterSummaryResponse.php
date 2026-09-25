<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Profile;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Le résumé `profile/wow/character/{realm}/{name}`, réduit à l'identité de la fiche.
 *
 * Tout y est optionnel : un personnage supprimé, renommé ou jamais joué rend un résumé lacunaire.
 */
final readonly class CharacterSummaryResponse
{
    public function __construct(
        public ?string $name,
        public ?string $realmName,
        public ?string $raceName,
        public ?int $classId,
        public ?string $className,
        public ?int $level,
        public ?int $equippedItemLevel,
        public ?string $factionName,
        public ?string $guildName,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $characterClass = $responsePayload->optionalObject('character_class');

        return new self(
            name: $responsePayload->optionalString('name'),
            realmName: $responsePayload->optionalObject('realm')?->optionalString('name'),
            raceName: $responsePayload->optionalObject('race')?->optionalString('name'),
            classId: $characterClass?->optionalInt('id'),
            className: $characterClass?->optionalString('name'),
            level: $responsePayload->optionalInt('level'),
            equippedItemLevel: $responsePayload->optionalInt('equipped_item_level'),
            factionName: $responsePayload->optionalObject('faction')?->optionalString('name'),
            guildName: $responsePayload->optionalObject('guild')?->optionalString('name'),
        );
    }
}
