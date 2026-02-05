<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class ExpansionAchievementGroup
{
    /**
     * @param list<array{name: string, krowi_category_id: int}> $categories
     */
    public function __construct(
        public string $expansionName,
        public int $order,
        public int $totalAchievements,
        public int $completedAchievements,
        public array $categories
    ) {
    }

    public function hasAchievements(): bool
    {
        return $this->totalAchievements > 0;
    }

    public function completionPercent(): int
    {
        if ($this->totalAchievements === 0) {
            return 0;
        }

        return (int) round(($this->completedAchievements / $this->totalAchievements) * 100);
    }
}
