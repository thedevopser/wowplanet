<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\CharacterFavoriteService;
use App\Domain\Exceptions\FavoriteLimitReachedException;
use App\Http\Controllers\Concerns\ResolvesBnetUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CharacterFavoriteController extends Controller
{
    use ResolvesBnetUser;

    public function __construct(
        private readonly CharacterFavoriteService $characterFavoriteService,
    ) {}

    public function index(): JsonResponse
    {
        $bnetUserId = $this->getAuthenticatedUserId();
        if ($bnetUserId === null) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        return response()->json($this->characterFavoriteService->getFavoritesForUser($bnetUserId));
    }

    public function store(Request $request): JsonResponse
    {
        $bnetUserId = $this->getAuthenticatedUserId();
        if ($bnetUserId === null) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        /** @var array<string, string> $validated */
        $validated = $request->validate([
            'realm_slug' => ['required', 'string'],
            'character_name' => ['required', 'string'],
        ]);

        try {
            $favorite = $this->characterFavoriteService->addFavorite(
                $bnetUserId,
                $validated['realm_slug'],
                $validated['character_name'],
            );
        } catch (FavoriteLimitReachedException) {
            return response()->json([
                'error' => 'Favorite limit reached',
                'max' => CharacterFavoriteService::MAX_FAVORITES,
            ], 422);
        }

        return response()->json($favorite, 201);
    }

    public function destroy(string $realm, string $name): JsonResponse
    {
        $bnetUserId = $this->getAuthenticatedUserId();
        if ($bnetUserId === null) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        $this->characterFavoriteService->removeFavorite($bnetUserId, $realm, $name);

        return response()->json(null, 204);
    }
}
