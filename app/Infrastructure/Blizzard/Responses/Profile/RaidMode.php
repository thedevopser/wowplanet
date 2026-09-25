<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Profile;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Une difficulté d'un raid : le décompte des boss vaincus et le détail de chacun.
 */
final readonly class RaidMode
{
    /**
     * @param  list<RaidEncounterProgress>  $encounters
     */
    public function __construct(
        public ?string $difficultyType,
        public ?int $completedCount,
        public ?int $totalCount,
        public array $encounters,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $progress = $responsePayload->optionalObject('progress');

        return new self(
            difficultyType: $responsePayload->optionalObject('difficulty')?->optionalString('type'),
            completedCount: $progress?->optionalInt('completed_count'),
            totalCount: $progress?->optionalInt('total_count'),
            encounters: array_map(RaidEncounterProgress::fromPayload(...), $progress?->objectList('encounters') ?? []),
        );
    }
}
