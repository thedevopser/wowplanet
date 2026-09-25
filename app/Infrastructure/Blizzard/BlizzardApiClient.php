<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard;

use App\Infrastructure\Blizzard\Responses\Exceptions\UnexpectedFieldTypeException;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;
use App\Infrastructure\Blizzard\Responses\SeasonIndexResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

class BlizzardApiClient
{
    private const NOT_MODIFIED = 304;

    /** Le plus petit index statique du catalogue : treize entrées pour lire un en-tête. */
    private const BUILD_PROBE_ENDPOINT = 'data/wow/playable-class/index';

    private readonly string $clientId;

    private readonly string $clientSecret;

    private readonly string $region;

    private readonly string $namespace;

    private ?string $lastSeenBuild = null;

    private ?string $lastModifiedSeen = null;

    public function __construct(private readonly Client $client)
    {
        /** @var string $clientId */
        $clientId = config('services.blizzard.client_id', '');
        $this->clientId = $clientId;

        /** @var string $clientSecret */
        $clientSecret = config('services.blizzard.client_secret', '');
        $this->clientSecret = $clientSecret;

        /** @var string $region */
        $region = config('services.blizzard.region', 'eu');
        $this->region = $region;

        $this->namespace = 'profile-'.$this->region;
    }

    /**
     * Guzzle remplace toute query string de l'URI par l'option 'query' : les
     * paramètres embarqués dans l'endpoint (ex. recherches bulk `?id=[a,b]`)
     * doivent donc être extraits et fusionnés explicitement.
     *
     * @param  array<string, string>  $query
     * @return array{0: string, 1: array<string, string>}
     */
    private function mergeEndpointQuery(string $endpoint, array $query): array
    {
        $parts = explode('?', $endpoint, 2);
        if (! isset($parts[1])) {
            return [$endpoint, $query];
        }

        parse_str($parts[1], $parsed);

        $embedded = [];
        foreach ($parsed as $key => $value) {
            if (! is_string($value)) {
                throw new InvalidArgumentException(sprintf('Query parameter [%s] of [%s] must be a single value.', $key, $endpoint));
            }

            $embedded[(string) $key] = $value;
        }

        return [$parts[0], array_merge($embedded, $query)];
    }

    public function getAccessToken(): string
    {
        /** @var string $token */
        $token = Cache::remember('blizzard_access_token', 3600, function (): string {
            $response = Http::asForm()
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->post(sprintf('https://%s.battle.net/oauth/token', $this->region), [
                    'grant_type' => 'client_credentials',
                ]);

            throw_if($response->failed(), \RuntimeException::class, 'Failed to fetch Blizzard access token');

            /** @var string $accessToken */
            $accessToken = $response->json('access_token');

            return $accessToken;
        });

        return $token;
    }

