<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\BlizzardApiService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ReputationController extends AbstractController
{
    public function __construct(
        private readonly BlizzardApiService $apiService,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('/reputation', name: 'app_reputation_search', methods: ['GET'])]
    public function searchForm(Request $request): Response
    {
        $session = $request->getSession();
        $accessToken = $session->get('blizzard_access_token');
        $expiresAt = $session->get('blizzard_token_expires_at', 0);

        if ($accessToken === null || $expiresAt <= time()) {
            $this->logger->warning('Reputation search attempted without valid token');
            $this->addFlash('warning', 'Vous devez vous authentifier avec Battle.net pour accéder aux réputations.');
            return $this->redirectToRoute('app_oauth_login');
        }

        return $this->render('reputation/search.html.twig');
    }

    #[Route('/reputation/process', name: 'app_reputation_process', methods: ['POST'])]
    public function processSearch(Request $request): Response
    {
        $session = $request->getSession();
        $accessToken = $session->get('blizzard_access_token');
        $expiresAt = $session->get('blizzard_token_expires_at', 0);

        if ($accessToken === null || $expiresAt <= time()) {
            $this->logger->warning('Reputation search attempted without valid token');
            $this->addFlash('warning', 'Vous devez vous authentifier avec Battle.net pour accéder aux réputations.');
            return $this->redirectToRoute('app_oauth_login');
        }

        $factionName = $request->request->get('faction');

        if ($factionName === null || !is_string($factionName)) {
            $this->addFlash('error', 'Veuillez saisir un nom de faction.');
            return $this->redirectToRoute('app_reputation_search');
        }

        if (!is_string($accessToken)) {
            $this->logger->error('Access token is not a string');
            return $this->redirectToRoute('app_oauth_login');
        }

        $this->logger->info('Searching reputation for faction', ['faction' => $factionName]);

        $results = $this->analyzeReputations($accessToken, $factionName);

        $session->set('reputation_results', [
            'faction' => $factionName,
            'results' => $results,
        ]);

        return $this->redirectToRoute('app_reputation_results');
    }

    #[Route('/reputation/results', name: 'app_reputation_results', methods: ['GET'])]
    public function showResults(Request $request): Response
    {
        $session = $request->getSession();
        $data = $session->get('reputation_results');

        if (!is_array($data) || !isset($data['faction'], $data['results'])) {
            $this->addFlash('warning', 'Aucun résultat disponible. Veuillez effectuer une recherche.');
            return $this->redirectToRoute('app_reputation_search');
        }

        $session->remove('reputation_results');

        return $this->render('reputation/results.html.twig', [
            'faction' => $data['faction'],
            'results' => $data['results'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function analyzeReputations(string $accessToken, string $targetFaction): array
    {
        $profile = $this->apiService->fetchUserProfile($accessToken);

        if (!isset($profile['wow_accounts']) || !is_array($profile['wow_accounts'])) {
            $this->logger->error('No WoW accounts found in profile');
            return [
                'error' => 'Aucun compte WoW trouvé. Vérifiez vos autorisations.',
                'characters' => [],
                'total_characters' => 0,
            ];
        }

        $characters = [];
        foreach ($profile['wow_accounts'] as $account) {
            if (!is_array($account) || !isset($account['characters']) || !is_array($account['characters'])) {
                continue;
            }

            foreach ($account['characters'] as $character) {
                if (is_array($character)) {
                    $characters[] = $character;
                }
            }
        }

        $this->logger->info('Found characters in profile', ['count' => count($characters)]);

        $results = [];

        foreach ($characters as $character) {
            $characterName = $character['name'] ?? null;
            $realm = $character['realm'] ?? null;

            if (!is_array($realm)) {
                continue;
            }

            $realmSlug = $realm['slug'] ?? null;

            if (!is_string($characterName) || !is_string($realmSlug)) {
                continue;
            }

            $reputations = $this->apiService->fetchCharacterReputations(
                $accessToken,
                $realmSlug,
                $characterName
            );

            if (!isset($reputations['reputations'])) {
                continue;
            }

            $specificReputation = $this->apiService->findSpecificReputation(
                $reputations,
                $targetFaction
            );

            if ($specificReputation === null) {
                continue;
            }

            $value = $this->apiService->calculateReputationValue($specificReputation);

            $results[] = [
                'character' => $character,
                'reputation' => $specificReputation,
                'value' => $value,
            ];

            usleep(100000);
        }

        usort($results, fn (array $a, array $b): int => $b['value'] <=> $a['value']);

        $this->logger->info('Reputation search completed', [
            'faction' => $targetFaction,
            'total_characters' => count($characters),
            'results_found' => count($results),
        ]);

        return [
            'characters' => $results,
            'total_characters' => count($characters),
        ];
    }
}
