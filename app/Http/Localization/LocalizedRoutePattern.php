<?php

declare(strict_types=1);

namespace App\Http\Localization;

/**
 * Chemin d'une route dans une langue, à la syntaxe de Laravel (`/a/{b}/{c?}`), capable de
 * reconnaître un chemin concret et d'en reconstruire un à partir de ses paramètres.
 */
final readonly class LocalizedRoutePattern
{
    private const string PARAMETER = '/^\{(\w+)(\?)?\}$/';

    private string $regex;

    /** @var list<string> */
    public array $parameters;

    public function __construct(public string $path)
    {
        $parameters = [];
        $regex = '';

        foreach ($this->segments() as $segment) {
            if (preg_match(self::PARAMETER, $segment, $match) !== 1) {
                $regex .= '/'.preg_quote($segment, '#');

                continue;
            }

            $parameters[] = $match[1];
            $regex .= isset($match[2]) ? '(?:/([^/]+))?' : '/([^/]+)';
        }

        $this->parameters = $parameters;
        $this->regex = '#^'.($regex === '' ? '/' : $regex).'$#';
    }

    /**
     * @return list<string>|null the parameter values in order, empty for an absent optional one
     */
    public function match(string $concretePath): ?array
    {
        $normalized = '/'.trim($concretePath, '/');

        if (preg_match($this->regex, $normalized, $match) !== 1) {
            return null;
        }

        return array_map(
            fn (int $position): string => $match[$position + 1] ?? '',
            array_keys($this->parameters),
        );
    }

    /**
     * @param  list<string>  $values  parameter values in order, empty for an absent optional one
     */
    public function build(array $values): string
    {
        $built = '';
        $position = 0;

        foreach ($this->segments() as $segment) {
            if (preg_match(self::PARAMETER, $segment) !== 1) {
                $built .= '/'.$segment;

                continue;
            }

            $value = $values[$position++] ?? '';
            $built .= $value === '' ? '' : '/'.$value;
        }

        return $built === '' ? '/' : $built;
    }

    /**
     * @return list<string>
     */
    private function segments(): array
    {
        return array_values(array_filter(explode('/', $this->path), fn (string $segment): bool => $segment !== ''));
    }
}
