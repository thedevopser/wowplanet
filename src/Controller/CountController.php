<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CharacterCountService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CountController extends AbstractController
{
    public function __construct(
        private readonly CharacterCountService $characterCountService,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('/count', name: 'app_count_search', methods: ['GET'])]
    public function searchForm(Request $request): Response
    {
        $session = $request->getSession();
        $accessToken = $session->get('blizzard_access_token');
        $expiresAt = $session->get('blizzard_token_expires_at', 0);

        if ($accessToken === null || $expiresAt <= time()) {
            $this->logger->warning('Count page accessed without valid token');
            $session->set('oauth_redirect_after_login', 'app_count_search');
            $this->addFlash('warning', 'Vous devez vous authentifier avec Battle.net pour accéder au compteur.');
            return $this->redirectToRoute('app_oauth_login');
        }

        return $this->render('count/search.html.twig');
    }

    #[Route('/count/process', name: 'app_count_process', methods: ['POST'])]
    public function processCount(Request $request): Response
    {
        $session = $request->getSession();
        $accessToken = $session->get('blizzard_access_token');
        $expiresAt = $session->get('blizzard_token_expires_at', 0);

        if ($accessToken === null || $expiresAt <= time()) {
            $this->logger->warning('Count process attempted without valid token');
            $this->addFlash('warning', 'Vous devez vous authentifier avec Battle.net pour accéder au compteur.');
            return $this->redirectToRoute('app_oauth_login');
        }

        if (!is_string($accessToken)) {
            $this->logger->error('Access token is not a string');
            return $this->redirectToRoute('app_oauth_login');
        }

        $this->logger->info('Starting character count by class');

        $result = $this->characterCountService->countCharactersByClass($accessToken);

        $session->set('count_results', $result->toArray());

        return $this->redirectToRoute('app_count_results');
    }

    #[Route('/count/results', name: 'app_count_results', methods: ['GET'])]
    public function showResults(Request $request): Response
    {
        $session = $request->getSession();
        $data = $session->get('count_results');

        if (!is_array($data)) {
            $this->addFlash('warning', 'Aucun résultat disponible. Veuillez effectuer une recherche.');
            return $this->redirectToRoute('app_count_search');
        }

        $session->remove('count_results');

        return $this->render('count/results.html.twig', [
            'results' => $data,
        ]);
    }
}
