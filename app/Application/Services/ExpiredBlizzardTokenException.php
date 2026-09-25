<?php

declare(strict_types=1);

namespace App\Application\Services;

use RuntimeException;

/**
 * Le calcul inter-personnages part avec le jeton du moment : une relance tardive depuis les jobs
 * échoués le rejoue avec un jeton expiré. Le traiter comme un endpoint vide enregistrerait
 * un compte sans aucune progression par-dessus le précédent.
 */
final class ExpiredBlizzardTokenException extends RuntimeException
{
    public static function forUrl(string $url): self
    {
        return new self(sprintf('The Blizzard token of this computation has expired: launch it again from the account hub instead of retrying it. (%s)', $url));
    }
}
