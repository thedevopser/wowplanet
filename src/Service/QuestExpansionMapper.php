<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\ExpansionQuestGroup;
use App\DTO\ZoneQuestProgress;
use Psr\Log\LoggerInterface;

final readonly class QuestExpansionMapper
{
    /** @var list<array{name: string, order: int}> */
    private array $expansionDefinitions;

    /** @var array<int, int> quest_id => expansion_order */
    private array $questExpansionMap;

    /** @var array<int, list<array{name: string, btw_category_id: int}>> */
    private array $expansionZones;

    /** @var array<int, int> expansion_order => total quest count */
    private array $questCountPerExpansion;

    /** @var array<int, list<int>> btw_category_id => quest_ids */
    private array $zoneQuests;

    /** @var array<int, string> quest_id => quest_name */
    private array $questNames;

    public function __construct(
        private LoggerInterface $logger
    ) {
        /** @var list<array{name: string, order: int}> $definitions */
        $definitions = require __DIR__ . '/../Data/wow_expansions.php';
        $this->expansionDefinitions = $definitions;

        /** @var array<int, int> $questMap */
        $questMap = require __DIR__ . '/../Data/quest_expansion_map.php';
        $this->questExpansionMap = $questMap;

        /** @var array<int, list<array{name: string, btw_category_id: int}>> $zones */
        $zones = require __DIR__ . '/../Data/expansion_zones.php';
        $this->expansionZones = $zones;

        /** @var array<int, list<int>> $zoneQuestsData */
        $zoneQuestsData = require __DIR__ . '/../Data/zone_quests.php';
        $this->zoneQuests = $zoneQuestsData;

        /** @var array<int, string> $names */
        $names = require __DIR__ . '/../Data/quest_names.php';
        $this->questNames = $names;

        $this->questCountPerExpansion = $this->computeQuestCounts();
    }

    /**
     * @param array<int, bool> $completedQuestIds
     * @return list<ExpansionQuestGroup>
     */
    public function buildExpansionProgress(array $completedQuestIds): array
    {
        $completedPerExpansion = $this->countCompletedPerExpansion($completedQuestIds);

        $groups = [];
        foreach ($this->expansionDefinitions as $expansion) {
            $order = $expansion['order'];

            $groups[] = new ExpansionQuestGroup(
                expansionName: $expansion['name'],
                order: $order,
                totalQuests: $this->questCountPerExpansion[$order] ?? 0,
                completedQuests: $completedPerExpansion[$order] ?? 0,
                zones: $this->expansionZones[$order] ?? [],
            );
        }

        usort(
            $groups,
            static fn (ExpansionQuestGroup $a, ExpansionQuestGroup $b): int => $a->order <=> $b->order
        );

        $this->logger->info('Expansion quest progress computed', [
            'total_completed' => count($completedQuestIds),
            'mapped_completed' => array_sum($completedPerExpansion),
        ]);

        return $groups;
    }

    /**
     * @param array<int, bool> $completedQuestIds
     * @return list<ZoneQuestProgress>
     */
    public function buildZoneProgress(
        int $expansionOrder,
        array $completedQuestIds
    ): array {
        $zones = $this->expansionZones[$expansionOrder] ?? [];
        $zoneProgressList = [];

        foreach ($zones as $zone) {
            $categoryId = $zone['btw_category_id'];
            $questIds = $this->zoneQuests[$categoryId] ?? [];

            $quests = [];
            $completedCount = 0;

            foreach ($questIds as $questId) {
                $isCompleted = $completedQuestIds[$questId] ?? false;

                if ($isCompleted) {
                    $completedCount++;
                }

                $quests[] = [
                    'id' => $questId,
                    'name' => $this->questNames[$questId] ?? 'Quest #' . $questId,
                    'completed' => $isCompleted,
                ];
            }

            $zoneProgressList[] = new ZoneQuestProgress(
                zoneName: $zone['name'],
                btwCategoryId: $categoryId,
                totalQuests: count($questIds),
                completedQuests: $completedCount,
                quests: $quests,
            );
        }

        usort(
            $zoneProgressList,
            static fn (ZoneQuestProgress $a, ZoneQuestProgress $b): int => $a->zoneName <=> $b->zoneName
        );

        return $zoneProgressList;
    }

    /**
     * @param array<int, bool> $completedQuestIds
     * @return array<int, int> expansion_order => completed count
     */
    private function countCompletedPerExpansion(array $completedQuestIds): array
    {
        $counts = [];

        foreach ($completedQuestIds as $questId => $completed) {
            if (!$completed) {
                continue;
            }

            $expansionOrder = $this->questExpansionMap[$questId] ?? null;

            if ($expansionOrder === null) {
                continue;
            }

            $counts[$expansionOrder] = ($counts[$expansionOrder] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @return array<int, int> expansion_order => total quest count
     */
    private function computeQuestCounts(): array
    {
        $counts = [];

        foreach ($this->questExpansionMap as $expansionOrder) {
            $counts[$expansionOrder] = ($counts[$expansionOrder] ?? 0) + 1;
        }

        return $counts;
    }
}
