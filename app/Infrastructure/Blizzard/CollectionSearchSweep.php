<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard;

use App\Infrastructure\Blizzard\Concerns\ImportsFromBlizzardApi;
use App\Infrastructure\Blizzard\Concerns\SweepsIdWindows;
use App\Infrastructure\Blizzard\Responses\DecorSearchDocument;
use App\Infrastructure\Blizzard\Responses\MountSearchDocument;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Balayage des montures et des décorations sur la grille de fenêtres de `SearchIdWindow`.
 *
 * Ces deux recherches portent le même contenu que le détail unitaire correspondant : quatre
 * fenêtres rendent les 1 669 montures et vingt-huit les 2 124 décorations, là où le détail
 * demanderait 3 793 appels. Les mascottes n'ont pas d'équivalent — `data/wow/search/pet`
 * est en 404 — et restent donc sur leur détail.
 *
 * Les documents sont petits et les fenêtres peu nombreuses : contrairement au balayage
 * d'items, le résultat tient en mémoire d'un bloc et n'a pas à être remis au fil de l'eau.
 */
final readonly class CollectionSearchSweep
{
    use ImportsFromBlizzardApi;
    use SweepsIdWindows;

    private const MOUNT_ENDPOINT = 'data/wow/search/mount';

    private const DECOR_ENDPOINT = 'data/wow/search/decor';

    public function __construct(BlizzardApiClient $blizzardApiClient)
    {
        $this->blizzardApiClient = $blizzardApiClient;
    }

    /**
     * @param  list<int>  $windows
     * @return array<int, MountSearchDocument>
     */
    public function sweepMounts(array $windows): array
    {
        $mounts = [];

        $this->sweepWindows(self::MOUNT_ENDPOINT, $windows, '', function (ResponsePayload $responsePayload) use (&$mounts): void {
            $mountSearchDocument = MountSearchDocument::fromPayload($responsePayload);
            $mounts[$mountSearchDocument->id] = $mountSearchDocument;
        });

        return $mounts;
    }

    /**
     * @param  list<int>  $windows
     * @return array<int, DecorSearchDocument>
     */
    public function sweepDecors(array $windows): array
    {
        $decors = [];

        $this->sweepWindows(self::DECOR_ENDPOINT, $windows, '', function (ResponsePayload $responsePayload) use (&$decors): void {
            $decorSearchDocument = DecorSearchDocument::fromPayload($responsePayload);
            $decors[$decorSearchDocument->id] = $decorSearchDocument;
        });

        return $decors;
    }
}
