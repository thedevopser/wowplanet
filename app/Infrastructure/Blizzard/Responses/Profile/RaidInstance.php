<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Profile;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Un raid dans la progression d'un personnage, et un mode par difficulté tentée.
 */
final readonly class RaidInstance
{
    /**
     * @param  list<RaidMode>  $modes
     */
    public function __construct(
        public ?int $id,
        public ?string $name,
        public array $modes,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $instance = $responsePayload->optionalObject('instance');

        return new self(
            id: $instance?->optionalInt('id'),
            name: $instance?->optionalString('name'),
            modes: array_map(RaidMode::fromPayload(...), $responsePayload->objectList('modes')),
        );
    }
}
