<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard;

/**
 * Le rangement d'un haut fait : sa catégorie racine et son extension.
 */
final readonly class AchievementPlacement
{
    public function __construct(
        public int $id,
        public string $name,
        public string $categoryName,
        public int $expansionId,
    ) {}
}
