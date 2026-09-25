<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses;

/**
 * Index de saison, servi à l'identique par `mythic-keystone/season/index` et
 * `pvp-season/index`.
 *
 * `current_season` est absent entre deux saisons : c'est un cas normal, pas une erreur.
 */
final readonly class SeasonIndexResponse
{
    public function __construct(public ?int $currentSeasonId) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        return new self($responsePayload->optionalObject('current_season')?->requiredInt('id'));
    }
}
