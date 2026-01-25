<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class BlizzardApiService implements BlizzardApiServiceInterface
{
    private const string API_BASE_URL = 'https://%s.api.blizzard.com';
    private const int CACHE_TTL_PROFILE = 300; // 5 minutes for user profile
    private const int CACHE_TTL_REPUTATION = 600; // 10 minutes for reputations
    private const int CACHE_TTL_CURRENCY = 600; // 10 minutes for currencies
    private const int CACHE_TTL_CHARACTER = 600; // 10 minutes for character details

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
        private LoggerInterface $logger,
        private string $region,
        private string $locale
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchUserProfile(string $accessToken): array
    {
        $cacheKey = sprintf('blizzard_profile_%s', hash('sha256', $accessToken));

        /** @var array<string, mixed> $cachedData */
        $cachedData = $this->cache->get($cacheKey, function (ItemInterface $item) use ($accessToken): array {
            $item->expiresAfter(self::CACHE_TTL_PROFILE);

            $url = sprintf(
                self::API_BASE_URL . '/profile/user/wow?namespace=profile-%s&locale=%s',
                $this->region,
                $this->region,
                $this->locale
            );

            $this->logger->info('Fetching user profile from Blizzard API', ['url' => $url]);

            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $accessToken),
                ],
            ]);

            $data = $response->toArray();
            $accountsData = $data['wow_accounts'] ?? [];

            $this->logger->info('User profile fetched successfully', [
                'accounts_count' => is_array($accountsData) ? count($accountsData) : 0,
            ]);

            return $data;
        });

        return $cachedData;
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchCharacterReputations(
        string $accessToken,
        string $realmSlug,
        string $characterName
    ): array {
        $characterNameLower = strtolower($characterName);
        $cacheKey = sprintf(
            'blizzard_reputation_%s_%s',
            $realmSlug,
            $characterNameLower
        );

        /** @var array<string, mixed> $cachedData */
        $cachedData = $this->cache->get($cacheKey, function (ItemInterface $item) use (
            $accessToken,
            $realmSlug,
            $characterNameLower
        ): array {
            $item->expiresAfter(self::CACHE_TTL_REPUTATION);

            $url = sprintf(
                self::API_BASE_URL . '/profile/wow/character/%s/%s/reputations?namespace=profile-%s&locale=%s',
                $this->region,
                $realmSlug,
                rawurlencode($characterNameLower),
                $this->region,
                $this->locale
            );

            $this->logger->info('Fetching character reputations from Blizzard API', [
                'realm' => $realmSlug,
                'character' => $characterNameLower,
            ]);

            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $accessToken),
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                $this->logger->warning('Failed to fetch character reputations', [
                    'realm' => $realmSlug,
                    'character' => $characterNameLower,
                    'status_code' => $statusCode,
                ]);

                return [];
            }

            $data = $response->toArray(false);
            $reputationsData = $data['reputations'] ?? [];

            $this->logger->debug('Character reputations fetched', [
                'realm' => $realmSlug,
                'character' => $characterNameLower,
                'reputations_count' => is_array($reputationsData) ? count($reputationsData) : 0,
            ]);

            return $data;
        });

        return $cachedData;
    }

    /**
     * @param array<string, mixed> $reputations
     * @return array<string, mixed>|null
     */
    public function findSpecificReputation(array $reputations, string $targetFaction): ?array
    {
        $reputationsList = $reputations['reputations'] ?? [];
        if (!is_array($reputationsList)) {
            return null;
        }

        $normalizedTarget = $this->normalizeFactionName($targetFaction);

        foreach ($reputationsList as $reputation) {
            if (!is_array($reputation)) {
                continue;
            }

            $faction = $reputation['faction'] ?? null;
            if (!is_array($faction)) {
                continue;
            }

            $factionName = $faction['name'] ?? null;
            if (!is_string($factionName)) {
                continue;
            }

            $normalizedFactionName = $this->normalizeFactionName($factionName);

            if ($normalizedFactionName === $normalizedTarget) {
                /** @var array<string, mixed> $reputation */
                return $reputation;
            }
        }

        return null;
    }

    private function normalizeFactionName(string $name): string
    {
        $normalized = mb_strtolower($name);
        $normalized = str_replace(["\u{2019}", "\u{2018}", "\u{00B4}", '`'], "'", $normalized);
        $normalized = str_replace(["\u{2013}", "\u{2014}"], '-', $normalized);
        $normalized = trim($normalized);

        return $normalized;
    }

    /**
     * @param array<string, mixed> $reputation
     */
    public function calculateReputationValue(array $reputation): int
    {
        $standings = [
            'Haï' => 0,
            'Hostile' => 1,
            'Inamical' => 2,
            'Neutre' => 3,
            'Amical' => 4,
            'Honoré' => 5,
            'Révéré' => 6,
            'Exalté' => 7,
            'Hated' => 0,
            'Unfriendly' => 2,
            'Neutral' => 3,
            'Friendly' => 4,
            'Honored' => 5,
            'Revered' => 6,
            'Exalted' => 7,
        ];

        $standing = $reputation['standing'] ?? [];
        if (!is_array($standing)) {
            return 300000;
        }

        $standingName = is_string($standing['name'] ?? null) ? $standing['name'] : 'Neutre';
        $baseValue = ($standings[$standingName] ?? 3) * 100000;

        $standingValue = is_int($standing['value'] ?? null) ? $standing['value'] : 0;
        $value = min($standingValue, 99999);

        $renownLevel = $standing['renown_level'] ?? null;
        if (is_int($renownLevel)) {
            $baseValue = 10000000 + ($renownLevel * 10000);
        }

        return $baseValue + $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchCharacterCurrencies(
        string $accessToken,
        string $realmSlug,
        string $characterName
    ): array {
        $characterNameLower = strtolower($characterName);
        $cacheKey = sprintf(
            'blizzard_currency_%s_%s',
            $realmSlug,
            $characterNameLower
        );

        /** @var array<string, mixed> $cachedData */
        $cachedData = $this->cache->get($cacheKey, function (ItemInterface $item) use (
            $accessToken,
            $realmSlug,
            $characterNameLower
        ): array {
            $item->expiresAfter(self::CACHE_TTL_CURRENCY);

            $url = sprintf(
                self::API_BASE_URL . '/profile/wow/character/%s/%s?namespace=profile-%s&locale=%s',
                $this->region,
                $realmSlug,
                rawurlencode($characterNameLower),
                $this->region,
                $this->locale
            );

            $this->logger->info('Fetching character profile for currencies from Blizzard API', [
                'realm' => $realmSlug,
                'character' => $characterNameLower,
                'url' => $url,
            ]);

            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $accessToken),
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                $this->logger->warning('Failed to fetch character profile for currencies', [
                    'realm' => $realmSlug,
                    'character' => $characterNameLower,
                    'status_code' => $statusCode,
                ]);

                return [];
            }

            $data = $response->toArray(false);

            $this->logger->debug('Character profile fetched, checking for currencies', [
                'realm' => $realmSlug,
                'character' => $characterNameLower,
                'keys' => array_keys($data),
            ]);

            return $data;
        });

        return $cachedData;
    }

    /**
     * @param array<string, mixed> $currencies
     * @return array<string, mixed>|null
     */
    public function findSpecificCurrency(array $currencies, string $targetCurrencyName): ?array
    {
        $currenciesList = $currencies['currencies'] ?? [];
        if (!is_array($currenciesList)) {
            return null;
        }

        $normalizedTarget = $this->normalizeCurrencyName($targetCurrencyName);

        foreach ($currenciesList as $currencyEntry) {
            if (!is_array($currencyEntry)) {
                continue;
            }

            $currency = $currencyEntry['currency'] ?? null;
            if (!is_array($currency)) {
                continue;
            }

            $currencyName = $currency['name'] ?? null;
            if (!is_string($currencyName)) {
                continue;
            }

            $normalizedCurrencyName = $this->normalizeCurrencyName($currencyName);

            if ($normalizedCurrencyName === $normalizedTarget) {
                /** @var array<string, mixed> $currencyEntry */
                return $currencyEntry;
            }
        }

        return null;
    }

    private function normalizeCurrencyName(string $name): string
    {
        $normalized = mb_strtolower($name);
        $normalized = str_replace(["\u{2019}", "\u{2018}", "\u{00B4}", '`'], "'", $normalized);
        $normalized = str_replace(["\u{2013}", "\u{2014}"], '-', $normalized);
        $normalized = trim($normalized);

        return $normalized;
    }

    /**
     * @param array<string, mixed> $currencyEntry
     */
    public function extractCurrencyQuantity(array $currencyEntry): int
    {
        $quantity = $currencyEntry['quantity'] ?? 0;

        if (!is_int($quantity)) {
            $this->logger->warning('Currency quantity is not an integer', [
                'quantity' => $quantity,
                'type' => gettype($quantity),
            ]);
            return 0;
        }

        return max(0, $quantity);
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchCharacterProfile(
        string $accessToken,
        string $realmSlug,
        string $characterName
    ): array {
        $characterNameLower = strtolower($characterName);
        $cacheKey = sprintf(
            'blizzard_character_%s_%s',
            $realmSlug,
            $characterNameLower
        );

        /** @var array<string, mixed> $cachedData */
        $cachedData = $this->cache->get($cacheKey, function (ItemInterface $item) use (
            $accessToken,
            $realmSlug,
            $characterNameLower
        ): array {
            $item->expiresAfter(self::CACHE_TTL_CHARACTER);

            $url = sprintf(
                self::API_BASE_URL . '/profile/wow/character/%s/%s?namespace=profile-%s&locale=%s',
                $this->region,
                $realmSlug,
                rawurlencode($characterNameLower),
                $this->region,
                $this->locale
            );

            $this->logger->debug('Fetching character profile from Blizzard API', [
                'realm' => $realmSlug,
                'character' => $characterNameLower,
            ]);

            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $accessToken),
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                $this->logger->warning('Failed to fetch character profile', [
                    'realm' => $realmSlug,
                    'character' => $characterNameLower,
                    'status_code' => $statusCode,
                ]);

                return [];
            }

            return $response->toArray(false);
        });

        return $cachedData;
    }
}
