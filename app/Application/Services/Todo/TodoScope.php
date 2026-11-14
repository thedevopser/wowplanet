<?php

declare(strict_types=1);

namespace App\Application\Services\Todo;

/**
 * Qui peut faire un élément de la page « À faire ».
 */
enum TodoScope: string
{
    /** Une collection ou un haut-fait commun au compte : n'importe quel personnage. */
    case Account = 'account';
}
