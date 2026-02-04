<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class ZoneQuestProgress
{
    /**
     * @param list<array{id: int, name: string, completed: bool}> $quests
     */
    public function __construct(
        public string $zoneName,
        public int $btwCategoryId,
        public int $totalQuests,
        public int $completedQuests,
        public array $quests
    ) {
    }

    public function completionPercent(): int
    {
        if ($this->totalQuests === 0) {
            return 0;
        }

        return (int) round(($this->completedQuests / $this->totalQuests) * 100);
    }
}
