<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard;

use InvalidArgumentException;

/**
 * La grille de fenêtres d'identifiants des recherches Blizzard.
 *
 * Une fenêtre est un index `w` désignant l'intervalle `[w × 1000, w × 1000 + 999]`. La
 * grille est fixe, et c'est ce qui rend un balayage reprenable : une fenêtre se désigne
 * par un entier, indépendamment de ce qu'elle contient. Mille identifiants tenant
 * toujours sous la page maximale de l'API, une fenêtre ne pagine jamais.
 *
 * Elle est partagée par tous les balayages — items, media — pour que l'offset de reprise
 * d'une passe vaille aussi pour la suivante.
 */
final class SearchIdWindow
{
    public const SIZE = 1000;

    /**
     * Nombre de fenêtres couvrant les identifiants jusqu'à `$highestId` inclus.
     */
    public static function countFor(int $highestId): int
    {
        if ($highestId < 0) {
            throw new InvalidArgumentException(sprintf('A highest id cannot be negative, got %d.', $highestId));
        }

        return intdiv($highestId, self::SIZE) + 1;
    }

    /**
     * Index de la fenêtre dont l'intervalle contient `$id`.
     */
    public static function holding(int $id): int
    {
        if ($id < 0) {
            throw new InvalidArgumentException(sprintf('An id cannot be negative, got %d.', $id));
        }

        return intdiv($id, self::SIZE);
    }

    /**
     * Paramètres de recherche d'une fenêtre, à concaténer à un endpoint.
     */
    public static function query(int $window): string
    {
        if ($window < 0) {
            throw new InvalidArgumentException(sprintf('A window index cannot be negative, got %d.', $window));
        }

        return sprintf(
            '_pageSize=%d&orderby=id&id=[%d,%d]',
            self::SIZE,
            $window * self::SIZE,
            $window * self::SIZE + self::SIZE - 1,
        );
    }
}
