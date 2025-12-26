<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\BlizzardApiService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CurrencyController extends AbstractController
{
    public function __construct(
        private readonly BlizzardApiService $apiService,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('/currency', name: 'app_currency_search', methods: ['GET'])]
    public function searchForm(Request $request): Response
    {
        $session = $request->getSession();
        $accessToken = $session->get('blizzard_access_token');
        $expiresAt = $session->get('blizzard_token_expires_at', 0);

        if ($accessToken === null || $expiresAt <= time()) {
            $this->logger->warning('Currency search attempted without valid token');
            $this->addFlash('warning', 'Vous devez vous authentifier avec Battle.net pour accéder aux monnaies.');
            return $this->redirectToRoute('app_oauth_login');
        }

        return $this->render('currency/search.html.twig');
    }

    #[Route('/currency/process', name: 'app_currency_process', methods: ['POST'])]
    public function processSearch(Request $request): Response
    {
        $session = $request->getSession();
        $accessToken = $session->get('blizzard_access_token');
        $expiresAt = $session->get('blizzard_token_expires_at', 0);

        if ($accessToken === null || $expiresAt <= time()) {
            $this->logger->warning('Currency search process attempted without valid token');
            $this->addFlash('warning', 'Vous devez vous authentifier avec Battle.net pour accéder aux monnaies.');
            return $this->redirectToRoute('app_oauth_login');
        }

        $currencyName = $request->request->get('currency');

        if ($currencyName === null || !is_string($currencyName)) {
            $this->addFlash('error', 'Veuillez saisir un nom de monnaie.');
            return $this->redirectToRoute('app_currency_search');
        }

        if (!is_string($accessToken)) {
            $this->logger->error('Access token is not a string');
            return $this->redirectToRoute('app_oauth_login');
        }

        $this->logger->info('Searching currency', ['currency' => $currencyName]);

        $results = $this->analyzeCurrencies($accessToken, $currencyName);

        $session->set('currency_results', [
            'currency' => $currencyName,
            'results' => $results,
        ]);

        return $this->redirectToRoute('app_currency_results');
    }

    #[Route('/currency/results', name: 'app_currency_results', methods: ['GET'])]
    public function showResults(Request $request): Response
    {
        $session = $request->getSession();
        $data = $session->get('currency_results');

        if (!is_array($data) || !isset($data['currency'], $data['results'])) {
            $this->addFlash('warning', 'Aucun résultat disponible. Veuillez effectuer une recherche.');
            return $this->redirectToRoute('app_currency_search');
        }

        $session->remove('currency_results');

        return $this->render('currency/results.html.twig', [
            'currency' => $data['currency'],
            'results' => $data['results'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function analyzeCurrencies(string $accessToken, string $targetCurrency): array
    {
        $profile = $this->apiService->fetchUserProfile($accessToken);

        $wowAccounts = $profile['wow_accounts'] ?? null;
        if (!is_array($wowAccounts)) {
            $this->logger->error('No WoW accounts found in profile');
            return [
                'error' => 'Aucun compte WoW trouvé. Vérifiez vos autorisations.',
                'characters' => [],
                'total_characters' => 0,
                'total_currency' => 0,
            ];
        }

        $characters = $this->extractAllCharacters($wowAccounts);

        $this->logger->info('Found characters in profile', ['count' => count($characters)]);

        $results = [];
        $totalCurrency = 0;

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

            $currencies = $this->apiService->fetchCharacterCurrencies(
                $accessToken,
                $realmSlug,
                $characterName
            );

            if (!isset($currencies['currencies'])) {
                continue;
            }

            $specificCurrency = $this->apiService->findSpecificCurrency(
                $currencies,
                $targetCurrency
            );

            if ($specificCurrency === null) {
                continue;
            }

            $quantity = $this->apiService->extractCurrencyQuantity($specificCurrency);

            $results[] = [
                'character' => $character,
                'currency' => $specificCurrency,
                'quantity' => $quantity,
            ];

            $totalCurrency += $quantity;

            usleep(100000);
        }

        usort($results, fn (array $a, array $b): int => $b['quantity'] <=> $a['quantity']);

        $this->logger->info('Currency search completed', [
            'currency' => $targetCurrency,
            'total_characters' => count($characters),
            'results_found' => count($results),
            'total_currency' => $totalCurrency,
        ]);

        return [
            'characters' => $results,
            'total_characters' => count($characters),
            'total_currency' => $totalCurrency,
        ];
    }

    /**
     * @param array<mixed> $wowAccounts
     * @return array<int, array<string, mixed>>
     */
    private function extractAllCharacters(array $wowAccounts): array
    {
        /** @var array<int, array<string, mixed>> $characters */
        $characters = [];

        foreach ($wowAccounts as $account) {
            if (!is_array($account)) {
                continue;
            }

            $accountCharacters = $account['characters'] ?? null;
            if (!is_array($accountCharacters)) {
                continue;
            }

            foreach ($accountCharacters as $character) {
                if (!is_array($character)) {
                    continue;
                }
                /** @var array<string, mixed> $character */
                $characters[] = $character;
            }
        }

        return $characters;
    }
}
