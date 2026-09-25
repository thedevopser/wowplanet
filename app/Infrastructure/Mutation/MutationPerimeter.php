<?php

declare(strict_types=1);

namespace App\Infrastructure\Mutation;

/**
 * Lecture de `mutation-perimeter.txt` : un chemin par ligne, commentaires en `#`.
 */
final class MutationPerimeter
{
    private const string COMMENT_MARKER = '#';

    /**
     * @return list<string>
     */
    public static function entries(string $contents): array
    {
        $entries = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $entry = trim($line);
            if ($entry === '') {
                continue;
            }

            if (str_starts_with($entry, self::COMMENT_MARKER)) {
                continue;
            }

            $entries[$entry] = true;
        }

        return array_keys($entries);
    }
}
