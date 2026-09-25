<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\CharacterTaskService;
use App\Http\Controllers\Concerns\ResolvesBnetUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CharacterTaskController extends Controller
{
    use ResolvesBnetUser;

    public function __construct(
        private readonly CharacterTaskService $characterTaskService,
    ) {}

    public function index(): JsonResponse
    {
        $bnetUserId = $this->getAuthenticatedUserId();
        if ($bnetUserId === null) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        $tasks = $this->characterTaskService->getTasksForUser($bnetUserId);

        return response()->json($tasks);
    }

    public function store(Request $request): JsonResponse
    {
        $bnetUserId = $this->getAuthenticatedUserId();
        if ($bnetUserId === null) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        $request->validate([
            'realm_slug' => ['required', 'string'],
            'character_name' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'reset_type' => ['required', 'in:daily,weekly,monthly'],
        ]);

        $characterTask = $this->characterTaskService->createTask($bnetUserId, [
            'realm_slug' => $request->string('realm_slug')->value(),
            'character_name' => $request->string('character_name')->value(),
            'name' => $request->string('name')->value(),
            'reset_type' => $request->string('reset_type')->value(),
        ]);

        return response()->json($characterTask->refresh(), 201);
    }

    public function update(int $id): JsonResponse
    {
        $bnetUserId = $this->getAuthenticatedUserId();
        if ($bnetUserId === null) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        try {
            $task = $this->characterTaskService->toggleTask($id, $bnetUserId);

            return response()->json($task);
        } catch (AccessDeniedHttpException) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $bnetUserId = $this->getAuthenticatedUserId();
        if ($bnetUserId === null) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        try {
            $this->characterTaskService->deleteTask($id, $bnetUserId);

            return response()->json(null, 204);
        } catch (AccessDeniedHttpException) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
    }
}
