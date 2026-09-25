<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Profile;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Les réputations entamées d'un personnage, `…/reputations`. Les factions jamais rencontrées
 * n'y figurent pas : c'est le référentiel qui les ajoute.
 */
final readonly class CharacterReputationsResponse
{
    /**
     * @param  list<ReputationStanding>  $standings
     */
    public function __construct(public array $standings) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        return new self(array_map(ReputationStanding::fromPayload(...), $responsePayload->objectList('reputations')));
    }
}
