<?php

namespace App\Security;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

class BattleNetProvider extends GenericProvider
{
    use BearerAuthorizationTrait;

    public function __construct(array $options = [], array $collaborators = [])
    {
        $options = array_merge([
            'urlAuthorize' => 'https://oauth.battle.net/authorize',
            'urlAccessToken' => 'https://oauth.battle.net/token',
            'urlResourceOwnerDetails' => 'https://oauth.battle.net/userinfo',
            'scopes' => ['openid']
        ], $options);

        parent::__construct($options, $collaborators);
    }

    protected function createResourceOwner(array $response, AccessToken $token): BattleNetResourceOwner
    {
        return new BattleNetResourceOwner($response);
    }

    protected function checkResponse(ResponseInterface $response, $data): void
    {
        if ($response->getStatusCode() >= 400) {
            throw new IdentityProviderException(
                $data['error'] ?? $response->getReasonPhrase(),
                $response->getStatusCode(),
                $response
            );
        }
    }
}
