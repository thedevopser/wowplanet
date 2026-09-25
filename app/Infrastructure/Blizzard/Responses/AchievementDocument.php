<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses;

/**
 * Le détail d'un haut fait, réduit aux deux champs que la hiérarchie ne porte pas.
 *
 * Les points et la faction ne figurent ni dans l'index ni dans les listes de catégories :
 * ce sont les seules raisons d'appeler le détail d'un haut fait.
 */
final readonly class AchievementDocument
{
    /**
     * Les seules factions que l'API impose à un haut fait. Un type inconnu vaut aucune
     * faction : un haut fait sans camp est le cas courant, pas une anomalie.
     *
     * @var array<string, string>
     */
    private const FACTIONS = [
        'ALLIANCE' => 'Alliance',
        'HORDE' => 'Horde',
    ];

    public function __construct(
        public int $id,
        public int $points,
        public ?string $faction,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $factionType = $responsePayload
            ->optionalObject('requirements')
            ?->optionalObject('faction')
            ?->optionalString('type');

        return new self(
            $responsePayload->requiredInt('id'),
            $responsePayload->requiredInt('points'),
            $factionType === null ? null : (self::FACTIONS[mb_strtoupper(trim($factionType))] ?? null),
        );
    }
}
