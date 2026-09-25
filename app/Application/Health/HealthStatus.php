<?php

declare(strict_types=1);

namespace App\Application\Health;

/**
 * Le verdict que la page de santé porte sur chaque mesure.
 *
 * C'est le serveur qui juge, et le panneau qui affiche : une anomalie ne doit jamais
 * être laissée à l'interprétation d'un chiffre. `Unavailable` n'est pas une gravité
 * de plus, c'est l'aveu que la mesure elle-même n'a pas pu être faite.
 */
enum HealthStatus: string
{
    case Ok = 'ok';
    case Warning = 'warning';
    case Critical = 'critical';
    case Unavailable = 'unavailable';
}
