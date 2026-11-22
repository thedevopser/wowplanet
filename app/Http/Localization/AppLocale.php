<?php

declare(strict_types=1);

namespace App\Http\Localization;

/**
 * Langue d'une page, lue dans son URL : l'anglais vit sous le préfixe `/en`, le français
 * garde les URL historiques du site, sans préfixe.
 */
enum AppLocale: string
{
    public const string ENGLISH_PREFIX = 'en';

    public const string COOKIE = 'wowplanet-locale';

    /** An api url carries no language: the page that calls it names its own in this header. */
    public const string HEADER = 'X-App-Locale';

    case French = 'fr';
    case English = 'en';

    public static function fromPath(string $path): self
    {
        $firstSegment = explode('/', ltrim($path, '/'), 2)[0];

        return $firstSegment === self::ENGLISH_PREFIX ? self::English : self::French;
    }

    public static function fromHeader(?string $header): self
    {
        return self::tryFrom($header ?? '') ?? self::French;
    }

    public static function current(): self
    {
        return self::tryFrom(app()->getLocale()) ?? self::French;
    }

    public function blizzardLocale(): string
    {
        return match ($this) {
            self::French => 'fr_FR',
            self::English => 'en_US',
        };
    }

    public function pick(string $french, ?string $english): string
    {
        return $this === self::English ? $english ?? $french : $french;
    }

    public function scopedCacheKey(string $key): string
    {
        return $key.':'.$this->value;
    }

    public function other(): self
    {
        return $this === self::French ? self::English : self::French;
    }
}
