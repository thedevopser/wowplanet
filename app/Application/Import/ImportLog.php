<?php

declare(strict_types=1);

namespace App\Application\Import;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;

/**
 * Le journal d'un import : le worker y pousse une ligne par événement, le panneau les
 * relit par plage depuis la position qu'il annonce.
 *
 * Une liste Redis plutôt qu'une clé de cache, parce que le contrat de suivi est
 * « curseur plus delta » : le panneau ne doit jamais retélécharger le journal entier
 * pour découvrir trois lignes neuves, et `LRANGE` donne exactement cette plage.
 *
 * L'index Redis est celui des imports, séparé du cache : le panneau sait vider les
 * caches, et l'administrateur qui le fait pendant un import n'a aucune raison de perdre
 * le journal qu'il est en train de lire.
 */
final readonly class ImportLog
{
    /**
     * Un journal vit une journée, le temps de relire au lendemain l'import lancé la
     * veille au soir. Au-delà, c'est l'historique en base qui porte la mémoire, et le
     * rapport plutôt que le détail ligne à ligne.
     */
    public const TTL_S = 86400;

    private const KEY_PREFIX = 'import_log:';

    public function append(string $jobId, string $line): void
    {
        $key = self::KEY_PREFIX.$jobId;
        $connection = $this->connection();

        $length = (int) $connection->rpush($key, $line);

        // L'expiration se pose à la création, reconnaissable à la première ligne : la
        // repousser à chaque ajout ferait vivre un journal bavard bien au-delà du jour.
        if ($length === 1) {
            $connection->expire($key, self::TTL_S);
        }
    }

    public function since(string $jobId, int $cursor): ImportLogSlice
    {
        $key = self::KEY_PREFIX.$jobId;
        $connection = $this->connection();

        $length = (int) $connection->llen($key);

        /** @var list<string> $lines */
        $lines = $connection->lrange($key, max(0, $cursor), -1);

        return new ImportLogSlice($lines, $length);
    }

    /**
     * Ajoute une ligne horodatée. C'est l'horodatage qui distingue un import qui progresse
     * lentement d'un import qui ne bouge plus, et il appartient au journal plutôt qu'à
     * chacun de ceux qui y écrivent.
     */
    public function note(string $jobId, string $line): void
    {
        $this->append($jobId, sprintf('[%s] %s', now()->format('H:i:s'), $line));
    }

    public function forget(string $jobId): void
    {
        $this->connection()->del(self::KEY_PREFIX.$jobId);
    }

    private function connection(): Connection
    {
        return Redis::connection('imports');
    }
}
