<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Profile;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Les montures collectées, `…/collections/mounts`.
 */
final readonly class CharacterMountsResponse
{
    /**
     * @param  list<int>  $mountIds
     */
    public function __construct(public array $mountIds) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        return new self(array_map(
            static fn (ResponsePayload $responsePayload): int => $responsePayload->requiredObject('mount')->requiredInt('id'),
            $responsePayload->objectList('mounts'),
        ));
    }
}
