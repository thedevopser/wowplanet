<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Profile;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Le media d'un personnage : l'avatar de la fiche est le portrait en médaillon, second asset servi,
 * avec repli sur le premier.
 */
final readonly class CharacterMediaResponse
{
    private const int INSET_ASSET_INDEX = 1;

    public function __construct(public ?string $avatarUrl) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $values = array_map(
            static fn (ResponsePayload $responsePayload): ?string => $responsePayload->optionalString('value'),
            $responsePayload->objectList('assets'),
        );

        return new self($values[self::INSET_ASSET_INDEX] ?? $values[0] ?? null);
    }
}
