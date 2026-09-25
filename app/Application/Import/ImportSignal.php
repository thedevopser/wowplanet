<?php

declare(strict_types=1);

namespace App\Application\Import;

/**
 * L'ordre donné à un import en cours par l'administrateur.
 *
 * Il n'y en a que deux, parce que ce sont les deux seuls qui s'écrivent : reprendre un
 * import consiste à effacer l'ordre posé, pas à en poser un troisième. Un import qui
 * porterait à la fois « en pause » et « reprends » serait un état impossible à trancher.
 */
enum ImportSignal: string
{
    case Pause = 'pause';
    case Cancel = 'cancel';
}
