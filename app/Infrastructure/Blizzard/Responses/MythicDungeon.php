<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses;

/**
 * A dungeon of the season's Mythic+ rotation.
 */
final readonly class MythicDungeon
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
