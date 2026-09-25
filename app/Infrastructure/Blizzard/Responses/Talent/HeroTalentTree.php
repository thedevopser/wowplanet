<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Talent;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Un arbre héroïque, et les spécialisations qui y ont accès.
 */
final readonly class HeroTalentTree
{
    /**
     * @param  list<int>  $specializationIds
     * @param  list<TalentNode>  $nodes
     */
    public function __construct(
        public ?int $id,
        public ?string $name,
        public array $specializationIds,
        public array $nodes,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $specializationIds = [];
        foreach ($responsePayload->objectList('playable_specializations') as $specialization) {
            $specializationId = $specialization->optionalInt('id');
            if ($specializationId !== null) {
                $specializationIds[] = $specializationId;
            }
        }

        return new self(
            id: $responsePayload->optionalInt('id'),
            name: $responsePayload->optionalString('name'),
            specializationIds: $specializationIds,
            nodes: array_map(TalentNode::fromPayload(...), $responsePayload->objectList('hero_talent_nodes')),
        );
    }
}
