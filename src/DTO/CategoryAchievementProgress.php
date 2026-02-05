<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class CategoryAchievementProgress
{
    /**
     * @param list<array{id: int, name: string, completed: bool}> $achievements
     */
    public function __construct(
        public string $categoryName,
        public int $krowiCategoryId,
        public int $totalAchievements,
        public int $completedAchievements,
        public array $achievements
    ) {
    }

    public function completionPercent(): int
    {
        if ($this->totalAchievements === 0) {
            return 0;
        }

        return (int) round(($this->completedAchievements / $this->totalAchievements) * 100);
    }
}
