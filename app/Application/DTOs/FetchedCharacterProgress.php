<?php

declare(strict_types=1);

namespace App\Application\DTOs;

use App\Infrastructure\Blizzard\Responses\Profile\CharacterProfessionsResponse;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterReputationsResponse;

/**
 * Ce que le calcul des données croisées tire d'un personnage : quêtes et hauts faits accomplis,
 * réputations et métiers. Un endpoint en échec donne une partie vide, jamais une absence.
 */
final readonly class FetchedCharacterProgress
{
    /**
     * @param  list<int>  $questIds
     * @param  list<int>  $achievementIds
     */
    public function __construct(
        public array $questIds,
        public array $achievementIds,
        public CharacterReputationsResponse $reputations,
        public CharacterProfessionsResponse $professions,
    ) {}
}
