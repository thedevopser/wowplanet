<?php

declare(strict_types=1);

namespace App\Application\Services\Progress;

use App\Infrastructure\Blizzard\Responses\Talent\CharacterSpecializationsResponse;
use App\Infrastructure\Blizzard\Responses\Talent\HeroTalentTree;
use App\Infrastructure\Blizzard\Responses\Talent\SelectedTalent;
use App\Infrastructure\Blizzard\Responses\Talent\TalentNode;
use App\Infrastructure\Blizzard\Responses\Talent\TalentRank;
use App\Infrastructure\Blizzard\Responses\Talent\TalentTooltip;
use App\Infrastructure\Blizzard\Responses\Talent\TalentTreeResponse;

/**
 * @phpstan-type TalentEntry array{talent_id: int, spell_id: int, name: string, icon_url: string|null}
 * @phpstan-type TalentChoiceEntry array{talent_id: int, spell_id: int, name: string, icon_url: string|null, selected: bool}
 * @phpstan-type TalentNodeView array{id: int, x: int, y: int, type: string, max_rank: int, selected_rank: int, locked_by: list<int>, unlocks: list<int>, entries: list<TalentEntry>|list<TalentChoiceEntry>}
 * @phpstan-type HeroTreeView array{id: int, name: string, active: bool, nodes: list<TalentNodeView>}
 * @phpstan-type TalentBuild array{spec_name: string, spec_id: int, class_name: string, class_nodes: list<TalentNodeView>, spec_nodes: list<TalentNodeView>, hero_trees: list<HeroTreeView>}
 * @phpstan-type SelectedMap array<int, array{rank: int, talent_id: int}>
 */
class TalentAggregator
{
    private const string DEFAULT_NODE_TYPE = 'ACTIVE';

    private const string CHOICE_NODE_TYPE = 'CHOICE';

    /**
     * @param  array<int, string>  $spellIcons  Icône par identifiant de sort
     * @return TalentBuild
     */
    public function aggregate(
        CharacterSpecializationsResponse $characterSpecializationsResponse,
        TalentTreeResponse $talentTreeResponse,
        array $spellIcons = [],
    ): array {
        $activeLoadout = $characterSpecializationsResponse->activeLoadout;
        $activeHeroTreeId = $activeLoadout?->heroTreeId;

        // Only keep hero trees available to the character's active specialization.
        $activeSpecId = $talentTreeResponse->specId ?? 0;
        $heroTrees = $this->filterHeroTreesBySpec($talentTreeResponse->heroTrees, $activeSpecId);

        // Blizzard duplicates some hero talent nodes into class/spec trees.
        // Collect all hero node IDs to filter them out.
        $heroNodeIds = $this->collectHeroNodeIds($heroTrees);

        return [
            'spec_name' => $talentTreeResponse->specName ?? '',
            'spec_id' => $activeSpecId,
            'class_name' => $talentTreeResponse->className ?? '',
            'class_nodes' => $this->transformNodes($talentTreeResponse->classNodes, $this->buildSelectedMap($activeLoadout->classTalents ?? []), $spellIcons, $heroNodeIds),
            'spec_nodes' => $this->transformNodes($talentTreeResponse->specNodes, $this->buildSelectedMap($activeLoadout->specTalents ?? []), $spellIcons, $heroNodeIds),
            'hero_trees' => $this->transformHeroTrees($heroTrees, $this->buildSelectedMap($activeLoadout->heroTalents ?? []), $spellIcons, $activeHeroTreeId),
        ];
    }

    /**
     * @param  list<HeroTalentTree>  $heroTrees
     * @return list<HeroTalentTree>
     */
    private function filterHeroTreesBySpec(array $heroTrees, int $specId): array
    {
        if ($specId === 0) {
            return $heroTrees;
        }

        return array_values(array_filter(
            $heroTrees,
            static fn (HeroTalentTree $heroTalentTree): bool => in_array($specId, $heroTalentTree->specializationIds, true),
        ));
    }

    /**
     * @param  list<HeroTalentTree>  $heroTrees
     * @return array<int, true>
     */
    private function collectHeroNodeIds(array $heroTrees): array
    {
        $ids = [];

        foreach ($heroTrees as $heroTree) {
            foreach ($heroTree->nodes as $node) {
                if ($node->id !== null) {
                    $ids[$node->id] = true;
                }
            }
        }

        return $ids;
    }

    /**
     * @param  list<SelectedTalent>  $selectedTalents
     * @return SelectedMap
     */
    private function buildSelectedMap(array $selectedTalents): array
    {
        $map = [];

        foreach ($selectedTalents as $selectedTalent) {
            $nodeId = $selectedTalent->nodeId ?? 0;

            if ($nodeId === 0) {
                continue;
            }

            $map[$nodeId] = [
                'rank' => $selectedTalent->rank ?? 0,
                'talent_id' => $selectedTalent->talentId ?? 0,
            ];
        }

        return $map;
    }

