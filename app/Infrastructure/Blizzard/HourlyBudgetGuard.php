<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard;

use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\Facades\Redis;

/**
 * Fenêtre glissante d'une heure sur le quota d'appels Blizzard (36 000 req/h).
 * Complète RateLimitingMiddleware (limite par seconde) pour les imports massifs :
 * l'importer interroge secondsUntilAvailable() avant chaque lot et attend si besoin.
 */
final class HourlyBudgetGuard
{
    /** Le quota horaire que Blizzard accorde au client, et qu'il ne faut jamais toucher. */
    public const PUBLISHED_HOURLY_QUOTA = 36000;

    /** Marge de sécurité sous le quota réel. */
    public const HOURLY_LIMIT = 34000;

    private const KEY_PREFIX = 'blizzard_budget:';

    /**
     * Total monotone, jamais expiré : les appels d'une étape d'import se mesurent par
     * différence entre son début et sa fin, ce que la fenêtre glissante ne permet pas
     * dès qu'une minute en sort pendant l'étape.
     */
    private const TOTAL_KEY = self::KEY_PREFIX.'total';

    private const WINDOW_S = 3600;

    /** Une minute de battement au-delà de la fenêtre, pour ne pas perdre le bucket en cours de lecture. */
    private const TTL_S = self::WINDOW_S + 60;

    private const WINDOW_MINUTES = 60;

    /**
     * L'expiration n'est posée qu'à la création de la clé — reconnaissable au total
     * rendu, égal à ce qu'on vient d'ajouter — pour tenir un seul aller-retour par appel.
     * Le pipeline préserve cet acquis en portant les deux incréments dans le même.
     */
    public function consume(int $count): void
    {
        $key = self::KEY_PREFIX.$this->currentMinute();
        $connection = $this->connection();

        /** @var array<int, int|string> $counts */
        $counts = $connection->pipeline(static function (\Redis $redis) use ($key, $count): void {
            $redis->incrBy($key, $count);
            $redis->incrBy(self::TOTAL_KEY, $count);
        });

        if ((int) ($counts[0] ?? 0) === $count) {
            $connection->expire($key, self::TTL_S);
        }
    }

    /**
     * Appels consommés depuis la mise en service du compteur, toutes fenêtres confondues.
     */
    public function totalConsumed(): int
    {
        /** @var string|null $total */
        $total = $this->connection()->get(self::TOTAL_KEY);

        return (int) $total;
    }

    /**
     * Secondes à attendre avant de pouvoir consommer $count requêtes sans dépasser
     * le plafond ($ceiling), ou HOURLY_LIMIT si non fourni. Les imports passent un
     * plafond réservé (< HOURLY_LIMIT) pour laisser de la marge au trafic du site.
     */
    public function secondsUntilAvailable(int $count, ?int $ceiling = null): int
    {
        $limit = $ceiling ?? self::HOURLY_LIMIT;
        $buckets = $this->buckets();

        if ($buckets === [] || array_sum($buckets) + $count <= $limit) {
            return 0;
        }

        $oldestMinute = min(array_keys($buckets));

        return max(1, ($oldestMinute * 60 + self::TTL_S) - now()->getTimestamp());
    }

    public function usedInWindow(): int
    {
        return array_sum($this->buckets());
    }

    /**
     * @return array<int, int> Minutes non vides de la fenêtre, epoch/60 => nb requêtes
     */
    private function buckets(): array
    {
        $currentMinute = $this->currentMinute();
        $minutes = range($currentMinute - (self::WINDOW_MINUTES - 1), $currentMinute);

        /** @var list<string|null> $values */
        $values = $this->connection()->mget(
            array_map(static fn (int $minute): string => self::KEY_PREFIX.$minute, $minutes),
        );

        $buckets = [];
        foreach ($minutes as $index => $minute) {
            $value = (int) ($values[$index] ?? 0);
            if ($value !== 0) {
                $buckets[$minute] = $value;
            }
        }

        return $buckets;
    }

    /**
     * Le compteur suppose phpredis, seul client installé sur le projet : c'est lui qui
     * porte le pipeline, donc l'aller-retour unique par appel.
     */
    private function connection(): PhpRedisConnection
    {
        $connection = Redis::connection('budget');

        throw_unless($connection instanceof PhpRedisConnection, \RuntimeException::class, 'The Blizzard budget counter needs the phpredis client.');

        return $connection;
    }

    private function currentMinute(): int
    {
        return intdiv(now()->getTimestamp(), 60);
    }
}
