<?php

declare(strict_types=1);

namespace App\Http\Localization;

use Illuminate\Http\Request;

/**
 * La langue à proposer à un visiteur dont le navigateur ne lit pas celle de la page.
 *
 * Toute langue autre que le français se voit proposer l'anglais. Rien n'est proposé à un
 * client qui n'annonce aucune langue, comme les robots d'indexation, ni à un visiteur qui
 * a déjà choisi : le cookie de choix, écrit en clair par le navigateur, fait foi.
 */
final class LocaleSuggestion
{
    private const string FRENCH_LANGUAGE = 'fr';

    public static function for(Request $request, AppLocale $page): ?AppLocale
    {
        $choice = $request->cookie(AppLocale::COOKIE);

        if (is_string($choice) && AppLocale::tryFrom($choice) instanceof AppLocale) {
            return null;
        }

        $preferredLanguage = $request->getLanguages()[0] ?? null;

        if ($preferredLanguage === null) {
            return null;
        }

        $browserLocale = str_starts_with(strtolower($preferredLanguage), self::FRENCH_LANGUAGE) ? AppLocale::French : AppLocale::English;

        return $browserLocale === $page ? null : $browserLocale;
    }
}