    /**
     * Frontière du décodage : la réponse en sort brute, telle que `json_decode` la rend. Seuls
     * la mettent en cache sous cette forme ceux qui doivent garder des données, jamais des objets ;
     * tout autre appelant passe par `getResponse()`.
     *
     * @param  array<string, string>  $query
     * @return array<array-key, mixed>
     *
     * @throws GuzzleException
     */
    public function get(string $endpoint, array $query = []): array
    {
        $accessToken = $this->getAccessToken();

        [$endpoint, $query] = $this->mergeEndpointQuery($endpoint, $query);
        $namespace = is_string($query['namespace'] ?? null) ? $query['namespace'] : $this->namespace;

        $response = $this->client->get($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer '.$accessToken,
                'Battlenet-Namespace' => $namespace,
            ],
            'query' => array_merge(['locale' => 'fr_FR'], $query),
        ]);

        $this->recordBuild($response);

        return $this->decode($endpoint, $response);
    }

    public function lastSeenBuild(): ?string
    {
        return $this->lastSeenBuild;
    }

    public function lastModifiedSeen(): ?string
    {
        return $this->lastModifiedSeen;
    }

    /**
     * Revalidation d'un index : rend `null` quand Blizzard répond 304, ce qui veut dire
     * inchangé et non échoué. Le corps d'un 304 étant vide, il ne doit jamais atteindre
     * le décodage JSON.
     *
     * @param  array<string, string>  $query
     *
     * @throws GuzzleException
     */
    public function getIfModifiedSince(string $endpoint, ?string $lastModified, array $query = []): ?ResponsePayload
    {
        $accessToken = $this->getAccessToken();

        [$endpoint, $query] = $this->mergeEndpointQuery($endpoint, $query);
        $namespace = is_string($query['namespace'] ?? null) ? $query['namespace'] : $this->namespace;

        $headers = [
            'Authorization' => 'Bearer '.$accessToken,
            'Battlenet-Namespace' => $namespace,
        ];

        if ($lastModified !== null) {
            $headers['If-Modified-Since'] = $lastModified;
        }

        $response = $this->client->get($endpoint, [
            'headers' => $headers,
            'query' => array_merge(['locale' => 'fr_FR'], $query),
        ]);

        $this->recordBuild($response);

        if ($response->getStatusCode() === self::NOT_MODIFIED) {
            return null;
        }

        $this->lastModifiedSeen = $response->getHeaderLine('Last-Modified') ?: null;

        return ResponsePayload::forEndpoint($endpoint, $this->decode($endpoint, $response));
    }

    /**
     * Le build sert à décider s'il y a lieu d'importer : la sonde ne part donc que
     * si aucune réponse n'a encore livré le sien.
     *
     * @throws GuzzleException
     */
    public function currentBuild(): ?string
    {
        if ($this->lastSeenBuild === null) {
            $this->get(self::BUILD_PROBE_ENDPOINT, ['namespace' => 'static-'.$this->region]);
        }

        return $this->lastSeenBuild;
    }

    private function recordBuild(ResponseInterface $response): void
    {
        $build = BlizzardNamespace::build($response->getHeaderLine('battlenet-namespace'));

        if ($build !== null) {
            $this->lastSeenBuild = $build;
        }
    }

    /**
     * Porte typée : la réponse en ressort sous forme de lecteur.
     *
     * @param  array<string, string>  $query
     *
     * @throws GuzzleException
     */
    public function getResponse(string $endpoint, array $query = []): ResponsePayload
    {
        return ResponsePayload::forEndpoint($endpoint, $this->get($endpoint, $query));
    }

    /**
     * @param  array<string, string>  $query
     *
     * @throws GuzzleException
     */
    public function getWithUserToken(string $endpoint, string $userToken, array $query = []): ResponsePayload
    {
        $response = $this->client->get($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer '.$userToken,
                'Battlenet-Namespace' => $this->namespace,
            ],
            'query' => array_merge(['locale' => 'fr_FR'], $query),
        ]);

        return ResponsePayload::forEndpoint($endpoint, $this->decode($endpoint, $response));
    }

    /**
     * @param  array<string, string>  $query
     */
    public function getAsync(string $endpoint, array $query = []): PromiseInterface
    {
        $accessToken = $this->getAccessToken();

        [$endpoint, $query] = $this->mergeEndpointQuery($endpoint, $query);
        $namespace = is_string($query['namespace'] ?? null) ? $query['namespace'] : $this->namespace;

        return $this->client->getAsync($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer '.$accessToken,
                'Battlenet-Namespace' => $namespace,
            ],
            'query' => array_merge(['locale' => 'fr_FR'], $query),
        ]);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decode(string $endpoint, ResponseInterface $response): array
    {
        $decoded = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw UnexpectedFieldTypeException::at('(root)', $endpoint, 'object', $decoded);
        }

        return $decoded;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getRegion(): string
    {
        return $this->region;
    }

    /**
     * Le cast n'est pas décoratif : Laravel ne sérialise pas les valeurs numériques, et
     * Redis les rend donc en chaîne, là où le store array rend l'entier écrit. Sans lui,
     * la seconde lecture — celle qui vient du cache — casse le type de retour partout
     * ailleurs qu'en test.
     */
    public function getCurrentMythicSeasonId(): int
    {
        /** @var int|string $seasonId */
        $seasonId = Cache::remember(
            'blizzard_current_m_plus_season',
            86400,
            fn (): int => $this->fetchCurrentSeasonId('data/wow/mythic-keystone/season/index'),
        );

        return (int) $seasonId;
    }

    public function getCurrentPvpSeasonId(): int
    {
        /** @var int|string $seasonId */
        $seasonId = Cache::remember(
            'blizzard_current_pvp_season',
            86400,
            fn (): int => $this->fetchCurrentSeasonId('data/wow/pvp-season/index'),
        );

        return (int) $seasonId;
    }

    /**
     * Hors saison, l'index n'en porte aucune : 0 reste la valeur d'absence attendue par les appelants.
     *
     * @throws GuzzleException
     */
    private function fetchCurrentSeasonId(string $endpoint): int
    {
        $seasonIndexResponse = SeasonIndexResponse::fromPayload($this->getResponse($endpoint, [
            'namespace' => 'dynamic-'.$this->region,
        ]));

        return $seasonIndexResponse->currentSeasonId ?? 0;
    }

    /**
     * @return array{headers: array{Authorization: string, Battlenet-Namespace: string}, query: array{locale: string, namespace: string}}
     */
    public function getBaseOptions(): array
    {
        return [
            'headers' => [
                'Authorization' => 'Bearer '.$this->getAccessToken(),
                'Battlenet-Namespace' => 'static-'.$this->region,
            ],
            'query' => [
                'locale' => 'fr_FR',
                'namespace' => 'static-'.$this->region,
            ],
        ];
    }
}
