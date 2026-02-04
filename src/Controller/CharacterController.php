<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\CharacterDetailProfile;
use App\DTO\CharacterMedia;
use App\Service\BlizzardApiService;
use App\Service\QuestExpansionMapper;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CharacterController extends AbstractController
{
    public function __construct(
        private readonly BlizzardApiService $apiService,
        private readonly QuestExpansionMapper $questExpansionMapper,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('/characters', name: 'app_characters_list', methods: ['GET'])]
    public function searchForm(Request $request): Response
    {
        $session = $request->getSession();
        $accessToken = $session->get('blizzard_access_token');
        $expiresAt = $session->get('blizzard_token_expires_at', 0);

        if ($accessToken === null || $expiresAt <= time()) {
            $this->logger->warning('Characters list attempted without valid token');
            $session->set('oauth_redirect_after_login', 'app_characters_list');
            $this->addFlash('warning', 'Vous devez vous authentifier avec Battle.net pour voir vos personnages.');

            return $this->redirectToRoute('app_oauth_login');
        }

        if (is_array($session->get('characters_results'))) {
            return $this->redirectToRoute('app_characters_results');
        }

        return $this->render('character/search.html.twig');
    }

    #[Route('/characters/process', name: 'app_characters_process', methods: ['POST'])]
    public function processCharacters(Request $request): Response
    {
        $session = $request->getSession();
        $accessToken = $session->get('blizzard_access_token');
        $expiresAt = $session->get('blizzard_token_expires_at', 0);

        if ($accessToken === null || $expiresAt <= time()) {
            $this->logger->warning('Characters process attempted without valid token');
            $this->addFlash('warning', 'Vous devez vous authentifier avec Battle.net pour voir vos personnages.');

            return $this->redirectToRoute('app_oauth_login');
        }

        if (!is_string($accessToken)) {
            $this->logger->error('Access token is not a string');

            return $this->redirectToRoute('app_oauth_login');
        }

        $this->logger->info('Starting characters fetch with guild enrichment');

        $characters = $this->fetchAllCharacters($accessToken);

        $session->set('characters_results', $characters);

        return $this->redirectToRoute('app_characters_results');
    }

    #[Route('/characters/results', name: 'app_characters_results', methods: ['GET'])]
    public function showResults(Request $request): Response
    {
        $session = $request->getSession();
        $characters = $session->get('characters_results');

        if (!is_array($characters)) {
            $this->addFlash('warning', 'Aucun resultat disponible. Veuillez effectuer une recherche.');

            return $this->redirectToRoute('app_characters_list');
        }

        $this->logger->info('Characters list displayed', [
            'total_characters' => count($characters),
        ]);

        return $this->render('character/results.html.twig', [
            'characters' => $characters,
        ]);
    }

    #[Route('/characters/refresh', name: 'app_characters_refresh', methods: ['POST'])]
    public function refreshCharacters(Request $request): Response
    {
        $session = $request->getSession();
        $session->remove('characters_results');

        $this->logger->info('Characters cache cleared, redirecting to process');

        return $this->redirectToRoute('app_characters_process');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAllCharacters(string $accessToken): array
    {
        $profile = $this->apiService->fetchUserProfile($accessToken);

        if (!isset($profile['wow_accounts']) || !is_array($profile['wow_accounts'])) {
            $this->logger->error('No WoW accounts found in profile');

            return [];
        }

        /** @var list<array<string, mixed>> $characters */
        $characters = [];
        foreach ($profile['wow_accounts'] as $account) {
            if (!is_array($account) || !isset($account['characters']) || !is_array($account['characters'])) {
                continue;
            }

            foreach ($account['characters'] as $character) {
                if (!is_array($character)) {
                    continue;
                }

                /** @var array<string, mixed> $character */
                $characters[] = $character;
            }
        }

        usort($characters, static fn (array $a, array $b): int => ($b['level'] ?? 0) <=> ($a['level'] ?? 0));

        return $this->enrichCharactersWithGuildInfo($accessToken, $characters);
    }

    /**
     * @param list<array<string, mixed>> $characters
     * @return list<array<string, mixed>>
     */
    private function enrichCharactersWithGuildInfo(string $accessToken, array $characters): array
    {
        /** @var list<array<string, mixed>> $enrichedCharacters */
        $enrichedCharacters = [];

        foreach ($characters as $character) {
            $enrichedCharacter = $this->enrichSingleCharacter($accessToken, $character);
            $enrichedCharacters[] = $enrichedCharacter;
        }

        return $enrichedCharacters;
    }

    /**
     * @param array<string, mixed> $character
     * @return array<string, mixed>
     */
    private function enrichSingleCharacter(string $accessToken, array $character): array
    {
        $realm = $character['realm'] ?? null;
        if (!is_array($realm)) {
            return $character;
        }

        $realmSlug = $realm['slug'] ?? null;
        $characterName = $character['name'] ?? null;

        if (!is_string($realmSlug) || !is_string($characterName)) {
            return $character;
        }

        $profile = $this->apiService->fetchCharacterProfile($accessToken, $realmSlug, $characterName);

        if ($profile === []) {
            return $character;
        }

        $guild = $profile['guild'] ?? null;
        if (!is_array($guild)) {
            return $character;
        }

        $character['guild'] = $guild;

        return $character;
    }

    #[Route('/characters/{realmSlug}/{characterName}', name: 'app_character_detail', methods: ['GET'])]
    public function showCharacterDetail(
        Request $request,
        string $realmSlug,
        string $characterName
    ): Response {
        $accessToken = $this->validateBlizzardToken($request);

        if ($accessToken === null) {
            $this->addFlash('warning', 'Vous devez vous authentifier avec Battle.net.');

            return $this->redirectToRoute('app_oauth_login');
        }

        $this->logger->info('Fetching character detail', [
            'realm' => $realmSlug,
            'character' => $characterName,
        ]);

        $profileData = $this->apiService->fetchCharacterProfile($accessToken, $realmSlug, $characterName);
        $mediaData = $this->apiService->fetchCharacterMedia($accessToken, $realmSlug, $characterName);
        $completedQuestsData = $this->apiService->fetchCompletedQuests($accessToken, $realmSlug, $characterName);

        $profile = CharacterDetailProfile::fromApiData($profileData);
        $media = CharacterMedia::fromApiData($mediaData);
        $completedQuestIds = $this->extractCompletedQuestIds($completedQuestsData);
        $expansionGroups = $this->questExpansionMapper->buildExpansionProgress($completedQuestIds);

        return $this->render('character/detail.html.twig', [
            'profile' => $profile,
            'media' => $media,
            'expansionGroups' => $expansionGroups,
            'realmSlug' => $realmSlug,
            'characterName' => $characterName,
        ]);
    }

    #[Route(
        '/characters/{realmSlug}/{characterName}/quests/{expansionOrder}',
        name: 'app_character_expansion_quests',
        methods: ['GET'],
        requirements: ['expansionOrder' => '\d+']
    )]
    public function loadExpansionQuests(
        Request $request,
        string $realmSlug,
        string $characterName,
        int $expansionOrder
    ): Response {
        $accessToken = $this->validateBlizzardToken($request);

        if ($accessToken === null) {
            return new Response('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        $this->logger->info('Loading expansion zone quests', [
            'realm' => $realmSlug,
            'character' => $characterName,
            'expansion_order' => $expansionOrder,
        ]);

        $completedQuestsData = $this->apiService->fetchCompletedQuests(
            $accessToken,
            $realmSlug,
            $characterName
        );
        $completedQuestIds = $this->extractCompletedQuestIds($completedQuestsData);
        $zoneProgressList = $this->questExpansionMapper->buildZoneProgress(
            $expansionOrder,
            $completedQuestIds
        );

        return $this->render('character/_expansion_zones.html.twig', [
            'zones' => $zoneProgressList,
            'expansionOrder' => $expansionOrder,
        ]);
    }

    private function validateBlizzardToken(Request $request): ?string
    {
        $session = $request->getSession();
        $accessToken = $session->get('blizzard_access_token');
        $expiresAt = $session->get('blizzard_token_expires_at', 0);

        if ($accessToken === null || $expiresAt <= time()) {
            return null;
        }

        if (!is_string($accessToken)) {
            return null;
        }

        return $accessToken;
    }

    /**
     * @param array<string, mixed> $completedQuestsData
     * @return array<int, bool>
     */
    private function extractCompletedQuestIds(array $completedQuestsData): array
    {
        $quests = $completedQuestsData['quests'] ?? [];

        if (!is_array($quests)) {
            return [];
        }

        $completedIds = [];

        foreach ($quests as $quest) {
            if (!is_array($quest)) {
                continue;
            }

            $id = $quest['id'] ?? null;

            if (!is_int($id)) {
                continue;
            }

            $completedIds[$id] = true;
        }

        return $completedIds;
    }
}