    /**
     * @param  list<TalentNode>  $talentNodes
     * @param  SelectedMap  $selectedMap
     * @param  array<int, string>  $spellIcons
     * @param  array<int, true>  $excludeIds  Node IDs to skip (e.g. hero tree dups)
     * @return list<TalentNodeView>
     */
    private function transformNodes(array $talentNodes, array $selectedMap, array $spellIcons, array $excludeIds = []): array
    {
        $nodes = [];

        foreach ($talentNodes as $talentNode) {
            $nodeId = $talentNode->id ?? 0;

            if ($nodeId !== 0 && isset($excludeIds[$nodeId])) {
                continue;
            }

            $node = $this->transformNode($talentNode, $selectedMap, $spellIcons);

            if ($node !== null) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    /**
     * @param  SelectedMap  $selectedMap
     * @param  array<int, string>  $spellIcons
     * @return TalentNodeView|null
     */
    private function transformNode(TalentNode $talentNode, array $selectedMap, array $spellIcons): ?array
    {
        $nodeId = $talentNode->id ?? 0;

        if ($nodeId === 0) {
            return null;
        }

        $type = $talentNode->type ?? self::DEFAULT_NODE_TYPE;
        $selected = $selectedMap[$nodeId] ?? null;

        $entries = $this->extractEntries($talentNode->ranks, $type, $selected['talent_id'] ?? 0, $spellIcons);

        if ($entries === []) {
            return null;
        }

        return [
            'id' => $nodeId,
            'x' => $talentNode->displayCol ?? 0,
            'y' => $talentNode->displayRow ?? 0,
            'type' => mb_strtolower($type),
            'max_rank' => count($talentNode->ranks),
            'selected_rank' => $selected['rank'] ?? 0,
            'locked_by' => $talentNode->lockedBy,
            'unlocks' => $talentNode->unlocks,
            'entries' => $entries,
        ];
    }

    /**
     * @param  list<TalentRank>  $ranks
     * @param  array<int, string>  $spellIcons
     * @return list<TalentEntry>|list<TalentChoiceEntry>
     */
    private function extractEntries(array $ranks, string $type, int $selectedTalentId, array $spellIcons): array
    {
        if ($ranks === []) {
            return [];
        }

        $firstRank = $ranks[0];

        if ($type === self::CHOICE_NODE_TYPE) {
            return $this->extractChoiceEntries($firstRank, $selectedTalentId, $spellIcons);
        }

        return $this->extractSingleEntry($firstRank, $spellIcons);
    }

    /**
     * @param  array<int, string>  $spellIcons
     * @return list<TalentEntry>
     */
    private function extractSingleEntry(TalentRank $talentRank, array $spellIcons): array
    {
        if (! $talentRank->tooltip instanceof TalentTooltip) {
            return [];
        }

        return [$this->entry($talentRank->tooltip, $spellIcons)];
    }

    /**
     * @param  array<int, string>  $spellIcons
     * @return list<TalentChoiceEntry>
     */
    private function extractChoiceEntries(TalentRank $talentRank, int $selectedTalentId, array $spellIcons): array
    {
        $entries = [];

        foreach ($talentRank->choices as $choice) {
            $entry = $this->entry($choice, $spellIcons);
            $entry['selected'] = $selectedTalentId > 0 && $entry['talent_id'] === $selectedTalentId;
            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * @param  array<int, string>  $spellIcons
     * @return TalentEntry
     */
    private function entry(TalentTooltip $talentTooltip, array $spellIcons): array
    {
        $spellId = $talentTooltip->spellId;

        return [
            'talent_id' => $talentTooltip->talentId ?? 0,
            'spell_id' => $spellId ?? 0,
            'name' => $talentTooltip->talentName ?? '',
            'icon_url' => $spellId === null ? null : $spellIcons[$spellId] ?? null,
        ];
    }

    /**
     * @param  list<HeroTalentTree>  $heroTrees
     * @param  SelectedMap  $selectedMap
     * @param  array<int, string>  $spellIcons
     * @return list<HeroTreeView>
     */
    private function transformHeroTrees(array $heroTrees, array $selectedMap, array $spellIcons, ?int $activeHeroTreeId): array
    {
        $result = [];

        foreach ($heroTrees as $heroTree) {
            $isActive = $heroTree->id !== null && $heroTree->id === $activeHeroTreeId;

            $result[] = [
                'id' => $heroTree->id ?? 0,
                'name' => $heroTree->name ?? '',
                'active' => $isActive,
                'nodes' => $this->transformNodes($heroTree->nodes, $isActive ? $selectedMap : [], $spellIcons),
            ];
        }

        return $result;
    }
}
