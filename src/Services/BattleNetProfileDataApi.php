<?php

declare(strict_types = 1);

namespace App\Services;

use RuntimeException;
use Exception;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BattleNetProfileDataApi
{
    /**
     * Validates that the response contains all required fields for character details.
     *
     * @param array<string, mixed> $data
     */
    private function validateCharacterDetailsResponse(array $data): bool
    {
        $requiredFields = [
            '_links', 'id', 'name', 'gender', 'faction', 'race',
            'character_class', 'active_spec', 'realm', 'level',
            'experience', 'achievement_points', 'achievements',
            'titles', 'pvp_summary', 'encounters', 'media',
            'last_login_timestamp', 'average_item_level',
            'equipped_item_level', 'specializations', 'statistics',
            'mythic_keystone_profile', 'equipment', 'appearance',
            'collections', 'active_title', 'reputations', 'quests',
            'achievements_statistics', 'professions', 'name_search',
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return false;
            }
        }

        return true;
    }

    private string $clientId;

    private string $clientSecret;

    private string $region;

    private string $locale;

    private string $tokenUrl;

    private string $apiBaseUrl;

    private CacheInterface $cache;

    private HttpClientInterface $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        CacheInterface $cache,
        string $clientId,
        string $clientSecret,
        string $region,
        string $locale
    ) {
        $this->httpClient = $httpClient;
        $this->cache = $cache;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->region = $region;
        $this->locale = $locale;
        $this->tokenUrl = 'https://oauth.battle.net/token';
        $this->apiBaseUrl = "https://{$this->region}.api.blizzard.com";
    }

    public function getAuthorizationUrl(string $redirectUri, string $state): string
    {
        return "https://oauth.battle.net/authorize?client_id={$this->clientId}&response_type=code&redirect_uri={$redirectUri}&scope=openid wow.profile&state={$state}";
    }

    /**
     * @throws InvalidArgumentException
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
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

        $this->cache->get('blizzard_user_access_token', function (ItemInterface $item) use ($data) {
            $item->expiresAfter($data['expires_in']);

            return $data['access_token'];
        });

        if (isset($data['refresh_token'])) {
            $this->cache->get('blizzard_user_refresh_token', function (ItemInterface $item) use ($data) {
                $item->expiresAfter(86400);

                return $data['refresh_token'];
            });
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function isAuthenticated(): bool
    {
        $token = $this->cache->get('blizzard_user_access_token', function () {
            return '';
        });

        return '' !== $token;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function getValidAccessToken(): string
    {
        return $this->cache->get('blizzard_user_access_token', function (): never {
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
     * @throws TransportExceptionInterface
     * @throws InvalidArgumentException
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     *
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     level: int,
     *     realm: string,
     *     realm_slug: string,
     *     class: string,
     *     race: string,
     *     gender: string,
     *     faction: string,
     *     character_url: string,
     *     ilvl: int|null,
     *     guild: string|null,
     *     title: string|null,
     *     specialization: string|null,
     *     achievement_points: int|null,
     *     avatar: string|null
     * }>
     */
    public function getFormattedUserCharacters(): array
    {
        $data = $this->getUserCharacters();

        if (!isset($data['wow_accounts']) || !is_array($data['wow_accounts']) || !isset($data['wow_accounts'][0]['characters']) || !is_array($data['wow_accounts'][0]['characters'])) {
            return [];
        }

        $characters = [];

        foreach ($data['wow_accounts'][0]['characters'] as $char) {
            if (!is_array($char) || !isset($char['realm']['slug'], $char['name'])) {
                continue;
            }

            try {
                $characterDetails = $this->getCharacterDetails($char['realm']['slug'], $char['name']);

                $characters[] = [
                    'id'                 => $characterDetails['id'],
                    'name'               => $characterDetails['name'],
                    'level'              => $characterDetails['level'],
                    'realm'              => $characterDetails['realm']['name'],
                    'realm_slug'         => $characterDetails['realm']['slug'],
                    'class'              => $characterDetails['character_class']['name'],
                    'race'               => $characterDetails['race']['name'],
                    'gender'             => $characterDetails['gender']['name'],
                    'faction'            => $characterDetails['faction']['name'],
                    'character_url'      => $characterDetails['_links']['self']['href'],
                    'ilvl'               => $characterDetails['equipped_item_level'],
                    'guild'              => $characterDetails['guild']['name'] ?? null,
                    'title'              => $characterDetails['active_title']['name'],
                    'specialization'     => $characterDetails['active_spec']['name'],
                    'achievement_points' => $characterDetails['achievement_points'],
                    'avatar'             => $this->getCharacterMedia($char['realm']['slug'], $char['name']),
                ];
            } catch (Exception $e) {
                continue;
            }
        }

        return $characters;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws InvalidArgumentException
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function getCharacterMedia(string $realmSlug, string $characterName): ?string
    {
        $accessToken = $this->getValidAccessToken();
        $encodedCharacterName = strtolower(str_replace(' ', '-', $characterName));
        $url = "{$this->apiBaseUrl}/profile/wow/character/{$realmSlug}/{$encodedCharacterName}/character-media?namespace=profile-{$this->region}&locale={$this->locale}";

        try {
            $data = $this->fetchFromApiWithUserToken(
                "blizzard_character_media_{$realmSlug}_{$encodedCharacterName}",
                $url,
                $accessToken,
                3600
            );

            if (!isset($data['assets']) || !is_array($data['assets']) || !isset($data['assets'][0]['value'])) {
                return null;
            }

            return $data['assets'][0]['value'];
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @throws RuntimeException|InvalidArgumentException
     *
     * @return array{
     *     _links: array{self: array{href: string}},
     *     id: int,
     *     name: string,
     *     gender: array{type: string, name: string},
     *     faction: array{type: string, name: string},
     *     race: array{key: array{href: string}, name: string, id: int},
     *     character_class: array{key: array{href: string}, name: string, id: int},
     *     active_spec: array{key: array{href: string}, name: string, id: int},
     *     realm: array{key: array{href: string}, name: string, id: int, slug: string},
     *     guild: ?array{key: array{href: string}, name: string, id: int, realm: array, faction: array},
     *     level: int,
     *     experience: int,
     *     achievement_points: int,
     *     achievements: array{href: string},
     *     titles: array{href: string},
     *     pvp_summary: array{href: string},
     *     encounters: array{href: string},
     *     media: array{href: string},
     *     last_login_timestamp: int,
     *     average_item_level: int,
     *     equipped_item_level: int,
     *     specializations: array{href: string},
     *     statistics: array{href: string},
     *     mythic_keystone_profile: array{href: string},
     *     equipment: array{href: string},
     *     appearance: array{href: string},
     *     collections: array{href: string},
     *     active_title: array{key: array{href: string}, name: string, id: int, display_string: string},
     *     reputations: array{href: string},
     *     quests: array{href: string},
     *     achievements_statistics: array{href: string},
     *     professions: array{href: string},
     *     name_search: string
     * }
     */
    public function getCharacterDetails(string $realmSlug, string $characterName): array
    {
        $accessToken = $this->getValidAccessToken();
        $encodedCharacterName = strtolower(str_replace(' ', '-', $characterName));
        $url = "{$this->apiBaseUrl}/profile/wow/character/{$realmSlug}/{$encodedCharacterName}?namespace=profile-{$this->region}&locale={$this->locale}";

        $data = $this->fetchFromApiWithUserToken("blizzard_character_{$characterName}", $url, $accessToken, 3600);

        if (!$this->validateCharacterDetailsResponse($data)) {
            throw new RuntimeException('Invalid character details response from API');
        }

        /* @var array{
         *     _links: array{self: array{href: string}},
         *     id: int,
         *     name: string,
         *     gender: array{type: string, name: string},
         *     faction: array{type: string, name: string},
         *     race: array{key: array{href: string}, name: string, id: int},
         *     character_class: array{key: array{href: string}, name: string, id: int},
         *     active_spec: array{key: array{href: string}, name: string, id: int},
         *     realm: array{key: array{href: string}, name: string, id: int, slug: string},
         *     guild: ?array{key: array{href: string}, name: string, id: int, realm: array, faction: array},
         *     level: int,
         *     experience: int,
         *     achievement_points: int,
         *     achievements: array{href: string},
         *     titles: array{href: string},
         *     pvp_summary: array{href: string},
         *     encounters: array{href: string},
         *     media: array{href: string},
         *     last_login_timestamp: int,
         *     average_item_level: int,
         *     equipped_item_level: int,
         *     specializations: array{href: string},
         *     statistics: array{href: string},
         *     mythic_keystone_profile: array{href: string},
         *     equipment: array{href: string},
         *     appearance: array{href: string},
         *     collections: array{href: string},
         *     active_title: array{key: array{href: string}, name: string, id: int, display_string: string},
         *     reputations: array{href: string},
         *     quests: array{href: string},
         *     achievements_statistics: array{href: string},
         *     professions: array{href: string},
         *     name_search: string
         * } */
        return $data;
    }

    /**
     * @throws InvalidArgumentException
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
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

            /* @var array<string, mixed> */
            return $response->toArray();
        });
    }
}
