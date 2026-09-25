<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Pvp;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Un palier PvP, `data/wow/pvp-tier/{id}`, réduit à son nom français. L'icône vient de son media.
 */
final readonly class PvpTierResponse
{
    public function __construct(public ?string $name) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        return new self($responsePayload->optionalString('name'));
    }
}
