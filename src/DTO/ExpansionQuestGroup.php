<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class ExpansionQuestGroup
{
    /**
     * @param list<array{name: string, btw_category_id: int}> $zones
     */
    public function __construct(
        public string $expansionName,
        public int $order,
        public int $totalQuests,
        public int $completedQuests,
        public array $zones
    ) {
    }

    public function hasQuests(): bool
    {
        return $this->totalQuests > 0;
    }

    public function completionPercent(): int
    {
        if ($this->totalQuests === 0) {
            return 0;
        }

        return (int) round(($this->completedQuests / $this->totalQuests) * 100);
    }
}
