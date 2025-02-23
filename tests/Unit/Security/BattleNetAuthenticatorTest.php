<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Security;

use App\Security\BattleNetAuthenticator;
use App\Security\BattleNetResourceOwner;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class BattleNetAuthenticatorTest extends TestCase
{
    public function testAuthenticate(): void
    {
        $clientRegistry = $this->createMock(ClientRegistry::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $router = $this->createMock(RouterInterface::class);
        $client = $this->createMock(OAuth2ClientInterface::class);
        $battleNetUser = $this->createMock(BattleNetResourceOwner::class);
        $accessToken = $this->createMock(AccessToken::class);

        $clientRegistry->method('getClient')->willReturn($client);
        $client->method('fetchUserFromToken')->willReturn($battleNetUser);
        $client->method('getAccessToken')->willReturn($accessToken);
        $battleNetUser->method('getId')->willReturn('battlenet-id');
        $battleNetUser->method('getBattletagName')->willReturn('Player#1234');
        $accessToken->method('getToken')->willReturn('access-token');

        $authenticator = new BattleNetAuthenticator($clientRegistry, $entityManager, $router);

        $request = new Request();
        $passport = $authenticator->authenticate($request);

        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);
        $this->assertInstanceOf(UserBadge::class, $passport->getBadge(UserBadge::class));
    }
}
