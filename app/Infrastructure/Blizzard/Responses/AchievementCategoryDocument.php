<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses;

/**
 * Une catégorie de hauts faits, réduite à ce dont le rangement a besoin.
 *
 * Une catégorie porte ses propres hauts faits *et* des sous-catégories qui portent les
 * leurs : une racine n'est pas un simple conteneur, et « Quêtes » en compte trente-quatre
 * en propre. Le lien de parenté est remonté par `parent_category`, absent des racines.
 */
final readonly class AchievementCategoryDocument
{
    /**
     * @param  array<int, string>  $achievements  identifiant → nom du haut fait
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?int $parentId,
        public array $achievements,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $achievements = [];
        foreach ($responsePayload->objectList('achievements') as $achievement) {
            // Quelques noms traînent un CRLF côté Blizzard, ex. le haut fait 13503.
            $achievements[$achievement->requiredInt('id')] = trim($achievement->requiredString('name'));
        }

        return new self(
            $responsePayload->requiredInt('id'),
            trim($responsePayload->requiredString('name')),
            $responsePayload->optionalObject('parent_category')?->requiredInt('id'),
            $achievements,
        );
    }
}
