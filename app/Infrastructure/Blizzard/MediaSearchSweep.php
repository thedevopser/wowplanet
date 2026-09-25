<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard;

use App\Infrastructure\Blizzard\Concerns\ImportsFromBlizzardApi;
use App\Infrastructure\Blizzard\Concerns\SweepsIdWindows;
use App\Infrastructure\Blizzard\Responses\MediaSearchDocument;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Balayage des media par fenêtres d'identifiants.
 *
 * Une fenêtre rend l'icône de tout ce que l'espace du tag contient dans son intervalle,
 * là où le media unitaire demanderait un appel par entrée. C'est la seule façon tenable
 * de récupérer des dizaines de milliers d'icônes.
 */
final readonly class MediaSearchSweep
{
    use ImportsFromBlizzardApi;
    use SweepsIdWindows;

    private const ENDPOINT = 'data/wow/search/media';

    public function __construct(BlizzardApiClient $blizzardApiClient)
    {
        $this->blizzardApiClient = $blizzardApiClient;
    }

    /**
     * Icônes des fenêtres demandées, indexées par identifiant de media.
     *
     * @param  list<int>  $windows
     * @return array<int, MediaSearchDocument>
     */
    public function sweep(array $windows, MediaSearchTag $mediaSearchTag): array
    {
        $media = [];

        $this->sweepWindows(self::ENDPOINT, $windows, '&tags='.$mediaSearchTag->value, function (ResponsePayload $responsePayload) use (&$media): void {
            $mediaSearchDocument = MediaSearchDocument::fromPayload($responsePayload);
            $media[$mediaSearchDocument->id] = $mediaSearchDocument;
        });

        return $media;
    }
}
