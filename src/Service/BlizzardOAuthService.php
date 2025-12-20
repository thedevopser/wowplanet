<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class BlizzardOAuthService
{
    private const string OAUTH_BASE_URL = 'https://%s.battle.net/oauth';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $clientId,
        private string $clientSecret,
        private string $region,
        private string $redirectUri
    ) {
    }

    public function generateAuthorizationUrl(string $state): string
    {
        $params = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'wow.profile',
            'state' => $state,
        ]);

        return sprintf(
            self::OAUTH_BASE_URL . '/authorize?%s',
            $this->region,
            $params
        );
    }

    /**
     * @return array{access_token: string, expires_at: int}
     */
    public function exchangeCodeForToken(string $code): array
    {
        $tokenUrl = sprintf(self::OAUTH_BASE_URL . '/token', $this->region);

        $this->logger->info('Exchanging authorization code for access token');

        $response = $this->httpClient->request('POST', $tokenUrl, [
            'auth_basic' => [$this->clientId, $this->clientSecret],
            'body' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->redirectUri,
            ],
        ]);

        $data = $response->toArray();

        if (!isset($data['access_token']) || !is_string($data['access_token'])) {
            $this->logger->error('No access token received from Blizzard OAuth', ['response' => $data]);
            throw new \RuntimeException('No access token received from Blizzard OAuth');
        }

        $expiresIn = is_int($data['expires_in'] ?? null) ? $data['expires_in'] : 86400;

        $this->logger->info('Access token received successfully', [
            'expires_in' => $expiresIn,
        ]);

        return [
            'access_token' => $data['access_token'],
            'expires_at' => time() + $expiresIn,
        ];
    }
}
