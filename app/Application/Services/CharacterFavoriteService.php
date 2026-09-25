<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Exceptions\FavoriteLimitReachedException;
use App\Models\CharacterFavorite;
use Illuminate\Support\Collection;

class CharacterFavoriteService
{
    public const MAX_FAVORITES = 3;

    /**
     * @return Collection<int, CharacterFavorite>
     */
    public function getFavoritesForUser(string $bnetUserId): Collection
    {
        return CharacterFavorite::query()
            ->where('bnet_user_id', $bnetUserId)
            ->orderBy('sort_order')->oldest()
            ->get();
    }

    public function addFavorite(string $bnetUserId, string $realmSlug, string $characterName): CharacterFavorite
    {
        $key = [
            'bnet_user_id' => $bnetUserId,
            'realm_slug' => mb_strtolower($realmSlug),
            'character_name' => mb_strtolower($characterName),
        ];

        /** @var CharacterFavorite|null $existing */
        $existing = CharacterFavorite::query()->where($key)->first();

        if ($existing instanceof CharacterFavorite) {
            return $existing;
        }

        $count = CharacterFavorite::query()->where('bnet_user_id', $bnetUserId)->count();

        throw_if($count >= self::MAX_FAVORITES, FavoriteLimitReachedException::class, sprintf('You cannot have more than %d favorites.', self::MAX_FAVORITES));

        return CharacterFavorite::query()->create([...$key, 'sort_order' => $count]);
    }

    public function removeFavorite(string $bnetUserId, string $realmSlug, string $characterName): void
    {
        CharacterFavorite::query()
            ->where('bnet_user_id', $bnetUserId)
            ->where('realm_slug', mb_strtolower($realmSlug))
            ->where('character_name', mb_strtolower($characterName))
            ->delete();
    }
}
