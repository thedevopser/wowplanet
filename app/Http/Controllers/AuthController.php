<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public const string INTENDED_URL_SESSION_KEY = 'url.intended';

    private const string DEFAULT_LANDING_PATH = '/mon-compte';

    private const string INTERRUPTED_LOGIN = 'La connexion Battle.net a été interrompue. Relancez-la depuis le bouton « Se connecter ».';

    private const string REFUSED_LOGIN = 'Battle.net n’a pas validé la connexion. Réessayez dans un instant.';

    private const string FAILED_LOGIN = 'Une erreur est survenue pendant la connexion à Battle.net. Réessayez dans un instant.';

    private readonly string $clientId;

    private readonly string $clientSecret;

    private readonly string $redirectUri;

    private readonly string $region;

    public function __construct()
    {
        /** @var string $clientId */
        $clientId = config('services.blizzard.client_id', '');
        $this->clientId = $clientId;

        /** @var string $clientSecret */
        $clientSecret = config('services.blizzard.client_secret', '');
        $this->clientSecret = $clientSecret;

        /** @var string $redirectUri */
        $redirectUri = config('services.blizzard.redirect_uri', '');
        $this->redirectUri = $redirectUri;

        /** @var string $region */
        $region = config('services.blizzard.region', 'eu');
        $this->region = $region;
    }

    public function redirect(): RedirectResponse
    {
        $state = Str::random(40);
        Session::put('blizzard_oauth_state', $state);

        $query = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'wow.profile',
            'state' => $state,
        ]);

        return redirect(sprintf('https://%s.battle.net/oauth/authorize?%s', $this->region, $query));
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            /** @var string $code */
            $code = $request->get('code', '');
            /** @var string $state */
            $state = $request->get('state', '');
            /** @var string $expectedState */
            $expectedState = Session::pull('blizzard_oauth_state', '');

            if ($code === '' || $state === '' || $state !== $expectedState) {
                Log::warning('Blizzard OAuth: state mismatch or missing params', [
                    'has_code' => $code !== '',
                    'has_state' => $state !== '',
                    'state_matches' => $state === $expectedState,
                ]);

                return redirect('/')->with('error', self::INTERRUPTED_LOGIN);
            }

            $response = Http::asForm()
                ->timeout(10)
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->post(sprintf('https://%s.battle.net/oauth/token', $this->region), [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $this->redirectUri,
                ]);

            if ($response->failed()) {
                Log::error('Blizzard OAuth: token exchange failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return redirect('/')->with('error', self::REFUSED_LOGIN);
            }

            $tokenData = $response->json();
            $tokenData = is_array($tokenData) ? $tokenData : [];
            $accessToken = $tokenData['access_token'] ?? null;

            if (! is_string($accessToken)) {
                Log::error('Blizzard OAuth: missing access_token in response', [
                    'response_keys' => array_keys($tokenData),
                ]);

                return redirect('/')->with('error', self::REFUSED_LOGIN);
            }

            Session::put('blizzard_user_token', $accessToken);
            $this->fetchAndStoreUserInfo($accessToken);

            return redirect($this->pullLandingPath());
        } catch (\Throwable $throwable) {
            Log::error('Blizzard OAuth: unexpected error during callback', [
                'message' => $throwable->getMessage(),
                'trace' => $throwable->getTraceAsString(),
            ]);

            return redirect('/')->with('error', self::FAILED_LOGIN);
        }
    }

    /**
     * Only a path of this site is followed: the remembered URL comes from the request,
     * and following an absolute or protocol-relative one would make an open redirect.
     */
    private function pullLandingPath(): string
    {
        $intended = Session::pull(self::INTENDED_URL_SESSION_KEY);

        if (! is_string($intended) || preg_match('#^/(?![/\\\\])#', $intended) !== 1) {
            return self::DEFAULT_LANDING_PATH;
        }

        return $intended;
    }

    private function fetchAndStoreUserInfo(string $accessToken): void
    {
        try {
            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->get(sprintf('https://%s.battle.net/oauth/userinfo', $this->region));

            if (! $response->ok()) {
                return;
            }

            $userInfo = $response->json();
            $userInfo = is_array($userInfo) ? $userInfo : [];
            $sub = is_string($userInfo['sub'] ?? null) ? $userInfo['sub'] : '';
            $battletag = is_string($userInfo['battletag'] ?? null) ? $userInfo['battletag'] : '';

            Session::put('bnet_user_id', $sub);
            Session::put('bnet_battletag', $battletag);

            Log::info('Bnet user logged in', ['sub' => $sub, 'battletag' => $battletag]);

            /** @var string $adminId */
            $adminId = config('services.blizzard.admin_bnet_id', '');
            if ($adminId !== '' && $sub === $adminId) {
                Session::put('is_admin', true);
            }
        } catch (\Throwable $throwable) {
            Log::warning('Failed to fetch Bnet userinfo', ['message' => $throwable->getMessage()]);
        }
    }
}
