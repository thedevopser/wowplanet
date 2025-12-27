<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\BlizzardOAuthService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BlizzardOAuthController extends AbstractController
{
    public function __construct(
        private readonly BlizzardOAuthService $oauthService,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('/oauth', name: 'app_oauth_login', methods: ['GET'])]
    public function login(Request $request): Response
    {
        $session = $request->getSession();

        $existingToken = $session->get('blizzard_access_token');
        $expiresAt = $session->get('blizzard_token_expires_at', 0);

        $isAuthenticated = $existingToken !== null && $expiresAt > time();

        if ($isAuthenticated) {
            $this->logger->info('User already authenticated, redirecting to reputation page');
            return $this->redirectToRoute('app_reputation_search');
        }

        $state = bin2hex(random_bytes(16));
        $session->set('oauth_state', $state);

        $authorizationUrl = $this->oauthService->generateAuthorizationUrl($state);

        $this->logger->info('Generating OAuth authorization URL', ['state' => $state]);

        return $this->render('oauth/login.html.twig', [
            'authorization_url' => $authorizationUrl,
            'token_expired' => $existingToken !== null && $expiresAt <= time(),
        ]);
    }

    #[Route('/oauth/callback', name: 'app_oauth_callback', methods: ['GET'])]
    public function callback(Request $request): Response
    {
        $code = $request->query->get('code');
        $state = $request->query->get('state');
        $error = $request->query->get('error');

        if ($error !== null) {
            $this->logger->error('OAuth error received', ['error' => $error]);
            $this->addFlash('error', sprintf('Erreur OAuth : %s', $error));
            return $this->redirectToRoute('app_oauth_login');
        }

        $session = $request->getSession();
        $expectedState = $session->get('oauth_state');

        if ($code === null || $state === null || $state !== $expectedState) {
            $this->logger->error('Invalid OAuth callback parameters or state mismatch');
            $this->addFlash('error', 'Paramètres invalides ou state mismatch');
            return $this->redirectToRoute('app_oauth_login');
        }

        $tokenData = $this->oauthService->exchangeCodeForToken($code);

        $session->set('blizzard_access_token', $tokenData['access_token']);
        $session->set('blizzard_token_expires_at', $tokenData['expires_at']);
        $session->remove('oauth_state');

        $this->logger->info('OAuth authentication successful', [
            'expires_at' => date('Y-m-d H:i:s', $tokenData['expires_at']),
        ]);

        $this->addFlash('success', 'Authentification réussie ! Vous pouvez maintenant rechercher vos réputations.');

        return $this->redirectToRoute('app_reputation_search');
    }

    #[Route('/oauth/logout', name: 'app_oauth_logout', methods: ['GET'])]
    public function logout(Request $request): Response
    {
        $session = $request->getSession();
        $session->remove('blizzard_access_token');
        $session->remove('blizzard_token_expires_at');

        $this->logger->info('User logged out from Blizzard OAuth');
        $this->addFlash('info', 'Vous avez été déconnecté.');

        return $this->redirectToRoute('app_oauth_login');
    }
}
