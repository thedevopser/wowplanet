<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard;

/**
 * Les espaces de media que `data/wow/search/media` sait filtrer.
 *
 * Chaque tag est son propre espace d'identifiants : un media d'item porte l'identifiant
 * de l'item, un media de haut fait celui du haut fait, et rien ne garantit qu'un
 * identifiant désigne la même chose d'un tag à l'autre.
 */
enum MediaSearchTag: string
{
    case Item = 'item';

    case Achievement = 'achievement';
}
