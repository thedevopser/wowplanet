<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard;

/**
 * Lecture de l'en-tête `battlenet-namespace`, que Blizzard renvoie sur chaque réponse.
 *
 * Les namespaces versionnés ont la forme `static-12.1.0_68914-eu` : le segment du milieu
 * identifie le build WoW servi, et les données `static` ne changent qu'au patch.
 */
final class BlizzardNamespace
{
    public static function build(string $header): ?string
    {
        $segments = explode('-', $header);

        if (count($segments) !== 3) {
            return null;
        }

        return $segments[1];
    }
}
