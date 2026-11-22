<?php

declare(strict_types=1);

namespace App\Http\Localization;

use App\Http\Localization\Exceptions\InvalidLocalizedRouteTable;
use App\Http\Localization\Exceptions\UnknownLocalizedRoute;

/**
 * Table unique des chemins traduits des pages publiques, partagée avec le front
 * (`resources/js/i18n/localizedRoutes.json`) : les routes, le sélecteur de langue,
 * les `hreflang` et les sitemaps en dérivent tous.
 */
final readonly class LocalizedRouteTable
{
    /** @var array<string, array{fr: LocalizedRoutePattern, en: LocalizedRoutePattern}> */
    private array $routes;

    /**
     * @param  array<string, array<string, string>>  $routes
     */
    public function __construct(array $routes)
    {
        $patterns = [];

        foreach ($routes as $name => $paths) {
            $patterns[$name] = $this->patternsOf($name, $paths);
        }

        $this->routes = $patterns;
    }

    public static function fromFile(string $file): self
    {
        $decoded = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw InvalidLocalizedRouteTable::because('the file must hold an object of routes');
        }

        $routes = [];

        foreach ($decoded as $name => $paths) {
            $routes[(string) $name] = is_array($paths) ? array_filter($paths, is_string(...)) : [];
        }

        /** @var array<string, array<string, string>> $routes */
        return new self($routes);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->routes);
    }

    public function path(string $name, AppLocale $locale): string
    {
        if (! isset($this->routes[$name])) {
            throw UnknownLocalizedRoute::named($name);
        }

        return $this->routes[$name][$locale->value]->path;
    }

    /**
     * The same page in the target locale, or null when the path is not a localized page.
     */
    public function alternate(string $concretePath, AppLocale $target): ?string
    {
        $source = AppLocale::fromPath($concretePath);

        foreach ($this->routes as $patterns) {
            $values = $patterns[$source->value]->match($concretePath);

            if ($values !== null) {
                return $patterns[$target->value]->build($values);
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $paths
     * @return array{fr: LocalizedRoutePattern, en: LocalizedRoutePattern}
     */
    private function patternsOf(string $name, array $paths): array
    {
        if (! isset($paths['fr'], $paths['en'])) {
            throw InvalidLocalizedRouteTable::because(sprintf('route "%s" needs a "fr" and an "en" path', $name));
        }

        $french = new LocalizedRoutePattern($paths['fr']);
        $english = new LocalizedRoutePattern($paths['en']);

        if (AppLocale::fromPath($english->path) !== AppLocale::English || AppLocale::fromPath($french->path) !== AppLocale::French) {
            throw InvalidLocalizedRouteTable::because(sprintf('route "%s" must keep english paths, and only them, under /en', $name));
        }

        if ($french->parameters !== $english->parameters) {
            throw InvalidLocalizedRouteTable::because(sprintf('route "%s" must declare the same parameters in both locales', $name));
        }

        return ['fr' => $french, 'en' => $english];
    }
}
