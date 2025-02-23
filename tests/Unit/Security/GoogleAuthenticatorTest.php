<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Security;

use App\Security\GoogleAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\Provider\GoogleClient;
use League\OAuth2\Client\Provider\GoogleUser;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class GoogleAuthenticatorTest extends TestCase
{
    public function testAuthenticate(): void
    {
        $clientRegistry = $this->createMock(ClientRegistry::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $router = $this->createMock(RouterInterface::class);
        $googleClient = $this->createMock(GoogleClient::class);
        $googleUser = $this->createMock(GoogleUser::class);
        $accessToken = $this->createMock(AccessToken::class);

        $clientRegistry->method('getClient')->willReturn($googleClient);
        $googleClient->method('fetchUserFromToken')->willReturn($googleUser);
        $googleClient->method('getAccessToken')->willReturn($accessToken);
        $googleUser->method('getId')->willReturn('google-id');
        $googleUser->method('getName')->willReturn('Google User');
        $googleUser->method('getAvatar')->willReturn('avatar-url');
        $accessToken->method('getToken')->willReturn('access-token');

        $authenticator = new GoogleAuthenticator($clientRegistry, $entityManager, $router);

        $request = new Request();
        $passport = $authenticator->authenticate($request);

        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);
        $this->assertInstanceOf(UserBadge::class, $passport->getBadge(UserBadge::class));
    }
}
