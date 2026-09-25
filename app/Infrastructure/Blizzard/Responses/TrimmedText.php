<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses;

/**
 * Première valeur textuelle utilisable parmi plusieurs candidates.
 *
 * Deux besoins se rejoignent ici. Les documents de recherche portent toutes les locales,
 * et le français manque parfois là où l'américain est rempli : on prend la première
 * servie. Et certains libellés de l'API traînent un CRLF — le haut fait 13503 en est le
 * cas connu — qu'il faut retirer avant de comparer ou de stocker.
 */
final class TrimmedText
{
    public static function firstNonEmpty(?string ...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $trimmed = trim($candidate ?? '');

            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }
}
