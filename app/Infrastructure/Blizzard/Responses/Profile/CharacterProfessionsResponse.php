<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Profile;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Les métiers d'un personnage, `…/professions` : les principaux d'abord, puis les secondaires.
 */
final readonly class CharacterProfessionsResponse
{
    /**
     * @param  list<CharacterProfession>  $professions
     */
    public function __construct(public array $professions) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        return new self(array_map(
            CharacterProfession::fromPayload(...),
            [...$responsePayload->objectList('primaries'), ...$responsePayload->objectList('secondaries')],
        ));
    }
}
