<?php

declare(strict_types=1);

namespace App\Application\Import;

use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\Facades\Redis;

/**
 * Désigne l'import en cours, pour que le panneau retrouve son suivi sans l'avoir lancé.
 *
 * C'est le serveur qui fait autorité, et pas la mémoire du navigateur : un import
 * démarré depuis un autre poste, un second onglet ou la ligne de commande doit être
 * raccroché de la même façon. Un rafraîchissement de page redemande simplement ce
 * pointeur, puis reprend le journal à sa position.
 *
 * Comme le journal, le pointeur vit sur l'index Redis des imports, hors de portée du
 * bouton « Vider les caches ».
 */
final readonly class CurrentImport
{
    /**
     * Le pointeur expire de lui-même : un worker tué en cours d'import ne laisse pas le
     * panneau suivre indéfiniment un job qui ne rendra plus rien. La durée couvre le
     * `retryUntil` du job, au-delà duquel plus aucune passe ne sera tentée.
     */
    public const TTL_S = 86400;

    private const KEY = 'import_current';

    public function mark(string $jobId, int $startedAt): void
    {
        $this->connection()->setex(self::KEY, self::TTL_S, $this->encode($jobId, $startedAt));
    }

    /**
     * Pose le pointeur seulement s'il est libre, en un aller-retour : c'est le verrou
     * « un import à la fois ». Deux onglets qui lancent en même temps ne peuvent pas
     * tous les deux l'obtenir, ce qu'un test suivi d'une écriture ne garantirait pas.
     */
    public function tryMark(string $jobId, int $startedAt): bool
    {
        return (bool) $this->connection()->set(
            self::KEY,
            $this->encode($jobId, $startedAt),
            'EX',
            self::TTL_S,
            'NX',
        );
    }

    private function encode(string $jobId, int $startedAt): string
    {
        return (string) json_encode(['job_id' => $jobId, 'started_at' => $startedAt]);
    }

    public function jobId(): ?string
    {
        $pointer = $this->pointer();

        return $pointer === null ? null : $pointer['job_id'];
    }

    /**
     * Depuis quand l'import en cours tourne, ce que le refus d'un second lancement doit
     * pouvoir dire.
     */
    public function startedAt(): ?int
    {
        $pointer = $this->pointer();

        return $pointer === null ? null : $pointer['started_at'];
    }

    /**
     * Un pointeur illisible se lit comme « rien ne tourne » : le panneau doit rester
     * utilisable, et la pire réponse serait de tomber sur une clé abîmée.
     *
     * @return array{job_id: string, started_at: int}|null
     */
    private function pointer(): ?array
    {
        /** @var string|null $raw */
        $raw = $this->connection()->get(self::KEY);

        if ($raw === null || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded) || ! is_string($decoded['job_id'] ?? null) || ! is_int($decoded['started_at'] ?? null)) {
            return null;
        }

        return ['job_id' => $decoded['job_id'], 'started_at' => $decoded['started_at']];
    }

    public function clear(): void
    {
        $this->connection()->del(self::KEY);
    }

    /**
     * Relâche le verrou, mais seulement si c'est bien cet import qui le détient.
     *
     * Un job qui rend la main sans vérifier effacerait le pointeur d'un import lancé
     * depuis — cas réel dès qu'une commande simple tourne hors du panneau, sans avoir
     * jamais pris le verrou.
     */
    public function release(string $jobId): void
    {
        if ($this->jobId() === $jobId) {
            $this->clear();
        }
    }

    /**
     * La pose conditionnelle du verrou passe par la surcharge phpredis de `set`, celle
     * qui accepte les options `EX` et `NX` : c'est elle qui rend l'opération atomique.
     */
    private function connection(): PhpRedisConnection
    {
        $connection = Redis::connection('imports');

        throw_unless($connection instanceof PhpRedisConnection, \RuntimeException::class, 'The import lock needs the phpredis client.');

        return $connection;
    }
}
