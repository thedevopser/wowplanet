<?php

declare(strict_types=1);

namespace App\Application\Services\Todo;

use App\Application\DTOs\AccountScoreProgress;
use App\Application\DTOs\CrossCharacterProgress;
use App\Application\Services\Progress\AchievementProgressAggregator;
use App\Application\Services\Progress\CollectionProgressAggregator;
use App\Application\Services\Progress\ProfessionProgressAggregator;
use App\Application\Services\Progress\QuestProgressAggregator;
use App\Domain\ValueObjects\ExpansionId;
use App\Http\Localization\AppLocale;

/**
 * Ce qu'il reste à faire sur tout le compte : les catégories entamées et incomplètes, du plus proche
 * de la fin au plus lointain, avec leurs éléments manquants et qui peut les faire.
 *
 * Pur : il lit le profil virtuel du compte que construit le score du compte et le résultat du calcul
 * inter-personnages, sans cache ni API. Les références qu'ils ne portent pas (faction des quêtes,
 * réputations suivies) lui sont passées.
 *
 * @phpstan-import-type AccountScoreResult from AccountScoreProgress
 * @phpstan-import-type AccountExpansionCollection from AccountScoreProgress
 * @phpstan-import-type CrossCharacterResult from CrossCharacterProgress
 * @phpstan-import-type FactionStanding from CrossCharacterProgress
 * @phpstan-import-type SkillPointOwner from CrossCharacterProgress
 * @phpstan-import-type CollectibleProgress from CollectionProgressAggregator
 * @phpstan-import-type DecorProgress from CollectionProgressAggregator
 * @phpstan-import-type AchievementItem from AchievementProgressAggregator
 * @phpstan-import-type QuestItem from QuestProgressAggregator
 * @phpstan-import-type ProfessionProgress from ProfessionProgressAggregator
 * @phpstan-import-type RecipeCategory from ProfessionProgressAggregator
 *
 * @phpstan-type TodoCharacter array{name: string, faction: string}
 * @phpstan-type TrackedFaction array{id: int, name: string, expansion_id: int}
 * @phpstan-type TodoDoers array{scope: string, characters: list<string>}
 * @phpstan-type TodoItem array{id: int, name: string, wowheadUrl: string, detail: string|null, doers: TodoDoers}
 * @phpstan-type TodoCategory array{key: string, name: string, dimension: string, expansionId: int|null, completed: int, total: int, missing: int, percent: float, missingItems: list<TodoItem>, missingMore: int}
 * @phpstan-type TodoCandidate array{id: int, name: string, is_completed: bool, wowheadUrl: string, detail: string|null, doers: TodoDoers}
 * @phpstan-type SourcedCandidate array{id: int, name: string, is_completed: bool, wowheadUrl: string, detail: string|null, doers: TodoDoers, source: string|null}
 */
