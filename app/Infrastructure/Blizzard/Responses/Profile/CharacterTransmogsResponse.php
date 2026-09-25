<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Profile;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Les apparences débloquées, `…/collections/transmogs`, aplaties sur tous les emplacements.
 *
 * Ce sont des identifiants item-appearance, clé primaire de `wow_appearances`.
 */
final readonly class CharacterTransmogsResponse
{
    /**
     * @param  list<int>  $appearanceIds
     */
    public function __construct(public array $appearanceIds) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $appearanceIds = [];
        foreach ($responsePayload->objectList('slots') as $slot) {
            foreach ($slot->objectList('appearances') as $appearance) {
                $appearanceId = $appearance->lenientInt('id');
                if ($appearanceId !== null) {
                    $appearanceIds[] = $appearanceId;
                }
            }
        }

        return new self($appearanceIds);
    }
}
