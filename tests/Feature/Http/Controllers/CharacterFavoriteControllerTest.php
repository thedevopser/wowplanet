<?php

declare(strict_types=1);

use App\Models\CharacterFavorite;

$session = [
    'blizzard_user_token' => 'fake-token',
    'bnet_user_id' => '111',
];

// ─── Authentication ─────────────────────────────────────────

test('unauthenticated user gets 401 on index', function (): void {
    $this->getJson('/api/character-favorites')->assertStatus(401);
});

test('unauthenticated user gets 401 on store', function (): void {
    $this->postJson('/api/character-favorites', [
        'realm_slug' => 'hyjal',
        'character_name' => 'thrall',
    ])->assertStatus(401);
});

test('unauthenticated user gets 401 on destroy', function (): void {
    $this->deleteJson('/api/character-favorites/hyjal/thrall')->assertStatus(401);
});

// ─── Index ──────────────────────────────────────────────────

test('index returns only the authenticated user favorites', function () use ($session): void {
    CharacterFavorite::factory()->create(['bnet_user_id' => '111', 'character_name' => 'thrall']);
    CharacterFavorite::factory()->create(['bnet_user_id' => '111', 'character_name' => 'jaina']);
    CharacterFavorite::factory()->create(['bnet_user_id' => '999', 'character_name' => 'arthas']);

    $response = $this->withSession($session)->getJson('/api/character-favorites');

    $response->assertOk();
    $response->assertJsonCount(2);
});

// ─── Store ──────────────────────────────────────────────────

test('store creates a favorite', function () use ($session): void {
    $response = $this->withSession($session)->postJson('/api/character-favorites', [
        'realm_slug' => 'Hyjal',
        'character_name' => 'Thrall',
    ]);

    $response->assertStatus(201);
    $response->assertJsonFragment(['realm_slug' => 'hyjal', 'character_name' => 'thrall']);

    expect(CharacterFavorite::query()->count())->toBe(1);
});

test('store validates required fields', function () use ($session): void {
    $this->withSession($session)
        ->postJson('/api/character-favorites', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['realm_slug', 'character_name']);
});

test('store returns 422 once the limit is reached', function () use ($session): void {
    foreach (['a', 'b', 'c'] as $name) {
        CharacterFavorite::factory()->create([
            'bnet_user_id' => '111',
            'realm_slug' => 'hyjal',
            'character_name' => $name,
        ]);
    }

    $response = $this->withSession($session)->postJson('/api/character-favorites', [
        'realm_slug' => 'hyjal',
        'character_name' => 'd',
    ]);

    $response->assertStatus(422);
    $response->assertJsonFragment(['error' => 'Favorite limit reached', 'max' => 3]);
});

// ─── Destroy ────────────────────────────────────────────────

test('destroy removes the favorite', function () use ($session): void {
    CharacterFavorite::factory()->create([
        'bnet_user_id' => '111',
        'realm_slug' => 'hyjal',
        'character_name' => 'thrall',
    ]);

    $this->withSession($session)
        ->deleteJson('/api/character-favorites/hyjal/thrall')
        ->assertStatus(204);

    expect(CharacterFavorite::query()->count())->toBe(0);
});

test('destroy cannot remove another user favorite', function () use ($session): void {
    CharacterFavorite::factory()->create([
        'bnet_user_id' => '999',
        'realm_slug' => 'hyjal',
        'character_name' => 'thrall',
    ]);

    $this->withSession($session)
        ->deleteJson('/api/character-favorites/hyjal/thrall')
        ->assertStatus(204);

    expect(CharacterFavorite::query()->count())->toBe(1);
});
