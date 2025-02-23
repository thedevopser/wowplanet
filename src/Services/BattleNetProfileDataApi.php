<?php

declare(strict_types = 1);

namespace App\Services;

use RuntimeException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BattleNetProfileDataApi
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
        $this->tokenUrl = 'https://oauth.battle.net/token';
        $this->apiBaseUrl = "https://{$this->region}.api.blizzard.com";
    }

    /**
     * Génère l'URL d'autorisation OAuth.
     */
    public function getAuthorizationUrl(string $redirectUri, string $state): string
    {
        return "https://oauth.battle.net/authorize?client_id={$this->clientId}&response_type=code&redirect_uri={$redirectUri}&scope=openid wow.profile&state={$state}";
    }

    /**
     * Échange le code d'autorisation contre un access token utilisateur.
     *
     * @throws InvalidArgumentException
     */
    public function exchangeAuthorizationCode(string $code, string $redirectUri): void
    {
        $response = $this->httpClient->request('POST', $this->tokenUrl, [
            'body' => [
                'grant_type'    => 'authorization_code',
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code'          => $code,
                'redirect_uri'  => $redirectUri,
            ],
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
        ]);

        $data = $response->toArray();

        if (!isset($data['access_token'])) {
            throw new RuntimeException('Failed to retrieve access token.');
        }

        // Stocke le token dans le cache
        $this->cache->get('blizzard_user_access_token', function (ItemInterface $item) use ($data) {
            $item->expiresAfter($data['expires_in']);

            return $data['access_token'];
        });

        // Stocke aussi le refresh token
        if (isset($data['refresh_token'])) {
            $this->cache->get('blizzard_user_refresh_token', function (ItemInterface $item) use ($data) {
                $item->expiresAfter(86400);

                return $data['refresh_token'];
            });
        }
    }

    /**
     * Vérifie si l'utilisateur est authentifié.
     *
     * @throws InvalidArgumentException
     */
    public function isAuthenticated(): bool
    {
        $token = $this->cache->get('blizzard_user_access_token', function () {
            return '';
        });

        return '' != $token;
    }

    /**
     * Récupère le token valide pour les requêtes API.
     *
     * @throws InvalidArgumentException
     */
    private function getValidAccessToken(): string
    {
        return $this->cache->get('blizzard_user_access_token', function (): void {
            throw new RuntimeException('User is not authenticated. Redirect to authorization URL first.');
        });
    }

    /**
     * @throws RuntimeException|InvalidArgumentException
     *
     * @return array<string, mixed>
     */
    public function getUserCharacters(): array
    {
        $accessToken = $this->getValidAccessToken();
        $url = "{$this->apiBaseUrl}/profile/user/wow?namespace=profile-{$this->region}&locale={$this->locale}";

        return $this->fetchFromApiWithUserToken('blizzard_user_characters', $url, $accessToken, 3600);
    }

    /**
     * @throws RuntimeException|InvalidArgumentException
     *
     * @return array<string, mixed>
     */
    public function getCharacterDetails(string $realmSlug, string $characterName): array
    {
        $accessToken = $this->getValidAccessToken();
        $url = "{$this->apiBaseUrl}/profile/wow/character/{$realmSlug}/{$characterName}?namespace=profile-{$this->region}&locale={$this->locale}";

        return $this->fetchFromApiWithUserToken("blizzard_character_{$characterName}", $url, $accessToken, 3600);
    }

    /**
     * @throws InvalidArgumentException
     *
     * @return array<string, mixed>
     */
    private function fetchFromApiWithUserToken(string $cacheKey, string $url, string $accessToken, int $cacheDuration): array
    {
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($url, $accessToken, $cacheDuration) {
            $item->expiresAfter($cacheDuration);

            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]);

            return $response->toArray();
        });
    }
}
