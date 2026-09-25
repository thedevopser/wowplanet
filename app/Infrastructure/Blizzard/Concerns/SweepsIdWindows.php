<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Concerns;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;
use App\Infrastructure\Blizzard\SearchIdWindow;

/**
 * Balayage d'un endpoint de recherche par fenêtres d'identifiants.
 *
 * Les réponses de recherche sont volumineuses — toutes les locales sont servies quelle
 * que soit celle demandée. Chaque document est donc remis à l'appelant puis libéré
 * immédiatement, et c'est l'appelant qui choisit combien de fenêtres il demande d'un
 * coup, ce qui fixe le pic mémoire.
 */
trait SweepsIdWindows
{
    /**
     * @param  list<int>  $windows
     * @param  callable(ResponsePayload): void  $onDocument
     */
    private function sweepWindows(string $endpoint, array $windows, string $extraQuery, callable $onDocument): void
    {
        if ($windows === []) {
            return;
        }

        $endpoints = [];
        foreach ($windows as $window) {
            $endpoints[$window] = $endpoint.'?'.SearchIdWindow::query($window).$extraQuery;
        }

        $responses = $this->fetchBatchAsync($endpoints);

        // Chaque réponse est libérée dès qu'elle est réduite : garder les corps décodés
        // du lot entier multiplierait le pic mémoire par le nombre de fenêtres.
        foreach (array_keys($responses) as $key) {
            $decoded = $responses[$key];
            unset($responses[$key]);

            if ($decoded === null) {
                continue;
            }

            foreach ($decoded->objectList('results') as $result) {
                $onDocument($result->requiredObject('data'));
            }
        }
    }
}
