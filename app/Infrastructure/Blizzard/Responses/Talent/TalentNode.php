<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Talent;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Un nœud d'arbre de talents : sa position, son type, ses rangs et ses dépendances.
 */
final readonly class TalentNode
{
    /**
     * @param  list<TalentRank>  $ranks
     * @param  list<int>  $lockedBy
     * @param  list<int>  $unlocks
     */
    public function __construct(
        public ?int $id,
        public ?string $type,
        public array $ranks,
        public array $lockedBy,
        public array $unlocks,
        public ?int $displayCol,
        public ?int $displayRow,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        return new self(
            id: $responsePayload->optionalInt('id'),
            type: $responsePayload->optionalObject('node_type')?->optionalString('type'),
            ranks: array_map(TalentRank::fromPayload(...), $responsePayload->objectList('ranks')),
            lockedBy: $responsePayload->intList('locked_by'),
            unlocks: $responsePayload->intList('unlocks'),
            displayCol: $responsePayload->optionalInt('display_col'),
            displayRow: $responsePayload->optionalInt('display_row'),
        );
    }

    /**
     * @return list<int>
     */
    public function spellIds(): array
    {
        return array_merge(...array_map(static fn (TalentRank $talentRank): array => $talentRank->spellIds(), $this->ranks));
    }
}
