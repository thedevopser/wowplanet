<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Talent;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * L'arbre de talents d'une spécialisation, `data/wow/talent-tree/{treeId}/playable-specialization/{specId}` :
 * arbre de classe, arbre de spécialisation et arbres héroïques.
 */
final readonly class TalentTreeResponse
{
    /**
     * @param  list<TalentNode>  $classNodes
     * @param  list<TalentNode>  $specNodes
     * @param  list<HeroTalentTree>  $heroTrees
     */
    public function __construct(
        public ?string $className,
        public ?string $specName,
        public ?int $specId,
        public array $classNodes,
        public array $specNodes,
        public array $heroTrees,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $playableSpecialization = $responsePayload->optionalObject('playable_specialization');

        return new self(
            className: $responsePayload->optionalObject('playable_class')?->optionalString('name'),
            specName: $playableSpecialization?->optionalString('name'),
            specId: $playableSpecialization?->optionalInt('id'),
            classNodes: array_map(TalentNode::fromPayload(...), $responsePayload->objectList('class_talent_nodes')),
            specNodes: array_map(TalentNode::fromPayload(...), $responsePayload->objectList('spec_talent_nodes')),
            heroTrees: array_map(HeroTalentTree::fromPayload(...), $responsePayload->objectList('hero_talent_trees')),
        );
    }

    /**
     * Les sorts de tous les rangs et de toutes les options, arbres héroïques compris, sans doublon.
     *
     * @return list<int>
     */
    public function spellIds(): array
    {
        $nodes = [...$this->classNodes, ...$this->specNodes];
        foreach ($this->heroTrees as $heroTree) {
            $nodes = [...$nodes, ...$heroTree->nodes];
        }

        $spellIds = array_merge(...array_map(static fn (TalentNode $talentNode): array => $talentNode->spellIds(), $nodes));

        return array_values(array_unique($spellIds));
    }
}
