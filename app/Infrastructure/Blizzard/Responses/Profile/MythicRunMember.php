<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Profile;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Un membre du groupe d'une clé Mythique+.
 */
final readonly class MythicRunMember
{
    public function __construct(
        public ?string $name,
        public ?string $realmName,
        public ?string $specializationName,
        public ?int $equippedItemLevel,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $character = $responsePayload->optionalObject('character');

        return new self(
            name: $character?->optionalString('name'),
            realmName: $character?->optionalObject('realm')?->optionalString('name'),
            specializationName: $responsePayload->optionalObject('specialization')?->optionalString('name'),
            equippedItemLevel: $responsePayload->optionalInt('equipped_item_level'),
        );
    }
}
