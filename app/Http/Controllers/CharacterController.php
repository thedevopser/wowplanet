<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\DTOs\CharacterProfileDTO;
use App\Application\Services\CharacterProfileService;
use App\Application\Services\CharacterSeoService;
use App\Application\Services\CrossCharacterService;
use App\Application\Services\UserCharacterService;
use App\Http\Character\CharacterSheetView;
use App\Http\Character\UnknownCharacterSheetSegment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class CharacterController extends Controller
{
    public function __construct(
        private readonly CharacterProfileService $characterProfileService,
        private readonly CrossCharacterService $crossCharacterService,
        private readonly UserCharacterService $userCharacterService,
        private readonly CharacterSeoService $characterSeoService,
    ) {}

    /**
     * Page personnage rendue via Inertia (SSR-compatible). Les segments facultatifs
     * désignent une section et un sous-onglet ; l'URL de base reste la seule canonique.
     */
    public function page(Request $request, string $realm, string $name, ?string $section = null, ?string $sub = null): InertiaResponse|Response
    {
        $segments = array_values(array_filter([$realm, $name, $section, $sub], is_string(...)));
        $lowercase = array_map(mb_strtolower(...), $segments);

        if ($segments !== $lowercase) {
            return redirect('/character/'.implode('/', $lowercase), 301);
        }

        try {
            $view = CharacterSheetView::fromSegments($section, $sub);
        } catch (UnknownCharacterSheetSegment) {
            return $this->renderNotFound($request, $realm, $name);
        }

        try {
            $profile = $this->characterProfileService->getProfile($realm, $name);
        } catch (\Exception $exception) {
            Log::error('Failed to render character page', [
                'realm' => $realm,
                'name' => $name,
                'exception' => $exception->getMessage(),
            ]);

            return $this->renderNotFound($request, $realm, $name);
        }

        $isOwner = $this->isOwnedByViewer($realm, $name);
        $this->mergeIntoCrossCharacterData($profile, $isOwner);

        return Inertia::render('CharacterPage', [
            'character' => $profile,
            'realm' => $realm,
            'name' => $name,
            'section' => $view->section?->value,
            'sub' => $view->sub,
            'meta' => $this->characterSeoService->buildCharacterMeta($profile, $realm, $name),
            'isOwner' => $isOwner,
        ]);
    }

    public function show(string $realm, string $name): JsonResponse
    {
        try {
            $profile = $this->characterProfileService->getProfile($realm, $name);
            $this->mergeIntoCrossCharacterData($profile, $this->isOwnedByViewer($realm, $name));

            return response()->json($profile);
        } catch (\Exception $exception) {
            Log::error('Failed to fetch character profile', [
                'realm' => $realm,
                'name' => $name,
                'exception' => $exception->getMessage(),
            ]);

            return response()->json([
                'error' => 'Character not found or Blizzard API error',
            ], 404);
        }
    }

    private function isOwnedByViewer(string $realm, string $name): bool
    {
        return $this->userCharacterService->isAuthenticated()
            && $this->userCharacterService->ownsCharacter($realm, $name);
    }

    /**
     * Only a character of the account may feed its cross-character data: merging
     * another player's sheet would credit the account with progress it never made.
     */
    private function mergeIntoCrossCharacterData(CharacterProfileDTO $characterProfileDTO, bool $isOwner): void
    {
        if (! $isOwner) {
            return;
        }

        try {
            $this->crossCharacterService->mergeCurrentCharacter($characterProfileDTO);
        } catch (\Exception $exception) {
            Log::debug('Cross-character piggyback failed', ['exception' => $exception->getMessage()]);
        }
    }

    private function renderNotFound(Request $request, string $realm, string $name): Response
    {
        return Inertia::render('CharacterPage', [
            'character' => null,
            'realm' => $realm,
            'name' => $name,
            'section' => null,
            'sub' => null,
            'meta' => $this->characterSeoService->buildNotFoundCharacterMeta($realm, $name),
            'isOwner' => false,
        ])->toResponse($request)->setStatusCode(404);
    }
}
