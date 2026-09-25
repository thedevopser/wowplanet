<?php

declare(strict_types=1);

namespace App\Application\Health;

/**
 * Le job échoué désigné n'existe pas, ou plus : un autre onglet l'a déjà relancé ou
 * supprimé entre l'affichage de la page et le clic.
 */
final class FailedJobNotFoundException extends \RuntimeException
{
    public static function forUuid(string $uuid): self
    {
        return new self(sprintf('Le job échoué %s est introuvable.', $uuid));
    }
}
