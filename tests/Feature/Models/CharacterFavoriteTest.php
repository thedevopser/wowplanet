<?php

declare(strict_types=1);

use App\Models\CharacterFavorite;

// ─── CharacterFavorite Model ────────────────────────────────

test('CharacterFavorite can be created with required fields', function (): void {
    $characterFavorite = CharacterFavorite::query()->create([
        'bnet_user_id' => '12345',
        'realm_slug' => 'hyjal',
        'character_name' => 'thrall',
    ]);

    $characterFavorite->refresh();

    expect($characterFavorite)->toBeInstanceOf(CharacterFavorite::class)
        ->and($characterFavorite->bnet_user_id)->toBe('12345')
        ->and($characterFavorite->realm_slug)->toBe('hyjal')
        ->and($characterFavorite->character_name)->toBe('thrall')
        ->and($characterFavorite->sort_order)->toBe(0);
});

test('CharacterFavorite enforces uniqueness per user and character', function (): void {
    CharacterFavorite::query()->create([
        'bnet_user_id' => '111',
        'realm_slug' => 'hyjal',
        'character_name' => 'thrall',
    ]);

    expect(fn (): CharacterFavorite => CharacterFavorite::query()->create([
        'bnet_user_id' => '111',
        'realm_slug' => 'hyjal',
        'character_name' => 'thrall',
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

test('CharacterFavorite factory creates valid instance', function (): void {
    $favorite = CharacterFavorite::factory()->create();

    expect($favorite)->toBeInstanceOf(CharacterFavorite::class)
        ->and($favorite->bnet_user_id)->toBeString()
        ->and($favorite->realm_slug)->toBeString()
        ->and($favorite->character_name)->toBeString()
        ->and($favorite->sort_order)->toBeInt();
});
