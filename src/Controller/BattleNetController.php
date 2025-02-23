<?php

declare(strict_types = 1);

namespace App\Controller;

use App\Services\BattleNetGameDataApi;
use App\Services\BattleNetProfileDataApi;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BattleNetController extends AbstractController
{
    private BattleNetProfileDataApi $profileDataApi;

    private BattleNetGameDataApi $gameDataApi;

    public function __construct(BattleNetProfileDataApi $profileDataApi, BattleNetGameDataApi $gameDataApi)
    {
        $this->profileDataApi = $profileDataApi;
        $this->gameDataApi = $gameDataApi;
    }

    #[Route('/api/connect/battlenet', name: 'blizzard_auth')]
    public function redirectToBlizzard(Request $request): RedirectResponse
    {
        $redirectUri = $this->generateUrl('blizzard_auth_callback', [], 0);
        $authUrl = $this->profileDataApi->getAuthorizationUrl($redirectUri, bin2hex(random_bytes(10)));

        $referer = $request->headers->get('referer', $this->generateUrl('app_homepage'));
        $request->getSession()->set('blizzard_redirect_after_login', $referer);

        return new RedirectResponse($authUrl);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/api/connect/battlenet/check', name: 'blizzard_auth_callback')]
    public function handleOAuthCallback(Request $request): RedirectResponse
    {
        $code = $request->query->get('code');
        $redirectUri = $this->generateUrl('blizzard_auth_callback', [], 0);

        if (!$code) {
            return $this->redirectToRoute('blizzard_auth');
        }

        $this->profileDataApi->exchangeAuthorizationCode($code, $redirectUri);

        $redirectUrl = $request->getSession()->get('blizzard_redirect_after_login') ?? $this->generateUrl('app_homepage');
        $request->getSession()->remove('blizzard_redirect_after_login');

        if (!is_string($redirectUrl)) {
            $redirectUrl = $this->generateUrl('app_homepage');
        }

        return new RedirectResponse($redirectUrl);
    }

    #[Route('/blizzard/user-characters', name: 'blizzard_user_characters')]
    public function getUserCharacters(Request $request): Response
    {
        if (!$this->profileDataApi->isAuthenticated()) {
            return $this->redirectToBlizzard($request);
        }

        return new JsonResponse($this->profileDataApi->getUserCharacters());
    }

    #[Route('/blizzard/character-details/{realmSlug}/{characterName}', name: 'blizzard_character_details')]
    public function getCharacterDetails(Request $request, string $realmSlug, string $characterName): Response
    {
        if (!$this->profileDataApi->isAuthenticated()) {
            return $this->redirectToBlizzard($request);
        }

        return new JsonResponse($this->profileDataApi->getCharacterDetails($realmSlug, $characterName));
    }

    #[Route('/blizzard/realms', name: 'blizzard_realms')]
    public function getRealms(): JsonResponse
    {
        return new JsonResponse($this->gameDataApi->getRealms());
    }

    #[Route('/blizzard/areas', name: 'blizzard_zones')]
    public function getZones(): JsonResponse
    {
        return new JsonResponse($this->gameDataApi->getQuestAreas());
    }

    #[Route('/blizzard/quests/{questId}', name: 'blizzard_quests')]
    public function getQuests(int $questId): JsonResponse
    {
        return new JsonResponse($this->gameDataApi->getQuestDetails($questId));
    }

    #[Route('/blizzard/quests/area/{zoneId}', name: 'blizzard_zone_quests')]
    public function getQuestsByZone(int $zoneId): JsonResponse
    {
        return new JsonResponse($this->gameDataApi->getQuestsByZone($zoneId));
    }
}
