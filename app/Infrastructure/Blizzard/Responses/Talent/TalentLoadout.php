<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Talent;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Un loadout de talents : les nœuds retenus dans chacun des trois arbres, et l'arbre héroïque choisi.
 */
final readonly class TalentLoadout
{
    /**
     * @param  list<SelectedTalent>  $classTalents
     * @param  list<SelectedTalent>  $specTalents
     * @param  list<SelectedTalent>  $heroTalents
     */
    public function __construct(
        public array $classTalents,
        public array $specTalents,
        public array $heroTalents,
        public ?int $heroTreeId,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        return new self(
            classTalents: array_map(SelectedTalent::fromPayload(...), $responsePayload->objectList('selected_class_talents')),
            specTalents: array_map(SelectedTalent::fromPayload(...), $responsePayload->objectList('selected_spec_talents')),
            heroTalents: array_map(SelectedTalent::fromPayload(...), $responsePayload->objectList('selected_hero_talents')),
            heroTreeId: $responsePayload->optionalObject('selected_hero_talent_tree')?->optionalInt('id'),
        );
    }
}
