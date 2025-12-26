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
        $currencyData = $session->get('currency_data');

        $availableCurrencies = [];
        if (is_array($currencyData) && $this->isValidCurrencyDataArray($currencyData)) {
            /** @var array<int, array<string, mixed>> $validCurrencyData */
            $validCurrencyData = $currencyData;
            $availableCurrencies = $this->extractAvailableCurrencies($validCurrencyData);
        }

        return $this->render('currency/search.html.twig', [
            'has_data' => is_array($currencyData) && !empty($currencyData),
            'available_currencies' => $availableCurrencies,
        ]);
    }

    #[Route('/currency/clear', name: 'app_currency_clear', methods: ['POST'])]
    public function clearCurrencyData(Request $request): Response
    {
        $session = $request->getSession();
        $session->remove('currency_data');
        $session->remove('currency_results');

        $this->addFlash('info', 'Données effacées. Vous pouvez charger un nouveau fichier.');
        return $this->redirectToRoute('app_currency_search');
    }

    #[Route('/currency/upload', name: 'app_currency_upload', methods: ['POST'])]
    public function uploadCurrencyData(Request $request): Response
    {
        $uploadedFile = $request->files->get('currency_file');

        if ($uploadedFile === null || !is_object($uploadedFile)) {
            $this->addFlash('error', 'Veuillez sélectionner un fichier JSON.');
            return $this->redirectToRoute('app_currency_search');
        }

        if (!method_exists($uploadedFile, 'getClientOriginalExtension')) {
            $this->addFlash('error', 'Fichier invalide.');
            return $this->redirectToRoute('app_currency_search');
        }

        if ($uploadedFile->getClientOriginalExtension() !== 'json') {
            $this->addFlash('error', 'Le fichier doit être au format JSON.');
            return $this->redirectToRoute('app_currency_search');
        }

        if (!method_exists($uploadedFile, 'getPathname')) {
            $this->addFlash('error', 'Fichier invalide.');
            return $this->redirectToRoute('app_currency_search');
        }

        $pathname = $uploadedFile->getPathname();
        if (!is_string($pathname)) {
            $this->addFlash('error', 'Impossible de lire le chemin du fichier.');
            return $this->redirectToRoute('app_currency_search');
        }

        $jsonContent = file_get_contents($pathname);
        if ($jsonContent === false) {
            $this->addFlash('error', 'Impossible de lire le fichier.');
            return $this->redirectToRoute('app_currency_search');
        }

        $cleanedJson = $this->cleanJsonControlCharacters($jsonContent);
        $currencyData = json_decode($cleanedJson, true, 512, JSON_INVALID_UTF8_IGNORE);
        $jsonError = json_last_error();

        if ($jsonError !== JSON_ERROR_NONE) {
            $errorMsg = match ($jsonError) {
                JSON_ERROR_DEPTH => 'Profondeur maximale atteinte',
                JSON_ERROR_STATE_MISMATCH => 'JSON mal formé',
                JSON_ERROR_CTRL_CHAR => 'Caractère de contrôle inattendu',
                JSON_ERROR_SYNTAX => 'Erreur de syntaxe JSON',
                JSON_ERROR_UTF8 => 'Caractères UTF-8 invalides',
                default => 'Erreur JSON inconnue (code: ' . $jsonError . ')',
            };

            $this->logger->error('JSON decode error', [
                'error_code' => $jsonError,
                'error_message' => $errorMsg,
                'file_size' => strlen($jsonContent),
            ]);

            $this->addFlash('error', 'Erreur lors de la lecture du JSON : ' . $errorMsg);
            return $this->redirectToRoute('app_currency_search');
        }

        if (!is_array($currencyData)) {
            $this->addFlash('error', 'Le fichier JSON est invalide (pas un tableau).');
            return $this->redirectToRoute('app_currency_search');
        }

        $characters = $currencyData['characters'] ?? $currencyData;
        if (!is_array($characters)) {
            $this->addFlash('error', 'Le fichier JSON ne contient pas de données de personnages.');
            return $this->redirectToRoute('app_currency_search');
        }

        $session = $request->getSession();
        $session->set('currency_data', $characters);

        $this->logger->info('Currency data uploaded', [
            'characters_count' => count($currencyData),
        ]);

        $this->addFlash('success', 'Données de monnaie chargées avec succès !');
        return $this->redirectToRoute('app_currency_search');
    }

    #[Route('/currency/process', name: 'app_currency_process', methods: ['POST'])]
    public function processSearch(Request $request): Response
    {
        $session = $request->getSession();
        $currencyData = $session->get('currency_data');

        if (!is_array($currencyData) || empty($currencyData) || !$this->isValidCurrencyDataArray($currencyData)) {
            $this->addFlash('error', 'Veuillez d\'abord charger un fichier de données de monnaie.');
            return $this->redirectToRoute('app_currency_search');
        }

        $currencyName = $request->request->get('currency');

        if ($currencyName === null || !is_string($currencyName) || $currencyName === '') {
            $this->addFlash('error', 'Veuillez sélectionner une monnaie.');
            return $this->redirectToRoute('app_currency_search');
        }

        $this->logger->info('Searching currency from uploaded data', ['currency' => $currencyName]);

        /** @var array<int, array<string, mixed>> $validCurrencyData */
        $validCurrencyData = $currencyData;
        $results = $this->analyzeCurrenciesFromData($validCurrencyData, $currencyName);

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

    #[Route('/currency/debug', name: 'app_currency_debug', methods: ['GET'])]
    public function debugCurrencies(Request $request): Response
    {
        $session = $request->getSession();
        $accessToken = $session->get('blizzard_access_token');
        $expiresAt = $session->get('blizzard_token_expires_at', 0);

        if ($accessToken === null || $expiresAt <= time()) {
            return new Response('No valid OAuth token', 401);
        }

        if (!is_string($accessToken)) {
            return new Response('Invalid token', 401);
        }

        $targetCharacterName = $request->query->get('character');
        $targetRealmSlug = $request->query->get('realm');

        if (!is_string($targetCharacterName)) {
            $targetCharacterName = 'Prøtactinium';
        }

        if (!is_string($targetRealmSlug)) {
            $targetRealmSlug = 'dalaran';
        }

        $characterData = $this->apiService->fetchCharacterCurrencies(
            $accessToken,
            $targetRealmSlug,
            $targetCharacterName
        );

        $styles = "body{font-family:monospace;margin:20px;} ";
        $styles .= "pre{background:#f5f5f5;padding:15px;border-radius:5px;overflow-x:auto;}";

        $json = json_encode($characterData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return new Response('Failed to encode JSON', 500);
        }

        $jsonHtml = htmlspecialchars($json);

        $output = "<html><head><meta charset='UTF-8'><title>Debug Character Data</title>";
        $output .= "<style>{$styles}</style>";
        $output .= "</head><body>";
        $output .= "<h1>Full Character Data for {$targetCharacterName} on {$targetRealmSlug}</h1>";
        $output .= "<p>Usage: /currency/debug?character=Prøtactinium&realm=dalaran</p>";
        $output .= "<h2>Raw JSON Response:</h2>";
        $output .= "<pre>{$jsonHtml}</pre>";
        $output .= "<p><a href='/currency'>Back to search</a></p>";
        $output .= "</body></html>";

        return new Response($output);
    }

    /**
     * @param array<mixed, mixed> $currencyData
     */
    private function isValidCurrencyDataArray(array $currencyData): bool
    {
        foreach ($currencyData as $key => $value) {
            if (!is_int($key)) {
                return false;
            }

            if (!is_array($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, array<string, mixed>> $currencyData
     * @return array<string, mixed>
     */
    private function analyzeCurrenciesFromData(array $currencyData, string $targetCurrency): array
    {
        $results = [];
        $totalCurrency = 0;
        $totalCharacters = count($currencyData);

        foreach ($currencyData as $characterData) {
            $characterName = $characterData['name'] ?? null;
            if (!is_string($characterName)) {
                continue;
            }

            $currencies = $characterData['currencies'] ?? [];
            if (!is_array($currencies)) {
                continue;
            }

            $specificCurrency = null;
            foreach ($currencies as $currency) {
                if (!is_array($currency)) {
                    continue;
                }

                $currencyName = $currency['name'] ?? null;
                if ($currencyName === $targetCurrency) {
                    $specificCurrency = $currency;
                    break;
                }
            }

            if ($specificCurrency === null) {
                continue;
            }

            $quantity = $specificCurrency['quantity'] ?? 0;
            if (!is_int($quantity)) {
                continue;
            }

            $results[] = [
                'character' => [
                    'name' => $characterName,
                    'faction' => $characterData['faction'] ?? 'Unknown',
                    'lastUpdate' => $characterData['lastUpdate'] ?? 0,
                    'class' => $characterData['class'] ?? null,
                    'level' => $characterData['level'] ?? null,
                    'realm' => $characterData['realm'] ?? null,
                ],
                'currency' => $specificCurrency,
                'quantity' => $quantity,
            ];

            $totalCurrency += $quantity;
        }

        usort($results, fn (array $a, array $b): int => $b['quantity'] <=> $a['quantity']);

        $this->logger->info('Currency search completed from uploaded data', [
            'currency' => $targetCurrency,
            'total_characters' => $totalCharacters,
            'results_found' => count($results),
            'total_currency' => $totalCurrency,
        ]);

        return [
            'characters' => $results,
            'total_characters' => $totalCharacters,
            'total_currency' => $totalCurrency,
        ];
    }

    private function cleanJsonControlCharacters(string $json): string
    {
        $json = preg_replace('/"description":\s*"[^"]*(?:\\.[^"]*)*",?\s*/s', '', $json) ?? $json;

        $json = preg_replace('/,(\s*[}\]])/', '$1', $json) ?? $json;

        return $json;
    }

    /**
     * @param array<int, array<string, mixed>> $currencyData
     * @return array<string, string>
     */
    private function extractAvailableCurrencies(array $currencyData): array
    {
        $currencies = [];

        foreach ($currencyData as $characterData) {
            $characterCurrencies = $characterData['currencies'] ?? [];
            if (!is_array($characterCurrencies)) {
                continue;
            }

            foreach ($characterCurrencies as $currency) {
                if (!is_array($currency)) {
                    continue;
                }

                $name = $currency['name'] ?? null;
                if (is_string($name) && !isset($currencies[$name])) {
                    $currencies[$name] = $name;
                }
            }
        }

        asort($currencies);
        return $currencies;
    }
}
