<?php

declare(strict_types=1);

namespace App\Http\Localization;

use Spatie\Sitemap\Tags\Url;

/**
 * Adresses absolues d'une page publique dans chaque langue, à partir de son chemin
 * français, le chemin de référence. Un chemin hors de la table des routes traduites
 * n'existe qu'en français et reste tel quel.
 */
final readonly class LocalizedUrls
{
    private const string DEFAULT_ALTERNATE = 'x-default';

    public function __construct(
        private LocalizedRouteTable $localizedRouteTable,
        private string $appUrl,
    ) {}

    public function absolute(string $frenchPath, AppLocale $locale): string
    {
        $path = $this->localizedRouteTable->alternate($frenchPath, $locale) ?? $frenchPath;

        return rtrim($this->appUrl, '/').($path === '/' ? '' : $path);
    }

    public function current(string $frenchPath): string
    {
        return $this->absolute($frenchPath, AppLocale::current());
    }

    /**
     * One sitemap entry per language, each announcing all the versions of the page.
     *
     * @return list<Url>
     */
    public function sitemapEntries(string $frenchPath): array
    {
        return array_map(
            fn (AppLocale $locale): Url => $this->withAlternates(Url::create($this->absolute($frenchPath, $locale)), $frenchPath),
            AppLocale::cases(),
        );
    }

    private function withAlternates(Url $url, string $frenchPath): Url
    {
        foreach (AppLocale::cases() as $locale) {
            $url->addAlternate($this->absolute($frenchPath, $locale), $locale->value);
        }

        return $url->addAlternate($this->absolute($frenchPath, AppLocale::French), self::DEFAULT_ALTERNATE);
    }
}
