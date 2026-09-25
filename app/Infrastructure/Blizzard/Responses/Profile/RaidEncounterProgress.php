<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Profile;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Un boss vaincu dans une difficulté, et la date de son dernier kill en millisecondes.
 */
final readonly class RaidEncounterProgress
{
    public function __construct(
        public ?int $id,
        public ?string $name,
        public ?int $lastKillTimestamp,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $encounter = $responsePayload->optionalObject('encounter');

        return new self(
            id: $encounter?->optionalInt('id'),
            name: $encounter?->optionalString('name'),
            lastKillTimestamp: $responsePayload->lenientInt('last_kill_timestamp'),
        );
    }
}