final readonly class TodoListBuilder
{
    public const int MAX_ITEMS_SHOWN = 20; // @pest-mutate-ignore

    private const string SPELL_PATH = 'spell'; // @pest-mutate-ignore

    private const string CREATURE_PATH = 'npc'; // @pest-mutate-ignore

    private const string ITEM_PATH = 'item'; // @pest-mutate-ignore

    private const string ACHIEVEMENT_PATH = 'achievement'; // @pest-mutate-ignore

    private const string QUEST_PATH = 'quest'; // @pest-mutate-ignore

    private const string FACTION_PATH = 'faction'; // @pest-mutate-ignore

    private const string RECIPE_CATEGORY_SEPARATOR = ' · '; // @pest-mutate-ignore

    private string $wowheadBase;

    public function __construct(?AppLocale $appLocale = null)
    {
        $this->wowheadBase = ($appLocale ?? AppLocale::French)->wowheadBase();
    }

    /**
     * @param  AccountScoreResult  $accountProfile
     * @param  CrossCharacterResult  $crossCharacter
     * @param  list<TodoCharacter>  $characters
     * @param  array<int, string>  $questFactions  Faction de chaque quête réservée à une faction ; une quête absente est neutre
     * @param  list<TrackedFaction>  $trackedFactions
     * @return list<TodoCategory>
     */
    public function build(array $accountProfile, array $crossCharacter, array $characters, array $questFactions, array $trackedFactions): array
    {
        $categories = [
            ...$this->bySource(TodoDimension::Mounts, $this->collectibles($accountProfile['mounts'], self::SPELL_PATH)),
            ...$this->bySource(TodoDimension::Pets, $this->collectibles($accountProfile['pets'], self::CREATURE_PATH)),
            ...$this->bySource(TodoDimension::Decor, $this->decorations($accountProfile['decor'])),
            ...$this->achievementCategories($accountProfile['collections']),
            ...$this->questCategories($accountProfile['collections'], $characters, $questFactions),
            ...$this->reputationCategories($trackedFactions, $crossCharacter['bestFactionStandings']),
            ...$this->recipeCategories($accountProfile['professions'], $crossCharacter['skillPointOwners']),
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
            ...$this->candidate($collectible['id'], $collectible['name'], $collectible['is_completed'], $this->wowheadUrl($wowheadPath, $collectible['wowhead_id'], $collectible['name'])),
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
            ...$this->candidate($decoration['id'], $decoration['name'], $decoration['is_completed'], $this->wowheadUrl(self::ITEM_PATH, $decoration['item_id'], $decoration['name'])),
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
            null,
            array_values(array_filter($candidates, static fn (array $candidate): bool => $candidate['source'] === $source)),
        ), $sources));
    }

    /**
     * @param  array<int, AccountExpansionCollection>  $collections
     * @return list<TodoCategory>
     */
    private function achievementCategories(array $collections): array
    {
        return $this->worthDoing($this->eachExpansion($collections, fn (int $expansionId, array $expansion): array => array_map(
            fn (array $achievementCategory): array => $this->category(
                TodoDimension::Achievements->value.':'.$expansionId.':'.$achievementCategory['name'],
                $achievementCategory['name'],
                TodoDimension::Achievements,
                $expansionId,
                $this->achievements($achievementCategory['items']),
            ),
            $expansion['achievements']['categories'],
        )));
    }

    /**
     * @param  list<AchievementItem>  $achievements
     * @return list<TodoCandidate>
     */
    private function achievements(array $achievements): array
    {
        return array_map(fn (array $achievement): array => $this->candidate(
            $achievement['id'],
            $achievement['name'],
            $achievement['is_completed'],
            $this->wowheadUrl(self::ACHIEVEMENT_PATH, $achievement['id'], $achievement['name']),
        ), $achievements);
    }

    /**
     * @param  array<int, AccountExpansionCollection>  $collections
     * @param  list<TodoCharacter>  $characters
     * @param  array<int, string>  $questFactions
     * @return list<TodoCategory>
     */
    private function questCategories(array $collections, array $characters, array $questFactions): array
    {
        return $this->worthDoing($this->eachExpansion($collections, fn (int $expansionId, array $expansion): array => array_map(
            fn (array $zone): array => $this->category(
                TodoDimension::Quests->value.':'.$expansionId.':'.$zone['name'],
                $zone['name'],
                TodoDimension::Quests,
                $expansionId,
                $this->doableQuests($zone['items'], $characters, $questFactions),
            ),
            $expansion['quests']['zones'],
        )));
    }

    /**
     * Le profil du compte marque une quête faite dès qu'un personnage l'a faite : une quête manquante
     * n'a été faite par personne, et revient aux personnages de sa faction. Une quête qu'aucun
     * personnage du compte ne peut faire sort de la liste, et de l'avancement de sa zone.
     *
     * @param  list<QuestItem>  $quests
     * @param  list<TodoCharacter>  $characters
     * @param  array<int, string>  $questFactions
     * @return list<TodoCandidate>
     */
    private function doableQuests(array $quests, array $characters, array $questFactions): array
    {
        $candidates = array_map(fn (array $quest): array => [
            ...$this->candidate($quest['id'], $quest['name'], $quest['is_completed'], $this->wowheadUrl(self::QUEST_PATH, $quest['id'], $quest['name'])),
            'doers' => $this->namedDoers($this->charactersOfFaction($characters, $questFactions[$quest['id']] ?? null)),
        ], $quests);

        return array_values(array_filter(
            $candidates,
            static fn (array $candidate): bool => $candidate['is_completed'] || $candidate['doers']['characters'] !== [],
        ));
    }

    /**
     * @param  list<TodoCharacter>  $characters
     * @return list<string>
     */
    private function charactersOfFaction(array $characters, ?string $faction): array
    {
        return array_values(array_map(
            static fn (array $character): string => $character['name'],
            array_filter($characters, static fn (array $character): bool => $faction === null || $character['faction'] === $faction),
        ));
    }

    /**
     * @param  list<TrackedFaction>  $trackedFactions
     * @param  array<int, FactionStanding>  $bestFactionStandings
     * @return list<TodoCategory>
     */
    private function reputationCategories(array $trackedFactions, array $bestFactionStandings): array
    {
        $expansionIds = array_values(array_unique(array_column($trackedFactions, 'expansion_id')));

        return $this->worthDoing(array_map(fn (int $expansionId): array => $this->category(
            TodoDimension::Reputations->value.':'.$expansionId,
            new ExpansionId($expansionId)->toString(),
            TodoDimension::Reputations,
            $expansionId,
            array_map(
                fn (array $trackedFaction): array => $this->reputation($trackedFaction, $bestFactionStandings[$trackedFaction['id']] ?? null),
                array_values(array_filter($trackedFactions, static fn (array $trackedFaction): bool => $trackedFaction['expansion_id'] === $expansionId)),
            ),
        ), $expansionIds));
    }

    /**
     * Une réputation revient au personnage le plus avancé ; une réputation qu'aucun personnage n'a
     * rencontrée peut être commencée par n'importe lequel.
     *
     * @param  TrackedFaction  $trackedFaction
     * @param  FactionStanding|null  $factionStanding
     * @return TodoCandidate
     */
    private function reputation(array $trackedFaction, ?array $factionStanding): array
    {
        $candidate = $this->candidate(
            $trackedFaction['id'],
            $trackedFaction['name'],
            $factionStanding !== null && $factionStanding['completed'],
            $this->wowheadUrl(self::FACTION_PATH, $trackedFaction['id'], $trackedFaction['name']),
        );

        if ($factionStanding === null) {
            return $candidate;
        }

        return [...$candidate, 'detail' => $factionStanding['standing_name'], 'doers' => $this->namedDoers([$factionStanding['character_name']])];
    }

    /**
     * @param  list<ProfessionProgress>  $professions
     * @param  array<int, array<int, SkillPointOwner>>  $skillPointOwners
     * @return list<TodoCategory>
     */
    private function recipeCategories(array $professions, array $skillPointOwners): array
    {
        $categories = [];

        foreach ($professions as $profession) {
            $categories = [...$categories, ...$this->professionRecipeCategories($profession, $skillPointOwners[$profession['profession_id']] ?? [])];
        }

        return $this->worthDoing($categories);
    }

    /**
     * Une recette revient au personnage le plus avancé dans ce métier pour son extension ; sans
     * personnage connu dans ce métier et cette extension, les recettes de l'extension ne sont pas proposées.
     *
     * @param  ProfessionProgress  $profession
     * @param  array<int, SkillPointOwner>  $ownersByExpansion
     * @return list<TodoCategory>
     */
    private function professionRecipeCategories(array $profession, array $ownersByExpansion): array
    {
        $categories = [];

        foreach (array_intersect_key($profession['expansions'], $ownersByExpansion) as $expansionId => $expansion) {
            $categories = [...$categories, ...array_map(
                fn (array $recipeCategory): array => $this->recipeCategory($profession, $expansionId, $recipeCategory, $ownersByExpansion[$expansionId]),
                $expansion['categories'],
            )];
        }

        return $categories;
    }

    /**
     * @param  ProfessionProgress  $profession
     * @param  RecipeCategory  $recipeCategory
     * @param  SkillPointOwner  $skillPointOwner
     * @return TodoCategory
     */
    private function recipeCategory(array $profession, int $expansionId, array $recipeCategory, array $skillPointOwner): array
    {
        return $this->category(
            TodoDimension::Recipes->value.':'.$profession['profession_id'].':'.$expansionId.':'.$recipeCategory['name'],
            $profession['profession_name'].self::RECIPE_CATEGORY_SEPARATOR.$recipeCategory['name'],
            TodoDimension::Recipes,
            $expansionId,
            array_map(fn (array $recipe): array => [
                ...$this->candidate($recipe['id'], $recipe['name'], $recipe['is_completed'], $this->wowheadUrl(self::SPELL_PATH, $recipe['wowhead_spell_id'], $recipe['name'])),
                'detail' => $skillPointOwner['skill_points'].' / '.$skillPointOwner['max_skill_points'],
                'doers' => $this->namedDoers([$skillPointOwner['character_name']]),
            ], $recipeCategory['items']),
        );
    }

    /**
     * @param  array<int, AccountExpansionCollection>  $collections
     * @param  callable(int, AccountExpansionCollection): list<TodoCategory>  $categoriesOfExpansion
     * @return list<TodoCategory>
     */
    private function eachExpansion(array $collections, callable $categoriesOfExpansion): array
    {
        $categories = [];

        foreach ($collections as $expansionId => $expansion) {
            $categories = [...$categories, ...$categoriesOfExpansion($expansionId, $expansion)];
        }

        return $categories;
    }

    /**
     * @return TodoCandidate
     */
    private function candidate(int $id, string $name, bool $isCompleted, string $wowheadUrl): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'is_completed' => $isCompleted,
            'wowheadUrl' => $wowheadUrl,
            'detail' => null,
            'doers' => ['scope' => TodoScope::Account->value, 'characters' => []],
        ];
    }

    /**
     * @param  list<string>  $characterNames
     * @return TodoDoers
     */
    private function namedDoers(array $characterNames): array
    {
        return ['scope' => TodoScope::Characters->value, 'characters' => $characterNames];
    }

    /**
     * Une catégorie vide (hauts-faits sans haut-fait, zone dont aucune quête n'est faisable) est rendue
     * à zéro puis écartée par `worthDoing()`.
     *
     * @param  list<TodoCandidate|SourcedCandidate>  $candidates
     * @return TodoCategory
     */
    private function category(string $key, string $name, TodoDimension $todoDimension, ?int $expansionId, array $candidates): array
    {
        $missing = array_values(array_filter($candidates, static fn (array $candidate): bool => ! $candidate['is_completed']));
        $total = count($candidates);
        $completed = $total - count($missing);

        return [
            'key' => $key,
            'name' => $name,
            'dimension' => $todoDimension->value,
            'expansionId' => $expansionId,
            'completed' => $completed,
            'total' => $total,
            'missing' => count($missing),
            'percent' => $total === 0 ? 0.0 : $completed / $total * 100,
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
            'detail' => $candidate['detail'],
            'doers' => $candidate['doers'],
        ];
    }

    private function wowheadUrl(string $path, ?int $wowheadId, string $name): string
    {
        if ($wowheadId === null) {
            return $this->wowheadBase.'/search?q='.rawurlencode($name);
        }

        return $this->wowheadBase.'/'.$path.'='.$wowheadId;
    }
}
