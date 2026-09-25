<?php

declare(strict_types=1);

namespace App\Application\Import;

/**
 * Pourquoi un import n'avance pas.
 *
 * Une attente silencieuse est indistinguable d'un blocage : tout ce qui met l'import
 * en pause plus d'un instant doit tomber dans l'une de ces quatre raisons.
 *
 * `Paused` est la seule dont la durée ne s'annonce pas : elle dure jusqu'à ce qu'un
 * humain décide, ce qui la range tout de même ici plutôt que dans un canal à part — le
 * panneau affiche déjà ce motif, et un import arrêté sans motif affiché serait le
 * défaut que cette énumération existe pour éviter.
 */
enum ImportWaitReason: string
{
    case HourlyBudget = 'hourly_budget';
    case RateLimitBackoff = 'rate_limit_backoff';
    case Batch = 'batch';
    case Paused = 'paused';
}
