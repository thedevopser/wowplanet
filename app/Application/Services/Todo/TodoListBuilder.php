<?php

declare(strict_types=1);

namespace App\Application\Services\Todo;

use App\Application\DTOs\AccountScoreProgress;
use App\Application\Services\Progress\AchievementProgressAggregator;
use App\Application\Services\Progress\CollectionProgressAggregator;

/**
 * Ce qu'il reste à faire sur tout le compte : les catégories entamées et incomplètes, du plus proche
 * de la fin au plus lointain, avec leurs éléments manquants et qui peut les faire.
 *
 * Pur : il lit le profil virtuel du compte que construit le score du compte, sans cache ni API.
 *
 * @phpstan-import-type AccountScoreResult from AccountScoreProgress
 * @phpstan-import-type AccountExpansionCollection from AccountScoreProgress
 * @phpstan-import-type CollectibleProgress from CollectionProgressAggregator
 * @phpstan-import-type DecorProgress from CollectionProgressAggregator
 * @phpstan-import-type AchievementItem from AchievementProgressAggregator
 *
 * @phpstan-type TodoDoers array{scope: string, characters: list<string>}
 * @phpstan-type TodoItem array{id: int, name: string, wowheadUrl: string, doers: TodoDoers}
 * @phpstan-type TodoCategory array{key: string, name: string, dimension: string, completed: int, total: int, missing: int, percent: float, missingItems: list<TodoItem>, missingMore: int}
 * @phpstan-type TodoCandidate array{id: int, name: string, is_completed: bool, wowheadUrl: string}
 * @phpstan-type SourcedCandidate array{id: int, name: string, is_completed: bool, wowheadUrl: string, source: string|null}
 */
