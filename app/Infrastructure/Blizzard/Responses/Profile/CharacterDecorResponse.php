<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Profile;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Les décorations de logis collectées, `…/collections/decor`.
 */
final readonly class CharacterDecorResponse
{
    /**
     * @param  list<int>  $decorIds
     */
    public function __construct(public array $decorIds) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        return new self(array_map(
            static fn (ResponsePayload $responsePayload): int => $responsePayload->requiredObject('decor')->requiredInt('id'),
            $responsePayload->objectList('decor_collected'),
        ));
    }
}
