<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Profile;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Les mascottes collectées, `…/collections/pets`, lues par espèce : c'est l'espèce que le catalogue référence, pas l'exemplaire.
 */
final readonly class CharacterPetsResponse
{
    /**
     * @param  list<int>  $speciesIds
     */
    public function __construct(public array $speciesIds) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        return new self(array_map(
            static fn (ResponsePayload $responsePayload): int => $responsePayload->requiredObject('species')->requiredInt('id'),
            $responsePayload->objectList('pets'),
        ));
    }
}
