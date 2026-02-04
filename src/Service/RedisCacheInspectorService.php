<?php

declare(strict_types=1);

namespace App\Service;

use Predis\Client;
use Psr\Log\LoggerInterface;

final readonly class RedisCacheInspectorService
{
    private Client $redisClient;

    public function __construct(
        private LoggerInterface $logger,
        private string $redisUrl
    ) {
        $this->redisClient = new Client($this->redisUrl);
    }

    /**
     * @return list<array{key: string, ttl: int, type: string}>
     */
    public function listKeys(string $pattern = '*'): array
    {
        $this->logger->debug('Scanning Redis keys', ['pattern' => $pattern]);

        $keys = [];
        $cursor = '0';

        do {
            /** @var array{0: string, 1: list<mixed>} $result */
            $result = $this->redisClient->scan($cursor, ['MATCH' => $pattern, 'COUNT' => 100]);
            $cursor = $result[0];

            foreach ($result[1] as $key) {
                if (!is_string($key)) {
                    continue;
                }

                $keys[] = [
                    'key' => $key,
                    'ttl' => $this->getKeyTtl($key),
                    'type' => $this->getKeyType($key),
                ];
            }
        } while ($cursor !== '0');

        usort($keys, static fn (array $a, array $b): int => $a['key'] <=> $b['key']);

        $this->logger->info('Redis keys listed', ['count' => count($keys), 'pattern' => $pattern]);

        return $keys;
    }

    public function getKeyValue(string $key): ?string
    {
        $type = $this->getKeyType($key);

        if ($type !== 'string') {
            $this->logger->warning('Cannot read non-string key', ['key' => $key, 'type' => $type]);

            return null;
        }

        /** @var string|null $value */
        $value = $this->redisClient->get($key);

        return $value;
    }

    public function getKeyTtl(string $key): int
    {
        /** @var int $ttl */
        $ttl = $this->redisClient->ttl($key);

        return $ttl;
    }

    public function getKeyType(string $key): string
    {
        /** @var string $type */
        $type = $this->redisClient->type($key);

        return $type;
    }

    public function deleteKey(string $key): bool
    {
        $this->logger->info('Deleting Redis key', ['key' => $key]);

        /** @var int $result */
        $result = $this->redisClient->del([$key]);

        return $result > 0;
    }

    public function flushAll(): void
    {
        $this->logger->warning('Flushing all Redis keys');

        $this->redisClient->flushdb();
    }

    /**
     * @return array{used_memory: string, connected_clients: string, total_keys: int, uptime: string}
     */
    public function getServerInfo(): array
    {
        /** @var array<string, mixed> $memoryInfo */
        $memoryInfo = $this->redisClient->info('memory');

        /** @var array<string, mixed> $clientsInfo */
        $clientsInfo = $this->redisClient->info('clients');

        /** @var array<string, mixed> $serverInfo */
        $serverInfo = $this->redisClient->info('server');

        /** @var int $dbSize */
        $dbSize = $this->redisClient->dbsize();

        $usedMemory = is_string($memoryInfo['used_memory_human'] ?? null)
            ? $memoryInfo['used_memory_human']
            : 'N/A';

        $rawConnectedClients = $clientsInfo['connected_clients'] ?? null;
        $connectedClients = is_scalar($rawConnectedClients) ? (string) $rawConnectedClients : 'N/A';

        $rawUptime = $serverInfo['uptime_in_seconds'] ?? null;
        $uptimeSeconds = is_int($rawUptime) ? $rawUptime : 0;

        return [
            'used_memory' => $usedMemory,
            'connected_clients' => $connectedClients,
            'total_keys' => $dbSize,
            'uptime' => $this->formatUptime($uptimeSeconds),
        ];
    }

    private function formatUptime(int $seconds): string
    {
        if ($seconds < 60) {
            return sprintf('%ds', $seconds);
        }

        if ($seconds < 3600) {
            return sprintf('%dm %ds', intdiv($seconds, 60), $seconds % 60);
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours < 24) {
            return sprintf('%dh %dm', $hours, $minutes);
        }

        $days = intdiv($hours, 24);
        $remainingHours = $hours % 24;

        return sprintf('%dj %dh', $days, $remainingHours);
    }
}
