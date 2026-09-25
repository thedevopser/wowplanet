<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Promises\LazyPromise;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Contrôle qu'une icône composée est bien servie par le CDN de rendu.
 *
 * Les icônes de montures sont les seules que l'application compose elle-même, à partir du
 * `SpellIconFileDataID` du socle : l'API n'en expose aucune. Or le CDN ne publie pas tous
 * les identifiants de fichier du client — 70 montures sur 1 659 répondent 403 aujourd'hui,
 * sans qu'aucune variante de taille, de région ou d'icône active ne réponde à leur place.
 *
 * Une URL qui échoue est **pire qu'une absence d'URL** : le front rend son gabarit de repli
 * sur un `null` et une image brisée sur un lien mort. Ce qui n'est pas servi est donc écarté
 * avant d'atteindre la base.
 *
 * Ce CDN n'est pas l'API Blizzard et ne consomme pas son quota. Le contrôle reste malgré
 * tout borné : les URL en double ne sont demandées qu'une fois, et l'appelant ne soumet que
 * celles qui changent — en régime stable, il n'en reste aucune.
 */
final readonly class RenderedIconProbe
{
    /** Au-delà, on ouvre plus de connexions que le gain de parallélisme n'en rapporte. */
    private const POOL_SIZE = 50;

    private const TIMEOUT_S = 10;

    /**
     * @param  list<string>  $urls
     * @return list<string> Celles que le CDN sert réellement, dans l'ordre reçu
     */
    public function servedUrls(array $urls): array
    {
        $distinct = array_values(array_unique($urls));
        if ($distinct === []) {
            return [];
        }

        $served = [];

        foreach (array_chunk($distinct, self::POOL_SIZE) as $chunk) {
            /** @var array<int, Response|\Throwable> $responses */
            $responses = Http::pool(static function (Pool $pool) use ($chunk): array {
                /** @var list<LazyPromise> $requests */
                $requests = [];

                foreach ($chunk as $url) {
                    $requests[] = $pool->timeout(self::TIMEOUT_S)->head($url);
                }

                return $requests;
            });

            foreach ($chunk as $position => $url) {
                $response = $responses[$position] ?? null;

                if ($response instanceof Response && $response->successful()) {
                    $served[] = $url;
                }
            }
        }

        return $served;
    }
}