final readonly class TodoListBuilder
{
    public const int MAX_ITEMS_SHOWN = 20; // @pest-mutate-ignore

    private const string WOWHEAD = 'https://www.wowhead.com/fr'; // @pest-mutate-ignore

    private const string SPELL_PATH = 'spell'; // @pest-mutate-ignore

    private const string CREATURE_PATH = 'npc'; // @pest-mutate-ignore

    private const string ITEM_PATH = 'item'; // @pest-mutate-ignore

    private const string ACHIEVEMENT_PATH = 'achievement'; // @pest-mutate-ignore

    /**
     * @param  AccountScoreResult  $accountProfile
     * @return list<TodoCategory>
     */
    public function build(array $accountProfile): array
    {
        $categories = [
            ...$this->bySource(TodoDimension::Mounts, $this->collectibles($accountProfile['mounts'], self::SPELL_PATH)),
            ...$this->bySource(TodoDimension::Pets, $this->collectibles($accountProfile['pets'], self::CREATURE_PATH)),
            ...$this->bySource(TodoDimension::Decor, $this->decorations($accountProfile['decor'])),
            ...$this->achievementCategories($accountProfile['collections']),
        ];

        usort($categories, $this->closestToTheEndFirst(...));

        return $categories;
    }

    /**
     * @param  TodoCategory  $first
     * @param  TodoCategory  $second
     */
    private function closestToTheEndFirst(array $first, array $second): int
    {
        return [$first['missing'], $second['percent']] <=> [$second['missing'], $first['percent']];
    }

    /**
     * @param  list<CollectibleProgress>  $collectibles
     * @return list<SourcedCandidate>
     */
    private function collectibles(array $collectibles, string $wowheadPath): array
    {
        return array_map(fn (array $collectible): array => [
            'id' => $collectible['id'],
            'name' => $collectible['name'],
            'is_completed' => $collectible['is_completed'],
            'wowheadUrl' => $this->wowheadUrl($wowheadPath, $collectible['wowhead_id'], $collectible['name']),
            'source' => $collectible['source'],
        ], $collectibles);
    }

    /**
     * @param  list<DecorProgress>  $decorations
     * @return list<SourcedCandidate>
     */
    private function decorations(array $decorations): array
    {
        return array_map(fn (array $decoration): array => [
            'id' => $decoration['id'],
            'name' => $decoration['name'],
            'is_completed' => $decoration['is_completed'],
            'wowheadUrl' => $this->wowheadUrl(self::ITEM_PATH, $decoration['item_id'], $decoration['name']),
            'source' => $decoration['source'],
        ], $decorations);
    }

    /**
     * Une collection se range par source ; un élément sans source connue n'entre dans aucune catégorie.
     *
     * @param  list<SourcedCandidate>  $candidates
     * @return list<TodoCategory>
     */
    private function bySource(TodoDimension $todoDimension, array $candidates): array
    {
        $sources = array_values(array_unique(array_filter(array_column($candidates, 'source'), is_string(...))));

        return $this->worthDoing(array_map(fn (string $source): array => $this->category(
            $todoDimension->value.':'.$source,
            $source,
            $todoDimension,
            array_values(array_filter($candidates, static fn (array $candidate): bool => $candidate['source'] === $source)),
        ), $sources));
    }

    /**
     * @param  array<int, AccountExpansionCollection>  $collections
     * @return list<TodoCategory>
     */
    private function achievementCategories(array $collections): array
    {
        $categories = [];

        foreach ($collections as $expansionId => $expansion) {
            $categories = [...$categories, ...$this->expansionAchievementCategories($expansionId, $expansion)];
        }

        return $this->worthDoing($categories);
    }

    /**
     * @param  AccountExpansionCollection  $expansion
     * @return list<TodoCategory>
     */
    private function expansionAchievementCategories(int $expansionId, array $expansion): array
    {
        return array_map(fn (array $achievementCategory): array => $this->category(
            TodoDimension::Achievements->value.':'.$expansionId.':'.$achievementCategory['name'],
            $achievementCategory['name'],
            TodoDimension::Achievements,
            $this->achievements($achievementCategory['items']),
        ), array_values(array_filter(
            $expansion['achievements']['categories'],
            static fn (array $achievementCategory): bool => $achievementCategory['items'] !== [],
        )));
    }

    /**
     * @param  list<AchievementItem>  $achievements
     * @return list<TodoCandidate>
     */
    private function achievements(array $achievements): array
    {
        return array_map(fn (array $achievement): array => [
            'id' => $achievement['id'],
            'name' => $achievement['name'],
            'is_completed' => $achievement['is_completed'],
            'wowheadUrl' => $this->wowheadUrl(self::ACHIEVEMENT_PATH, $achievement['id'], $achievement['name']),
        ], $achievements);
    }

    /**
     * @param  list<TodoCandidate|SourcedCandidate>  $candidates
     * @return TodoCategory
     */
    private function category(string $key, string $name, TodoDimension $todoDimension, array $candidates): array
    {
        $missing = array_values(array_filter($candidates, static fn (array $candidate): bool => ! $candidate['is_completed']));
        $total = count($candidates);
        $completed = $total - count($missing);

        return [
            'key' => $key,
            'name' => $name,
            'dimension' => $todoDimension->value,
            'completed' => $completed,
            'total' => $total,
            'missing' => count($missing),
            'percent' => $completed / $total * 100,
            'missingItems' => array_map($this->item(...), array_slice($missing, 0, self::MAX_ITEMS_SHOWN)),
            'missingMore' => max(0, count($missing) - self::MAX_ITEMS_SHOWN),
        ];
    }

    /**
     * Une catégorie mérite sa place quand elle est entamée et qu'il y reste quelque chose : une catégorie
     * jamais commencée ne dit pas au joueur par où reprendre.
     *
     * @param  list<TodoCategory>  $categories
     * @return list<TodoCategory>
     */
    private function worthDoing(array $categories): array
    {
        return array_values(array_filter(
            $categories,
            static fn (array $category): bool => $category['completed'] > 0 && $category['missing'] > 0,
        ));
    }

    /**
     * @param  TodoCandidate|SourcedCandidate  $candidate
     * @return TodoItem
     */
    private function item(array $candidate): array
    {
        return [
            'id' => $candidate['id'],
            'name' => $candidate['name'],
            'wowheadUrl' => $candidate['wowheadUrl'],
            'doers' => ['scope' => TodoScope::Account->value, 'characters' => []],
        ];
    }

    private function wowheadUrl(string $path, ?int $wowheadId, string $name): string
    {
        if ($wowheadId === null) {
            return self::WOWHEAD.'/search?q='.rawurlencode($name);
        }

        return self::WOWHEAD.'/'.$path.'='.$wowheadId;
    }
}
