<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Wohali\OAuth2\Client\Provider\DiscordResourceOwner;

class DiscordAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private EntityManagerInterface $entityManager,
        private RouterInterface $router
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return 'connect_discord_check' === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('discord');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client) {
                /** @var DiscordResourceOwner $discordUser */
                $discordUser = $client->fetchUserFromToken($accessToken);
                $userId = $discordUser->getId();

                $avatarUrl = $this->getDiscordAvatarUrl($discordUser);

                $user = $this->entityManager->getRepository(User::class)
                    ->findOneBy(['discordId' => $userId])
                    ?? new User();

                $user->setUsername($discordUser->getUsername() ?? 'DiscordUser');
                $user->setDiscordId($userId);
                $user->setAvatar($avatarUrl);

                $this->entityManager->persist($user);
                $this->entityManager->flush();

                return $user;
            })
        );
    }

    /**
     * Génère l'URL de l'avatar Discord.
     */
    private function getDiscordAvatarUrl(DiscordResourceOwner $discordUser): string
    {
        $userId = $discordUser->getId();
        $avatarHash = $discordUser->getAvatarHash() ?? null;
        $discriminator = $discordUser->getDiscriminator() ?? '0';

        return $avatarHash
            ? sprintf(
                'https://cdn.discordapp.com/avatars/%s/%s.%s',
                $userId,
                $avatarHash,
                str_starts_with($avatarHash, 'a_') ? 'gif' : 'png'
            )
            : sprintf('https://cdn.discordapp.com/embed/avatars/%d.png', (int) $discriminator % 5);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse(
            $this->router->generate('app_homepage')
        );
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());

        return new RedirectResponse(
            $this->router->generate('app_login', ['error' => $message])
        );
    }
}
