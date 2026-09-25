<?php

declare(strict_types=1);

namespace App\Application\Services\Progress;

use App\Infrastructure\Blizzard\ExpansionTierMatcher;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterProfession;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterProfessionsResponse;
use App\Models\WowProfession;
use App\Models\WowRecipe;
use Illuminate\Support\Collection;

/**
 * @phpstan-type RecipeItem array{id: int, name: string, is_completed: bool, wowhead_spell_id: int|null}
 * @phpstan-type RecipeCategory array{name: string, total: int, completed: int, items: list<RecipeItem>}
 * @phpstan-type ProfessionExpansionProgress array{total: int, completed: int, categories: list<RecipeCategory>, has_tier: bool, tier_exists: bool, skill_points: int, max_skill_points: int}
 * @phpstan-type ProfessionProgress array{profession_id: int, profession_name: string, type: string, is_archaeology: bool, global_skill_points: int, global_max_skill_points: int, expansions: array<int, ProfessionExpansionProgress>}
 */
class ProfessionProgressAggregator
{
    // @pest-mutate-ignore
    private const PROFESSION_NAMES_FR = [
        164 => 'Forge',
        165 => 'Travail du cuir',
        171 => 'Alchimie',
        182 => 'Herboristerie',
        185 => 'Cuisine',
        186 => 'Minage',
        197 => 'Couture',
        202 => 'Ingénierie',
        333 => 'Enchantement',
        356 => 'Pêche',
        393 => 'Dépeçage',
        755 => 'Joaillerie',
        773 => 'Calligraphie',
        794 => 'Archéologie',
    ];

    // @pest-mutate-ignore
    private const SECONDARY_PROFESSION_IDS = [185, 356, 794];

    // @pest-mutate-ignore
    private const int ARCHAEOLOGY_ID = 794;

    /**
     * @return list<ProfessionProgress>
     */
    public function aggregate(CharacterProfessionsResponse $characterProfessionsResponse, string $characterFaction): array
    {
        return array_map(
            fn (CharacterProfession $characterProfession): array => $this->aggregateSingleProfession($characterProfession, $characterFaction),
            $characterProfessionsResponse->professions,
        );
    }

    /**
     * @return ProfessionProgress
     */
    private function aggregateSingleProfession(CharacterProfession $characterProfession, string $characterFaction): array
    {
        $professionId = $characterProfession->professionId;
        $tierData = $this->extractTierData($characterProfession);
        $allRecipes = $this->loadRecipesForProfession($professionId, $characterFaction);

        /** @var WowProfession|null $profession */
        $profession = WowProfession::query()->find($professionId);

        /** @var array<int, int> $dbMaxSkillLevels */
        $dbMaxSkillLevels = $profession !== null ? ($profession->max_skill_levels ?? []) : [];

        $expansionProgress = $this->buildExpansionProgress(
            $allRecipes,
            $tierData['knownRecipeIds'],
            $tierData['skillPointsByExpansion'],
            $dbMaxSkillLevels,
        );

        $professionName = $profession !== null
            ? (string) $profession->name_fr
            : (self::PROFESSION_NAMES_FR[$professionId] ?? $characterProfession->professionName ?? '');
        $professionType = $profession !== null
            ? (string) $profession->type
            : (in_array($professionId, self::SECONDARY_PROFESSION_IDS, true) ? 'secondary' : 'primary');

        return [
            'profession_id' => $professionId,
            'profession_name' => $professionName,
            'type' => $professionType,
            'is_archaeology' => $professionId === self::ARCHAEOLOGY_ID,
            'global_skill_points' => $characterProfession->skillPoints ?? 0,
            'global_max_skill_points' => $characterProfession->maxSkillPoints ?? 0,
            'expansions' => $expansionProgress,
        ];
    }

    /**
     * @return array{knownRecipeIds: list<int>, skillPointsByExpansion: array<int, array{skill_points: int, max_skill_points: int}>}
     */
    private function extractTierData(CharacterProfession $characterProfession): array
    {
        $knownRecipeIds = [];
        $skillPointsByExpansion = [];

        foreach ($characterProfession->tiers as $tier) {
            $knownRecipeIds = [...$knownRecipeIds, ...$tier->knownRecipeIds];

            $tierExpansionId = ExpansionTierMatcher::match($tier->name ?? '') ?? 0;
            $skillPointsByExpansion[$tierExpansionId] = [
                'skill_points' => $tier->skillPoints ?? 0,
                'max_skill_points' => $tier->maxSkillPoints ?? 0,
            ];
        }

        return ['knownRecipeIds' => $knownRecipeIds, 'skillPointsByExpansion' => $skillPointsByExpansion];
    }

