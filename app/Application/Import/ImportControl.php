<?php

declare(strict_types=1);

namespace App\Application\Import;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;

/**
 * Le drapeau par lequel l'administrateur reprend la main sur un import en cours.
 *
 * Un import ne s'interrompt pas de l'extérieur : le contrôleur pose un ordre, le job le
 * lit à la frontière de tranche suivante et décide. Ni `kill` de processus, ni exception
 * jetée dans un travail en train d'écrire — c'est ce qui garantit qu'une annulation
 * tombe entre deux écritures et jamais au milieu de l'une d'elles.
 *
 * Comme le pointeur et le journal, le drapeau vit sur l'index Redis des imports, hors de
 * portée du bouton « Vider les caches » : couper le quota pendant un import ne doit pas
 * dépendre de ce qu'un autre écran du panneau vient de faire.
 */
final readonly class ImportControl
{
    /** Un ordre ne survit pas à l'import qu'il vise : même durée de vie que son pointeur. */
    public const TTL_S = CurrentImport::TTL_S;

    private const KEY_PREFIX = 'import_control:';

    public function request(string $jobId, ImportSignal $importSignal, string $actor, int $requestedAt): void
    {
        $this->connection()->setex(
            self::KEY_PREFIX.$jobId,
            self::TTL_S,
            (string) json_encode((new ImportRequest($importSignal, $actor, $requestedAt))->toArray()),
        );
    }

    public function pending(string $jobId): ?ImportRequest
    {
        /** @var string|null $raw */
        $raw = $this->connection()->get(self::KEY_PREFIX.$jobId);

        if ($raw === null || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return null;
        }

        /** @var array{signal?: string, actor?: string, requested_at?: int} $decoded */
        return ImportRequest::fromArray($decoded);
    }

    public function clear(string $jobId): void
    {
        $this->connection()->del(self::KEY_PREFIX.$jobId);
    }

    private function connection(): Connection
    {
        return Redis::connection('imports');
    }
}
