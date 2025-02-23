<?php

declare(strict_types=1);

namespace App\Controller;

use LogicException;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error'         => $error,
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/connect/google', name: 'connect_google')]
    public function connectGoogle(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry->getClient('google')->redirect([], []);
    }

    #[Route('/connect/discord', name: 'connect_discord')]
    public function connectDiscord(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry->getClient('discord')->redirect([], []);
    }

    #[Route('/connect/battlenet', name: 'connect_battlenet')]
    public function connectBattlenet(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry->getClient('battlenet')->redirect([], []);
    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    #[Route('/connect/discord/check', name: 'connect_discord_check')]
    #[Route('/connect/battlenet/check', name: 'connect_battlenet_check')]
    public function check(Request $request): void
    {
    }
}
