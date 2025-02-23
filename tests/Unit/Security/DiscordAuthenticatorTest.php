<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\DiscordAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\Provider\DiscordClient;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Wohali\OAuth2\Client\Provider\DiscordResourceOwner;

class DiscordAuthenticatorTest extends TestCase
{
    public function testAuthenticate(): void
    {
        $clientRegistry = $this->createMock(ClientRegistry::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $router = $this->createMock(RouterInterface::class);
        $discordClient = $this->createMock(DiscordClient::class);
        $discordUser = $this->createMock(DiscordResourceOwner::class);
        $accessToken = $this->createMock(AccessToken::class);

        $clientRegistry->method('getClient')->willReturn($discordClient);
        $discordClient->method('fetchUserFromToken')->willReturn($discordUser);
        $discordClient->method('getAccessToken')->willReturn($accessToken);
        $discordUser->method('getId')->willReturn('discord-id');
        $discordUser->method('getUsername')->willReturn('Discord User');
        $discordUser->method('getAvatarHash')->willReturn('avatar-hash');
        $discordUser->method('getDiscriminator')->willReturn('1234');
        $accessToken->method('getToken')->willReturn('access-token');

        $authenticator = new DiscordAuthenticator($clientRegistry, $entityManager, $router);

        $request = new Request();
        $passport = $authenticator->authenticate($request);

        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);
        $this->assertInstanceOf(UserBadge::class, $passport->getBadge(UserBadge::class));
    }
}
