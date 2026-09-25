<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Models\CharacterTask;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CharacterTaskService
{
    /**
     * @return Collection<int, CharacterTask>
     */
    public function getTasksForUser(string $bnetUserId): Collection
    {
        return CharacterTask::query()
            ->where('bnet_user_id', $bnetUserId)
            ->orderBy('sort_order')->oldest()
            ->get();
    }

    /**
     * @param  array{realm_slug: string, character_name: string, name: string, reset_type: string}  $data
     */
    public function createTask(string $bnetUserId, array $data): CharacterTask
    {
        return CharacterTask::query()->create([
            'bnet_user_id' => $bnetUserId,
            'realm_slug' => $data['realm_slug'],
            'character_name' => $data['character_name'],
            'name' => $data['name'],
            'reset_type' => $data['reset_type'],
        ]);
    }

    public function toggleTask(int $taskId, string $bnetUserId): CharacterTask
    {
        $characterTask = $this->findOwnedTask($taskId, $bnetUserId);

        $characterTask->update([
            'is_completed' => ! $characterTask->is_completed,
            'completed_at' => $characterTask->is_completed ? null : now(),
        ]);

        return $characterTask->refresh();
    }

    public function deleteTask(int $taskId, string $bnetUserId): void
    {
        $characterTask = $this->findOwnedTask($taskId, $bnetUserId);
        $characterTask->delete();
    }

    public function resetTask(int $taskId, string $bnetUserId): CharacterTask
    {
        $characterTask = $this->findOwnedTask($taskId, $bnetUserId);

        $characterTask->update([
            'is_completed' => false,
            'completed_at' => null,
        ]);

        return $characterTask->refresh();
    }

    private function findOwnedTask(int $taskId, string $bnetUserId): CharacterTask
    {
        /** @var CharacterTask $characterTask */
        $characterTask = CharacterTask::query()->findOrFail($taskId);

        throw_if($characterTask->bnet_user_id !== $bnetUserId, AccessDeniedHttpException::class, 'You do not own this task.');

        return $characterTask;
    }
}
