<?php

declare(strict_types=1);

use App\Application\Services\CharacterFavoriteService;
use App\Domain\Exceptions\FavoriteLimitReachedException;
use App\Models\CharacterFavorite;

beforeEach(function (): void {
    $this->service = new CharacterFavoriteService;
});

// ─── Add ────────────────────────────────────────────────────

test('addFavorite creates a favorite', function (): void {
    $favorite = $this->service->addFavorite('111', 'hyjal', 'thrall');

    expect($favorite->bnet_user_id)->toBe('111')
        ->and($favorite->realm_slug)->toBe('hyjal')
        ->and($favorite->character_name)->toBe('thrall')
        ->and($favorite->sort_order)->toBe(0);
});

test('addFavorite normalizes realm and name to lowercase', function (): void {
    $favorite = $this->service->addFavorite('111', 'Hyjal', 'Thrall');

    expect($favorite->realm_slug)->toBe('hyjal')
        ->and($favorite->character_name)->toBe('thrall');
});

test('addFavorite is idempotent', function (): void {
    $first = $this->service->addFavorite('111', 'hyjal', 'thrall');
    $second = $this->service->addFavorite('111', 'HYJAL', 'THRALL');

    expect($second->id)->toBe($first->id)
        ->and(CharacterFavorite::query()->count())->toBe(1);
});

test('addFavorite increments sort_order', function (): void {
    $this->service->addFavorite('111', 'hyjal', 'thrall');
    $second = $this->service->addFavorite('111', 'hyjal', 'jaina');

    expect($second->sort_order)->toBe(1);
});

test('addFavorite throws once the limit is reached', function (): void {
    $this->service->addFavorite('111', 'hyjal', 'a');
    $this->service->addFavorite('111', 'hyjal', 'b');
    $this->service->addFavorite('111', 'hyjal', 'c');

    expect(fn () => $this->service->addFavorite('111', 'hyjal', 'd'))
        ->toThrow(FavoriteLimitReachedException::class);

    expect(CharacterFavorite::query()->count())->toBe(CharacterFavoriteService::MAX_FAVORITES);
});

test('addFavorite still returns an existing favorite when the limit is reached', function (): void {
    $this->service->addFavorite('111', 'hyjal', 'a');
    $this->service->addFavorite('111', 'hyjal', 'b');

    $expected = $this->service->addFavorite('111', 'hyjal', 'c');

    expect($this->service->addFavorite('111', 'hyjal', 'c')->id)->toBe($expected->id);
});

test('the limit is per user', function (): void {
    $this->service->addFavorite('111', 'hyjal', 'a');
    $this->service->addFavorite('111', 'hyjal', 'b');
    $this->service->addFavorite('111', 'hyjal', 'c');

    $other = $this->service->addFavorite('999', 'hyjal', 'd');

    expect($other->sort_order)->toBe(0);
});

// ─── Remove ─────────────────────────────────────────────────

test('removeFavorite deletes the favorite', function (): void {
    $this->service->addFavorite('111', 'hyjal', 'thrall');
    $this->service->removeFavorite('111', 'HYJAL', 'THRALL');

    expect(CharacterFavorite::query()->count())->toBe(0);
});

test('removeFavorite leaves other users untouched', function (): void {
    $this->service->addFavorite('111', 'hyjal', 'thrall');
    $this->service->addFavorite('999', 'hyjal', 'thrall');

    $this->service->removeFavorite('111', 'hyjal', 'thrall');

    expect(CharacterFavorite::query()->where('bnet_user_id', '999')->count())->toBe(1);
});

test('removeFavorite is silent when nothing matches', function (): void {
    $this->service->removeFavorite('111', 'hyjal', 'ghost');

    expect(CharacterFavorite::query()->count())->toBe(0);
});

// ─── List ───────────────────────────────────────────────────

test('getFavoritesForUser returns only that user favorites in order', function (): void {
    $this->service->addFavorite('111', 'hyjal', 'b');
    $this->service->addFavorite('111', 'hyjal', 'a');
    $this->service->addFavorite('999', 'hyjal', 'z');

    $favorites = $this->service->getFavoritesForUser('111');

    expect($favorites)->toHaveCount(2)
        ->and($favorites->pluck('character_name')->all())->toBe(['b', 'a']);
});
