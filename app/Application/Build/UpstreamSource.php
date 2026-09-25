<?php

declare(strict_types=1);

namespace App\Application\Build;

/**
 * Les deux amonts dont WowPlanet dépend, et qui ne servent pas le même numéro.
 *
 * Blizzard publie `12.1.0_68914` dans son en-tête `battlenet-namespace`, wago publie
 * `12.1.0.69875` — séparateurs et valeurs différents, et wago prend de l'avance.
 * Les rapprocher ferait apparaître un écart permanent qui n'existe pas : chaque
 * famille d'entités se compare au sien, et à lui seul.
 */
enum UpstreamSource: string
{
    case Blizzard = 'blizzard';
    case Wago = 'wago';

    public function label(): string
    {
        return match ($this) {
            self::Blizzard => 'API Blizzard',
            self::Wago => 'wago.tools',
        };
    }

    public function cacheKey(): string
    {
        return 'upstream_build:'.$this->value;
    }
}
