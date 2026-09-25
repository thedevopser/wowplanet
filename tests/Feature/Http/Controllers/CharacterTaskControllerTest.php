<?php

declare(strict_types=1);

use App\Models\CharacterTask;

// ─── Authentication ─────────────────────────────────────────

test('unauthenticated user gets 401 on index', function (): void {
    $response = $this->getJson('/api/character-tasks');
    $response->assertStatus(401);
});

test('unauthenticated user gets 401 on store', function (): void {
    $response = $this->postJson('/api/character-tasks', [
        'realm_slug' => 'hyjal',
        'character_name' => 'thrall',
        'name' => 'Test',
        'reset_type' => 'daily',
    ]);
    $response->assertStatus(401);
});

// ─── Index ──────────────────────────────────────────────────

test('index returns all tasks for authenticated user', function (): void {
    CharacterTask::factory()->create(['bnet_user_id' => '111', 'name' => 'Task A']);
    CharacterTask::factory()->create(['bnet_user_id' => '111', 'name' => 'Task B']);
    CharacterTask::factory()->create(['bnet_user_id' => '999', 'name' => 'Other']);

    $response = $this->withSession([
        'blizzard_user_token' => 'fake-token',
        'bnet_user_id' => '111',
    ])->getJson('/api/character-tasks');

    $response->assertOk();
    $response->assertJsonCount(2);
});

// ─── Store ──────────────────────────────────────────────────

test('store creates a new task', function (): void {
    $response = $this->withSession([
        'blizzard_user_token' => 'fake-token',
        'bnet_user_id' => '111',
    ])->postJson('/api/character-tasks', [
        'realm_slug' => 'hyjal',
        'character_name' => 'thrall',
        'name' => 'Donjon mythique',
        'reset_type' => 'weekly',
    ]);

    $response->assertStatus(201);
    $response->assertJsonFragment(['name' => 'Donjon mythique', 'reset_type' => 'weekly']);

    expect(CharacterTask::query()->count())->toBe(1);
});

test('store validates required fields', function (): void {
    $response = $this->withSession([
        'blizzard_user_token' => 'fake-token',
        'bnet_user_id' => '111',
    ])->postJson('/api/character-tasks', []);

    $response->assertStatus(422);
});

test('store validates reset_type is daily, weekly or monthly', function (): void {
    $response = $this->withSession([
        'blizzard_user_token' => 'fake-token',
        'bnet_user_id' => '111',
    ])->postJson('/api/character-tasks', [
        'realm_slug' => 'hyjal',
        'character_name' => 'thrall',
        'name' => 'Test',
        'reset_type' => 'yearly',
    ]);

    $response->assertStatus(422);
});

// ─── Update (toggle) ────────────────────────────────────────

test('update toggles task completion', function (): void {
    $task = CharacterTask::factory()->create([
        'bnet_user_id' => '111',
        'is_completed' => false,
    ]);

    $response = $this->withSession([
        'blizzard_user_token' => 'fake-token',
        'bnet_user_id' => '111',
    ])->putJson('/api/character-tasks/'.$task->id);

    $response->assertOk();
    $response->assertJsonFragment(['is_completed' => true]);
});

test('update returns 403 for wrong user', function (): void {
    $task = CharacterTask::factory()->create(['bnet_user_id' => '111']);

    $response = $this->withSession([
        'blizzard_user_token' => 'fake-token',
        'bnet_user_id' => '999',
    ])->putJson('/api/character-tasks/'.$task->id);

    $response->assertStatus(403);
});

// ─── Destroy ────────────────────────────────────────────────

test('destroy deletes the task', function (): void {
    $task = CharacterTask::factory()->create(['bnet_user_id' => '111']);

    $response = $this->withSession([
        'blizzard_user_token' => 'fake-token',
        'bnet_user_id' => '111',
    ])->deleteJson('/api/character-tasks/'.$task->id);

    $response->assertStatus(204);

    expect(CharacterTask::query()->count())->toBe(0);
});

test('destroy returns 403 for wrong user', function (): void {
    $task = CharacterTask::factory()->create(['bnet_user_id' => '111']);

    $response = $this->withSession([
        'blizzard_user_token' => 'fake-token',
        'bnet_user_id' => '999',
    ])->deleteJson('/api/character-tasks/'.$task->id);

    $response->assertStatus(403);
});
