<?php

declare(strict_types=1);

namespace App\Application\Services;

use RuntimeException;

/**
 * Le calcul inter-personnages se présente dans la page Santé sous le BattleTag du compte : sans
 * lui, l'administrateur verrait un calcul sans savoir pour qui il tourne.
 */
final class MissingBattleTagException extends RuntimeException
{
    public static function forAccount(string $bnetUserId): self
    {
        return new self(sprintf('The Battle.net account %s has no BattleTag in session: its computation cannot be queued.', $bnetUserId));
    }
}
