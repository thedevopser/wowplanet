<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\WeeklyActivityService;
use App\Http\Controllers\Concerns\ResolvesBnetUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * L'API de la page « Semaine » du compte : ce qu'il reste à faire avant la remise à zéro, ou le calcul en cours.
 */
class AccountWeekController extends Controller
{
    use ResolvesBnetUser;

    public function __construct(
        private readonly WeeklyActivityService $weeklyActivityService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $battleTag = $request->session()->get('bnet_battletag');
        $result = $this->weeklyActivityService->getOrCompute(is_string($battleTag) ? $battleTag : '');

        return match ($result['status']) {
            'unauthenticated' => response()->json(['error' => 'Not authenticated'], 401),
            'failed' => response()->json($result, 502),
            default => response()->json($result),
        };
    }

    public function refresh(): JsonResponse
    {
        if ($this->getAuthenticatedUserId() === null) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        $this->weeklyActivityService->refresh();

        return response()->json(['success' => true]);
    }
}
