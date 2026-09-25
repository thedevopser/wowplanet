<?php

declare(strict_types=1);

namespace App\Application\Health;

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Database\ConnectionResolverInterface;

/**
 * Vérifie que PostgreSQL et chaque index Redis répondent.
 *
 * Chaque sonde est isolée : un service tombé est signalé comme tel, et les autres
 * continuent d'être mesurés. La limite est connue — la session et le limiteur de débit
 * vivent sur Redis, donc un Redis entièrement arrêté empêche la page d'être servie ;
 * c'est alors `/up`, hors session, qui répond. Ces sondes attrapent tout le reste : un
 * index injoignable, une base qui ne répond plus sous une application encore debout.
 */
final readonly class ServiceProbes
{
    /** Les clés de `database.redis` qui règlent le client, et ne sont pas des connexions. */
    private const array REDIS_SETTINGS = ['client', 'options', 'clusters'];

    public function __construct(
        private ConnectionResolverInterface $connectionResolver,
        private RedisFactory $redisFactory,
    ) {}

    /**
     * @return list<array{service: string, status: string, issue: string|null, detail: string|null}>
     */
    public function probe(): array
    {
        return [
            $this->attempt('postgresql', 'PostgreSQL injoignable.', function (): void {
                $this->connectionResolver->connection()->select('select 1');
            }),
            ...array_map(
                fn (string $name): array => $this->attempt('redis:'.$name, sprintf('Redis (%s) injoignable.', $name), function () use ($name): void {
                    $this->redisFactory->connection($name)->command('ping');
                }),
                $this->redisConnections(),
            ),
        ];
    }

    /**
     * @return list<string>
     */
    private function redisConnections(): array
    {
        $redis = config('database.redis');
        throw_unless(is_array($redis), \UnexpectedValueException::class, 'The Redis configuration is not an array.');

        return array_values(array_filter(
            array_map(strval(...), array_keys($redis)),
            static fn (string $key): bool => ! in_array($key, self::REDIS_SETTINGS, true),
        ));
    }

    /**
     * @param  \Closure(): void  $check
     * @return array{service: string, status: string, issue: string|null, detail: string|null}
     */
    private function attempt(string $service, string $issue, \Closure $check): array
    {
        try {
            $check();
        } catch (\Throwable $throwable) {
            return ['service' => $service, 'status' => HealthStatus::Unavailable->value, 'issue' => $issue, 'detail' => $throwable->getMessage()];
        }

        return ['service' => $service, 'status' => HealthStatus::Ok->value, 'issue' => null, 'detail' => null];
    }
}