    /**
     * @return Collection<int, WowRecipe>
     */
    private function loadRecipesForProfession(int $professionId, string $characterFaction): Collection
    {
        return WowRecipe::query()
            ->where('profession_id', $professionId)
            ->where('is_active', true)
            ->where(fn (\Illuminate\Contracts\Database\Query\Builder $builder) => $builder->whereNull('faction')->orWhere('faction', $characterFaction))
            ->get();
    }

    /**
     * @param  Collection<int, WowRecipe>  $allRecipes
     * @param  list<int>  $knownRecipeIds
     * @param  array<int, array{skill_points: int, max_skill_points: int}>  $skillPointsByExpansion
     * @param  array<int, int>  $dbMaxSkillLevels
     * @return array<int, ProfessionExpansionProgress>
     */
    private function buildExpansionProgress(
        Collection $allRecipes,
        array $knownRecipeIds,
        array $skillPointsByExpansion,
        array $dbMaxSkillLevels,
    ): array {
        $recipesByExpansion = $allRecipes->groupBy('expansion_id');
        $expansionProgress = [];

        for ($exp = 0; $exp <= 11; $exp++) {
            /** @var Collection<int, WowRecipe> $expansionRecipes */
            $expansionRecipes = $recipesByExpansion->get($exp, new Collection);
            $categoryProgress = $this->buildCategoryProgress($expansionRecipes, $knownRecipeIds);

            $hasTier = array_key_exists($exp, $skillPointsByExpansion);
            $tierExistsInGame = array_key_exists($exp, $dbMaxSkillLevels);

            $expansionProgress[$exp] = [
                'total' => array_sum(array_column($categoryProgress, 'total')),
                'completed' => array_sum(array_column($categoryProgress, 'completed')),
                'categories' => $categoryProgress,
                'has_tier' => $hasTier,
                'tier_exists' => $tierExistsInGame,
                'skill_points' => $skillPointsByExpansion[$exp]['skill_points'] ?? 0,
                'max_skill_points' => $hasTier
                    ? ($skillPointsByExpansion[$exp]['max_skill_points'] ?? 0)
                    : ($dbMaxSkillLevels[$exp] ?? 0),
            ];
        }

        return $expansionProgress;
    }

    /**
     * @param  Collection<int, WowRecipe>  $expansionRecipes
     * @param  list<int>  $knownRecipeIds
     * @return list<RecipeCategory>
     */
    private function buildCategoryProgress(Collection $expansionRecipes, array $knownRecipeIds): array
    {
        $categoryProgress = [];
        /** @var Collection<string, Collection<int, WowRecipe>> $recipesByCategory */
        $recipesByCategory = $expansionRecipes->groupBy('category_name');

        foreach ($recipesByCategory as $catName => $catRecipes) {
            if (empty($catName)) {
                continue;
            }

            $items = $this->buildRecipeItems($catRecipes, $knownRecipeIds);
            $items = $this->deduplicateRankedRecipes($items);

            $categoryProgress[] = [
                'name' => $catName,
                'total' => count($items),
                'completed' => count(array_filter($items, fn (array $item) => $item['is_completed'])),
                'items' => $items,
            ];
        }

        return $categoryProgress;
    }

    /**
     * @param  Collection<int, WowRecipe>  $recipes
     * @param  list<int>  $knownRecipeIds
     * @return list<array{id: int, name: string, is_completed: bool, wowhead_spell_id: int|null}>
     */
    private function buildRecipeItems(Collection $recipes, array $knownRecipeIds): array
    {
        $items = [];
        foreach ($recipes as $recipe) {
            $items[] = [
                'id' => $recipe->id,
                'name' => $recipe->name_fr,
                'is_completed' => in_array($recipe->id, $knownRecipeIds),
                'wowhead_spell_id' => $recipe->wowhead_spell_id,
            ];
        }

        return $items;
    }

    /**
     * @param  list<array{id: int, name: string, is_completed: bool, wowhead_spell_id: int|null}>  $items
     * @return list<array{id: int, name: string, is_completed: bool, wowhead_spell_id: int|null}>
     */
    private function deduplicateRankedRecipes(array $items): array
    {
        /** @var array<string, list<array{id: int, name: string, is_completed: bool, wowhead_spell_id: int|null}>> $groups */
        $groups = [];
        foreach ($items as $item) {
            $groups[$item['name']][] = $item;
        }

        $result = [];
        foreach ($groups as $group) {
            if (count($group) === 1) {
                $result[] = $group[0];

                continue;
            }

            usort($group, fn (array $a, array $b): int => $b['id'] <=> $a['id']);

            $picked = $group[0];
            foreach ($group as $entry) {
                if ($entry['is_completed']) {
                    $picked = $entry;

                    break;
                }
            }

            $result[] = $picked;
        }

        return $result;
    }
}
