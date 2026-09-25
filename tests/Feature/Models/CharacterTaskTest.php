<?php

declare(strict_types=1);

use App\Models\CharacterTask;
use Carbon\Carbon;

// ─── CharacterTask Model ────────────────────────────────────

test('CharacterTask can be created with required fields', function (): void {
    $characterTask = CharacterTask::query()->create([
        'bnet_user_id' => '12345',
        'realm_slug' => 'hyjal',
        'character_name' => 'thrall',
        'name' => 'Donjon mythique hebdo',
        'reset_type' => 'weekly',
    ]);

    $characterTask->refresh();

    expect($characterTask)->toBeInstanceOf(CharacterTask::class)
        ->and($characterTask->bnet_user_id)->toBe('12345')
        ->and($characterTask->realm_slug)->toBe('hyjal')
        ->and($characterTask->character_name)->toBe('thrall')
        ->and($characterTask->name)->toBe('Donjon mythique hebdo')
        ->and($characterTask->reset_type)->toBe('weekly')
        ->and($characterTask->is_completed)->toBeFalse()
        ->and($characterTask->completed_at)->toBeNull()
        ->and($characterTask->sort_order)->toBe(0);
});

test('CharacterTask casts is_completed to boolean', function (): void {
    $characterTask = CharacterTask::query()->create([
        'bnet_user_id' => '12345',
        'realm_slug' => 'hyjal',
        'character_name' => 'thrall',
        'name' => 'Quête journalière',
        'reset_type' => 'daily',
        'is_completed' => true,
        'completed_at' => '2026-03-19 10:00:00',
    ]);

    expect($characterTask->is_completed)->toBeTrue()
        ->and($characterTask->is_completed)->toBeBool()
        ->and($characterTask->completed_at)->toBeInstanceOf(Carbon::class);
});

test('CharacterTask can be queried by user and character', function (): void {
    CharacterTask::query()->create([
        'bnet_user_id' => '111',
        'realm_slug' => 'hyjal',
        'character_name' => 'thrall',
        'name' => 'Task A',
        'reset_type' => 'daily',
    ]);
    CharacterTask::query()->create([
        'bnet_user_id' => '111',
        'realm_slug' => 'hyjal',
        'character_name' => 'thrall',
        'name' => 'Task B',
        'reset_type' => 'weekly',
    ]);
    CharacterTask::query()->create([
        'bnet_user_id' => '111',
        'realm_slug' => 'dalaran',
        'character_name' => 'jaina',
        'name' => 'Task C',
        'reset_type' => 'daily',
    ]);
    CharacterTask::query()->create([
        'bnet_user_id' => '999',
        'realm_slug' => 'hyjal',
        'character_name' => 'thrall',
        'name' => 'Other user task',
        'reset_type' => 'daily',
    ]);

    $userTasks = CharacterTask::query()->where('bnet_user_id', '111')->get();
    expect($userTasks)->toHaveCount(3);

    $thrallTasks = CharacterTask::query()->where('bnet_user_id', '111')
        ->where('realm_slug', 'hyjal')
        ->where('character_name', 'thrall')
        ->get();
    expect($thrallTasks)->toHaveCount(2);
});

test('CharacterTask factory creates valid instance', function (): void {
    $task = CharacterTask::factory()->create();

    expect($task)->toBeInstanceOf(CharacterTask::class)
        ->and($task->bnet_user_id)->toBeString()
        ->and($task->realm_slug)->toBeString()
        ->and($task->character_name)->toBeString()
        ->and($task->name)->toBeString()
        ->and($task->reset_type)->toBeIn(['daily', 'weekly', 'monthly'])
        ->and($task->is_completed)->toBeBool();
});
