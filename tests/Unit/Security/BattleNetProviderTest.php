<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Security;

use App\Security\BattleNetProvider;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\TestCase;

class BattleNetProviderTest extends TestCase
{
    public function testUrls(): void
    {
        $provider = new BattleNetProvider([
            'clientId'     => 'test_client_id',
            'clientSecret' => 'test_client_secret',
            'redirectUri'  => 'http://example.com/callback',
        ]);

        $this->assertEquals('https://oauth.battle.net/authorize', $provider->getBaseAuthorizationUrl());
        $this->assertEquals('https://oauth.battle.net/token', $provider->getBaseAccessTokenUrl([]));
        $this->assertEquals('https://oauth.battle.net/userinfo', $provider->getResourceOwnerDetailsUrl(new AccessToken(['access_token' => 'test'])));
    }
}
