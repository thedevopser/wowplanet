<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\AccountTodoService;
use App\Http\Controllers\Concerns\ResolvesBnetUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * L'API de la page « À faire » du compte : la liste, ou l'avancement des calculs qui la préparent.
 */
class AccountTodoController extends Controller
{
    use ResolvesBnetUser;

    public function __construct(
        private readonly AccountTodoService $accountTodoService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $battleTag = $request->session()->get('bnet_battletag');
        $result = $this->accountTodoService->getOrCompute(is_string($battleTag) ? $battleTag : '');

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

        $this->accountTodoService->refresh();

        return response()->json(['success' => true]);
    }
}
