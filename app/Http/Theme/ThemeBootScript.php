<?php

declare(strict_types=1);

namespace App\Http\Theme;

use UnexpectedValueException;

/**
 * Script qui pose la classe du thème avant le premier rendu, inliné en tête de `<head>`.
 *
 * Sa source vit dans `resources/js/themeBoot.js`, où Vitest l'exécute. La politique de
 * sécurité du contenu l'autorise par son empreinte, calculée sur le texte exact inliné.
 */
final class ThemeBootScript
{
    private const string SOURCE_PATH = 'js/themeBoot.js';

    public function source(): string
    {
        $path = resource_path(self::SOURCE_PATH);
        $content = is_readable($path) ? file_get_contents($path) : false;

        throw_if($content === false, UnexpectedValueException::class, sprintf('Theme boot script unreadable at %s.', $path));

        return trim($content);
    }

    public function cspSource(): string
    {
        return "'sha256-".base64_encode(hash('sha256', $this->source(), true))."'";
    }
}
