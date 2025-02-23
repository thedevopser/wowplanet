<?php

declare(strict_types=1);

namespace App\Security;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

class BattleNetProvider extends GenericProvider
{
    use BearerAuthorizationTrait;

    /**
     * @param array<string> $options
     * @param array<string> $collaborators
     */
    public function __construct(array $options = [], array $collaborators = [])
    {
        $options = array_merge([
            'urlAuthorize'            => 'https://oauth.battle.net/authorize',
            'urlAccessToken'          => 'https://oauth.battle.net/token',
            'urlResourceOwnerDetails' => 'https://oauth.battle.net/userinfo',
            'scopes'                  => ['openid'],
        ], $options);

        parent::__construct($options, $collaborators);
    }

    /**
     * @param array<string, mixed> $response
     */
    protected function createResourceOwner(array $response, AccessToken $token): BattleNetResourceOwner
    {
        return new BattleNetResourceOwner($response);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws IdentityProviderException
     */
    protected function checkResponse(ResponseInterface $response, $data): void
    {
        if ($response->getStatusCode() >= 400) {
            $errorMessage = is_string($data['error'] ?? null) ? $data['error'] : $response->getReasonPhrase();
            throw new IdentityProviderException($errorMessage, $response->getStatusCode(), $response);
        }
    }
}
