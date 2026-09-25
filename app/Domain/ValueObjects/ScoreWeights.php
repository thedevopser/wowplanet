<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

/**
 * Pondération du score de complétion — source unique de vérité.
 *
 * Invariant : la somme des poids vaut 1. L'ordre des clés est l'ordre d'affichage.
 * Le PvP en est absent : chargé paresseusement, il noterait à zéro les profils PvE.
 */
final readonly class ScoreWeights
{
    /** Incrémenter à tout changement de pondération : invalide le cache du score de compte. */
    public const VERSION = 2;

    /** @var array<string, float> */
    public const WEIGHTS = [
        'quests' => 0.13,
        'achievements' => 0.20,
        'reputations' => 0.12,
        'raids' => 0.07,
        'mounts' => 0.13,
        'transmog' => 0.12,
        'pets' => 0.08,
        'decor' => 0.07,
        'professions' => 0.08,
    ];

    /** @var array<string, string> */
    public const LABELS = [
        'quests' => 'Quêtes',
        'achievements' => 'Hauts-faits',
        'reputations' => 'Réputations',
        'raids' => 'Raids',
        'mounts' => 'Montures',
        'transmog' => 'Garde-robe',
        'pets' => 'Mascottes',
        'decor' => 'Décorations',
        'professions' => 'Métiers',
    ];
}
