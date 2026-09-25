<?php

declare(strict_types=1);

use App\Application\Services\CharacterTaskService;
use App\Models\CharacterTask;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

// ─── getTasksForUser ────────────────────────────────────────

test('getTasksForUser returns all tasks for the given user', function (): void {
    CharacterTask::factory()->create(['bnet_user_id' => '111', 'name' => 'Task A']);
    CharacterTask::factory()->create(['bnet_user_id' => '111', 'name' => 'Task B']);
    CharacterTask::factory()->create(['bnet_user_id' => '999', 'name' => 'Other user']);

    $characterTaskService = resolve(CharacterTaskService::class);
    $tasks = $characterTaskService->getTasksForUser('111');

    expect($tasks)->toHaveCount(2)
        ->and($tasks->pluck('name')->toArray())->toContain('Task A', 'Task B');
});

test('getTasksForUser returns empty collection when user has no tasks', function (): void {
    $characterTaskService = resolve(CharacterTaskService::class);
    $tasks = $characterTaskService->getTasksForUser('nonexistent');

    expect($tasks)->toHaveCount(0);
});

// ─── createTask ─────────────────────────────────────────────

test('createTask creates a task for the user', function (): void {
    $characterTaskService = resolve(CharacterTaskService::class);
    $characterTask = $characterTaskService->createTask('111', [
        'realm_slug' => 'hyjal',
        'character_name' => 'thrall',
        'name' => 'Donjon mythique',
        'reset_type' => 'weekly',
    ]);

    $characterTask->refresh();

    expect($characterTask)->toBeInstanceOf(CharacterTask::class)
        ->and($characterTask->bnet_user_id)->toBe('111')
        ->and($characterTask->realm_slug)->toBe('hyjal')
        ->and($characterTask->character_name)->toBe('thrall')
        ->and($characterTask->name)->toBe('Donjon mythique')
        ->and($characterTask->reset_type)->toBe('weekly')
        ->and($characterTask->is_completed)->toBeFalse();

    expect(CharacterTask::query()->count())->toBe(1);
});

// ─── toggleTask ─────────────────────────────────────────────

test('toggleTask marks an incomplete task as completed', function (): void {
    $task = CharacterTask::factory()->create([
        'bnet_user_id' => '111',
        'is_completed' => false,
        'completed_at' => null,
    ]);

    $characterTaskService = resolve(CharacterTaskService::class);
    $characterTask = $characterTaskService->toggleTask($task->id, '111');

    expect($characterTask->is_completed)->toBeTrue()
        ->and($characterTask->completed_at)->not->toBeNull();
});

test('toggleTask marks a completed task as incomplete', function (): void {
    $task = CharacterTask::factory()->create([
        'bnet_user_id' => '111',
        'is_completed' => true,
        'completed_at' => now(),
    ]);

    $characterTaskService = resolve(CharacterTaskService::class);
    $characterTask = $characterTaskService->toggleTask($task->id, '111');

    expect($characterTask->is_completed)->toBeFalse()
        ->and($characterTask->completed_at)->toBeNull();
});

test('toggleTask rejects access for wrong user', function (): void {
    $task = CharacterTask::factory()->create(['bnet_user_id' => '111']);

    $characterTaskService = resolve(CharacterTaskService::class);
    $characterTaskService->toggleTask($task->id, '999');
})->throws(AccessDeniedHttpException::class);

// ─── deleteTask ─────────────────────────────────────────────

test('deleteTask removes the task', function (): void {
    $task = CharacterTask::factory()->create(['bnet_user_id' => '111']);

    $characterTaskService = resolve(CharacterTaskService::class);
    $characterTaskService->deleteTask($task->id, '111');

    expect(CharacterTask::query()->count())->toBe(0);
});

test('deleteTask rejects access for wrong user', function (): void {
    $task = CharacterTask::factory()->create(['bnet_user_id' => '111']);

    $characterTaskService = resolve(CharacterTaskService::class);
    $characterTaskService->deleteTask($task->id, '999');
})->throws(AccessDeniedHttpException::class);

// ─── resetTask ──────────────────────────────────────────────

test('resetTask sets is_completed to false and clears completed_at', function (): void {
    $task = CharacterTask::factory()->create([
        'bnet_user_id' => '111',
        'is_completed' => true,
        'completed_at' => now(),
    ]);

    $characterTaskService = resolve(CharacterTaskService::class);
    $characterTask = $characterTaskService->resetTask($task->id, '111');

    expect($characterTask->is_completed)->toBeFalse()
        ->and($characterTask->completed_at)->toBeNull();
});
