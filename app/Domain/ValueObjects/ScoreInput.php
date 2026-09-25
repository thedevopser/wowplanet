<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

/**
 * Entrée du calcul, commune au profil d'un personnage et au profil virtuel d'un compte.
 *
 * Le domaine ne décrit que ce que la formule lit : chaque forme est un sous-ensemble de ce que
 * rendent les agrégateurs, et une clé absente vaut zéro.
 *
 * @phpstan-type ScoreCounts array{completed?: int, total?: int}
 * @phpstan-type ScoreCollection array{quests?: ScoreCounts, achievements?: ScoreCounts, reputations?: ScoreCounts}
 * @phpstan-type ScoreItem array{is_completed?: bool}
 * @phpstan-type ScoreProfession array{expansions?: array<int, array{completed?: int, total?: int, skill_points?: int, max_skill_points?: int}>}
 * @phpstan-type ScoreRaidMode array{difficulty_type?: string, total_count?: int, encounters?: list<array{id?: int}>}
 * @phpstan-type ScoreRaid array{modes?: list<ScoreRaidMode>}
 */
final readonly class ScoreInput
{
    /**
     * @param  array<int, ScoreCollection>  $collections  Par extension : quests / achievements / reputations
     * @param  list<ScoreItem>  $mounts  Items portant `is_completed`
     * @param  list<ScoreItem>  $pets
     * @param  list<ScoreItem>  $decor
     * @param  list<ScoreProfession>  $professions
     * @param  list<array{slot?: string, total?: int, completed?: int}>  $appearances
     * @param  list<ScoreRaid>|null  $raids  Tier courant, cf. RaidProgressAggregator
     * @param  array{completed: int, total: int}|null  $bestProfessionStats  Meilleur ratio métier du compte
     */
    public function __construct(
        public array $collections = [],
        public array $mounts = [],
        public array $pets = [],
        public array $decor = [],
        public array $professions = [],
        public array $appearances = [],
        public ?array $raids = null,
        public ?array $bestProfessionStats = null,
    ) {}
}
