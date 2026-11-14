<?php

declare(strict_types=1);

namespace App\Application\Services\Todo;

/**
 * Les dimensions de la page « À faire », dans les termes que le front traduit.
 */
enum TodoDimension: string
{
    case Mounts = 'mounts';
    case Pets = 'pets';
    case Decor = 'decor';
    case Achievements = 'achievements';
}
