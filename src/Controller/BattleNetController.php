<?php

declare(strict_types = 1);

namespace App\Controller;

use App\Services\BattleNetProfileDataApi;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class BattleNetController extends AbstractController
{
    public function __construct(private readonly BattleNetProfileDataApi $profileDataApi)
    {
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
}
