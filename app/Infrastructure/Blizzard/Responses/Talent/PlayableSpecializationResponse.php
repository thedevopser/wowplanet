<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Talent;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Une spécialisation jouable, `data/wow/playable-specialization/{id}`, réduite à l'identifiant de
 * son arbre de talents. L'API ne le donne qu'à travers le lien vers l'arbre.
 */
final readonly class PlayableSpecializationResponse
{
    private const string TALENT_TREE_LINK = '/talent-tree\/(\d+)/';

    public function __construct(public ?int $talentTreeId) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $href = $responsePayload->optionalObject('spec_talent_tree')?->optionalObject('key')?->optionalString('href') ?? '';

        return new self(preg_match(self::TALENT_TREE_LINK, $href, $matches) === 1 ? (int) $matches[1] : null);
    }
}
