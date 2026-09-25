<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Talent;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Un rang de nœud : un tooltip pour un nœud simple, une liste d'options pour un nœud à choix.
 */
final readonly class TalentRank
{
    /**
     * @param  list<TalentTooltip>  $choices
     */
    public function __construct(
        public ?TalentTooltip $tooltip,
        public array $choices,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $tooltip = $responsePayload->optionalObject('tooltip');

        return new self(
            tooltip: $tooltip instanceof ResponsePayload && ! $tooltip->isEmpty() ? TalentTooltip::fromPayload($tooltip) : null,
            choices: array_map(TalentTooltip::fromPayload(...), $responsePayload->objectList('choice_of_tooltips')),
        );
    }

    /**
     * @return list<int>
     */
    public function spellIds(): array
    {
        $tooltips = $this->tooltip instanceof TalentTooltip ? [$this->tooltip, ...$this->choices] : $this->choices;

        return array_values(array_filter(
            array_map(static fn (TalentTooltip $talentTooltip): ?int => $talentTooltip->spellId, $tooltips),
            is_int(...),
        ));
    }
}
