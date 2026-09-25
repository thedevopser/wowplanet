<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\CharacterSeoService;
use App\Application\Services\PvpLeaderboardService;
use App\Application\Services\PvpProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PvpController extends Controller
{
    public function __construct(
        private readonly PvpProfileService $pvpProfileService,
        private readonly PvpLeaderboardService $pvpLeaderboardService,
        private readonly CharacterSeoService $characterSeoService,
    ) {}

    /**
     * PvP d'un personnage, chargé à la demande par l'onglet du profil.
     */
    public function show(string $realm, string $name): JsonResponse
    {
        $realm = mb_strtolower($realm);
        $name = mb_strtolower($name);

        try {
            $pvp = $this->pvpProfileService->getForCharacter($realm, $name);
        } catch (\Throwable $throwable) {
            // Un onglet PvP en échec ne doit jamais casser la fiche personnage.
            Log::warning('Failed to fetch PvP profile', [
                'realm' => $realm,
                'name' => $name,
                'exception' => $throwable->getMessage(),
            ]);

            $pvp = null;
        }

        return response()->json(['pvp' => $pvp]);
    }

    /**
     * Classements officiels de la saison, servis en direct par l'API Blizzard.
     */
    public function leaderboard(Request $request, ?string $bracket = null): InertiaResponse
    {
        $search = $request->query('search');
        $search = is_string($search) && trim($search) !== '' ? trim($search) : null;

        $result = $this->pvpLeaderboardService->leaderboard(
            $bracket ?? PvpLeaderboardService::DEFAULT_BRACKET,
            max(1, (int) $request->query('page', '1')),
            $search,
        );

        return Inertia::render('PvpLeaderboardPage', array_merge($result, [
            'groups' => $this->pvpLeaderboardService->availableBrackets(),
            'search' => $search,
            'meta' => $this->characterSeoService->getStaticPageMeta('classements-pvp'),
        ]));
    }
}
