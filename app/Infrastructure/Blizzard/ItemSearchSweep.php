<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard;

use App\Infrastructure\Blizzard\Concerns\ImportsFromBlizzardApi;
use App\Infrastructure\Blizzard\Concerns\SweepsIdWindows;
use App\Infrastructure\Blizzard\Responses\ItemSearchDocument;
use App\Infrastructure\Blizzard\Responses\MediaSearchDocument;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Balayage du catalogue d'items sur la grille de fenêtres de `SearchIdWindow`.
 *
 * Un document de recherche d'item porte déjà tout ce qu'un import de garde-robe cherche —
 * nom, qualité, media, apparences —, là où le détail unitaire demandait un appel par
 * apparence. Les réponses pèsent environ 1,2 Mo par fenêtre : l'appelant choisit combien
 * de fenêtres il traite d'un coup, ce qui fixe le pic mémoire.
 */
final readonly class ItemSearchSweep
{
    use ImportsFromBlizzardApi;
    use SweepsIdWindows;

    public const WINDOW_SIZE = SearchIdWindow::SIZE;

    private const ITEM_ENDPOINT = 'data/wow/search/item';

    public function __construct(
        BlizzardApiClient $blizzardApiClient,
        private MediaSearchSweep $mediaSearchSweep,
    ) {
        $this->blizzardApiClient = $blizzardApiClient;
    }

    /**
     * Nombre de fenêtres couvrant le catalogue jusqu'à `$highestId` inclus.
     */
    public static function windowCountFor(int $highestId): int
    {
        return SearchIdWindow::countFor($highestId);
    }

    /**
     * Plus grand identifiant d'item du catalogue, ou `null` si l'API ne l'a pas rendu.
     *
     * C'est la borne du balayage : sans elle, les fenêtres les plus hautes seraient
     * choisies au jugé, et ce sont précisément celles qui portent le contenu récent.
     */
    public function highestItemId(): ?int
    {
        $decoded = $this->fetchWithRetry(self::ITEM_ENDPOINT.'?_pageSize=1&orderby=id:desc');
        if (! $decoded instanceof \App\Infrastructure\Blizzard\Responses\ResponsePayload) {
            return null;
        }

        $results = $decoded->objectList('results');

        return $results === [] ? null : $results[0]->requiredObject('data')->requiredInt('id');
    }

    /**
     * Balaie les fenêtres demandées et remet chaque document à l'appelant.
     *
     * @param  list<int>  $windows
     * @param  callable(ItemSearchDocument): void  $onDocument
     */
    public function sweepItems(array $windows, callable $onDocument): void
    {
        $this->sweepWindows(self::ITEM_ENDPOINT, $windows, '', function (ResponsePayload $responsePayload) use ($onDocument): void {
            $onDocument(ItemSearchDocument::fromPayload($responsePayload));
        });
    }

    /**
     * Icônes des items des fenêtres demandées, indexées par identifiant de media.
     *
     * @param  list<int>  $windows
     * @return array<int, MediaSearchDocument>
     */
    public function sweepItemMedia(array $windows): array
    {
        return $this->mediaSearchSweep->sweep($windows, MediaSearchTag::Item);
    }
}
