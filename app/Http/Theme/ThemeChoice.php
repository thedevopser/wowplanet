<?php

declare(strict_types=1);

namespace App\Http\Theme;

use Illuminate\Http\Request;

/**
 * Thème choisi par le visiteur, relu dans le cookie que le navigateur écrit en clair.
 *
 * Le serveur ne sait résoudre que les choix explicites : « system » dépend de la
 * préférence du système, que seul le script de démarrage du thème connaît.
 */
enum ThemeChoice: string
{
    public const string COOKIE = 'wowplanet-theme';

    case System = 'system';
    case Dark = 'dark';
    case Light = 'light';

    public static function fromRequest(Request $request): self
    {
        $value = $request->cookie(self::COOKIE);

        if (! is_string($value)) {
            return self::System;
        }

        return self::tryFrom($value) ?? self::System;
    }

    public function rendersDark(): bool
    {
        return $this === self::Dark;
    }
}
