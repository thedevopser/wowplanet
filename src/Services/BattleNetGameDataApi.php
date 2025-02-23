<?php

declare(strict_types = 1);

namespace App\Services;

use RuntimeException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BattleNetGameDataApi
{
    private string $clientId;

    private string $clientSecret;

    private string $region;

    private string $locale;

    private string $tokenUrl;

    private string $apiBaseUrl;

    private CacheInterface $cache;

    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient, CacheInterface $cache, string $clientId, string $clientSecret, string $region, string $locale)
    {
        $this->httpClient = $httpClient;
        $this->cache = $cache;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->region = $region;
        $this->locale = $locale;
        $this->tokenUrl = "https://{$this->region}.battle.net/oauth/token";
        $this->apiBaseUrl = "https://{$this->region}.api.blizzard.com";
    }

    /**
     * Récupère le token d'accès client_credentials (cache 24h).
     */
    private function getAccessToken(): string
    {
        return $this->cache->get('blizzard_api_token', function (ItemInterface $item) {
            $item->expiresAfter(86400); // 24h

            $response = $this->httpClient->request('POST', $this->tokenUrl, [
                'body' => [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
            ]);

            $data = $response->toArray();

            if (!isset($data['access_token'])) {
                throw new RuntimeException('Unable to get Blizzard access token');
            }

            return $data['access_token'];
        });
    }

    /**
     * @throws InvalidArgumentException
     *
     * @return array<string, mixed>
     */
    public function getRealms(): array
    {
        $url = "{$this->apiBaseUrl}/data/wow/realm/index?namespace=dynamic-{$this->region}&locale={$this->locale}";

        return $this->fetchFromApi('blizzard_realms', $url, 86400);
    }

    /**
     * @throws InvalidArgumentException
     *
     * @return array<string, mixed>
     */
    public function getQuestAreas(): array
    {
        $url = "{$this->apiBaseUrl}/data/wow/quest/area/index?namespace=static-{$this->region}&locale={$this->locale}";

        return $this->fetchFromApi('blizzard_quest_areas', $url, 86400);
    }

    /**
     * @throws InvalidArgumentException
     *
     * @return array<string, mixed>
     */
    public function getQuestsByZone(int $questAreaId): array
    {
        $url = "{$this->apiBaseUrl}/data/wow/quest/area/{$questAreaId}?namespace=static-{$this->region}&locale={$this->locale}";

        return $this->fetchFromApi("blizzard_zone_quests_{$questAreaId}", $url, 86400);
    }

    /**
     * @throws InvalidArgumentException
     *
     * @return array<string, mixed>
     */
    public function getQuestDetails(int $questId): array
    {
        $url = "{$this->apiBaseUrl}/data/wow/quest/{$questId}?namespace=static-{$this->region}&locale={$this->locale}";

        return $this->fetchFromApi("blizzard_quest_{$questId}", $url, 86400);
    }

    /**
     * @throws InvalidArgumentException
     *
     * @return array<string, mixed>
     */
    private function fetchFromApi(string $cacheKey, string $url, int $cacheDuration): array
    {
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($url, $cacheDuration) {
            $item->expiresAfter($cacheDuration);

            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getAccessToken(),
                ],
            ]);

            return $response->toArray();
        });
    }
}
