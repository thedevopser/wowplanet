<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Importers;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Concerns\ImportsFromBlizzardApi;
use App\Infrastructure\Blizzard\ExpansionTierMatcher;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;
use App\Models\WowProfession;
use App\Models\WowRecipe;
use Illuminate\Support\Collection;

/**
 * Importe métiers et recettes depuis l'API officielle Blizzard (Profession API).
 *
 * Pipeline : index des métiers → détail par métier (type, skill tiers) → détail
 * par tier (catégories → recettes, id = SkillLineAbility, noms FR). L'extension
 * d'une recette vient du nom de son tier via ExpansionTierMatcher.
 */
final readonly class ProfessionImporter
{
    use ImportsFromBlizzardApi;

    // @pest-mutate-ignore
    private const string TYPE_PRIMARY = 'PRIMARY';

    // @pest-mutate-ignore
    private const string TYPE_SECONDARY = 'SECONDARY';

    // @pest-mutate-ignore
    private const int DEACTIVATION_CHUNK = 1000;

    public function __construct(
        BlizzardApiClient $blizzardApiClient,
    ) {
        $this->blizzardApiClient = $blizzardApiClient;
    }

    /**
     * @param  array<int, string>  $recipeFactionMap  [recipeId => 'Alliance'|'Horde']
     */
    public function import(array $recipeFactionMap = []): void
    {
        $this->info('Fetching profession index from Blizzard API...');
        $this->info(sprintf('  Recipe faction map: %d entries', count($recipeFactionMap)));

        $index = $this->fetchWithRetry('data/wow/profession/index');
        if (! $index instanceof ResponsePayload) {
            $this->info('ERROR: Could not fetch profession index.');

            return;
        }

        ['professions' => $professions, 'unresolved' => $unresolved] = $this->fetchProfessions($index->objectList('professions'));
        if ($professions === []) {
            $this->info('ERROR: No primary/secondary professions found.');

            return;
        }

        $this->info(sprintf('Found %d professions. Fetching skill tiers...', count($professions)));

        ['recipes' => $recipes, 'incomplete' => $incomplete, 'skill_levels' => $skillLevels] = $this->fetchRecipes($professions, $recipeFactionMap);
        $this->info(sprintf('Built %d recipes.', count($recipes)));

        $this->saveProfessions($professions, $skillLevels, $incomplete);
        $this->deactivateProfessionsNotRetained(array_keys($professions), $unresolved);
        $this->saveRecipes($recipes);
        $this->deactivateRecipesNotReturned(array_column($recipes, 'id'), [...$unresolved, ...$incomplete]);

        $this->info(sprintf('Profession import complete: %d professions, %d recipes.', count($professions), count($recipes)));
    }

    /**
     * Détails des métiers retenus par `isTrackedProfession()`. Les identifiants dont
     * le détail n'a pas répondu sont rendus à part : leur absence ne prouve rien.
     *
     * @param  list<ResponsePayload>  $indexEntries
     * @return array{professions: array<int, array{id: int, name_fr: string, type: string, tiers: list<array{id: int, name: string}>}>, unresolved: list<int>}
     */
    private function fetchProfessions(array $indexEntries): array
    {
        $endpoints = [];
        foreach ($indexEntries as $indexEntry) {
            $id = $indexEntry->optionalInt('id') ?? 0;
            if ($id > 0) {
                $endpoints[$id] = 'data/wow/profession/'.$id;
            }
        }

        $details = $this->fetchBatchAsync($endpoints);

        $professions = [];
        $unresolved = [];
        foreach ($details as $id => $detail) {
            if ($detail === null) {
                $unresolved[] = (int) $id;

                continue;
            }

            $typeValue = strtoupper($detail->optionalObject('type')?->optionalString('type') ?? '');
            $name = trim($detail->lenientString('name') ?? '');
            if ($name === '') {
                continue;
            }

            $tiers = [];
            foreach ($detail->objectList('skill_tiers') as $rawTier) {
                $tierId = $rawTier->optionalInt('id') ?? 0;
                if ($tierId > 0) {
                    $tiers[] = ['id' => $tierId, 'name' => trim($rawTier->optionalString('name') ?? '')];
                }
            }

            if (! $this->isTrackedProfession($typeValue, $tiers)) {
                continue;
            }

            $professions[(int) $id] = [
                'id' => (int) $id,
                'name_fr' => $name,
                'type' => strtolower($typeValue),
                'tiers' => $tiers,
            ];
        }

        return ['professions' => $professions, 'unresolved' => $unresolved];
    }

    /**
     * Blizzard type PRIMARY des artisanats annexes (Suture abominable, Préparation de
     * teinture…) que l'API laisse sans palier, donc sans recette ni progression. Un
     * métier secondaire reste suivi sans palier : l'Archéologie n'en a pas.
     *
     * @param  list<array{id: int, name: string}>  $tiers
     */
    private function isTrackedProfession(string $type, array $tiers): bool
    {
        return match ($type) {
            self::TYPE_SECONDARY => true,
            self::TYPE_PRIMARY => $tiers !== [],
            default => false,
        };
    }

    /**
     * Recettes par tier : catégories → recettes, extension déduite du nom du tier.
     * Un métier dont un palier n'a pas répondu est rendu dans `incomplete`. Le niveau
     * maximal de chaque palier est relevé par extension, sans les paliers non reconnus.
     *
     * @param  array<int, array{id: int, name_fr: string, type: string, tiers: list<array{id: int, name: string}>}>  $professions
     * @param  array<int, string>  $recipeFactionMap
     * @return array{recipes: list<array{id: int, name_fr: string, profession_id: int, expansion_id: int, category_name: string, faction: string|null, is_active: bool}>, incomplete: list<int>, skill_levels: array<int, array<int, int>>}
     */
    private function fetchRecipes(array $professions, array $recipeFactionMap): array
    {
        $endpoints = [];
        $tierMeta = [];
        foreach ($professions as $profession) {
            foreach ($profession['tiers'] as $tier) {
                $key = $profession['id'].'-'.$tier['id'];
                $endpoints[$key] = sprintf('data/wow/profession/%d/skill-tier/%d', $profession['id'], $tier['id']);
                $tierMeta[$key] = [
                    'profession_id' => $profession['id'],
                    'matched_expansion_id' => ExpansionTierMatcher::match($tier['name']),
                ];
            }
        }

        $tierDetails = $this->fetchBatchAsync($endpoints);

        $recipes = [];
        $incomplete = [];
        $skillLevels = [];
        foreach ($tierDetails as $key => $tierDetail) {
            if (! isset($tierMeta[$key])) {
                continue;
            }

            $meta = $tierMeta[$key];

            if ($tierDetail === null) {
                $incomplete[$meta['profession_id']] = $meta['profession_id'];

                continue;
            }

            $maximumSkillLevel = $tierDetail->optionalInt('maximum_skill_level');
            if ($meta['matched_expansion_id'] !== null && $maximumSkillLevel !== null) {
                $skillLevels[$meta['profession_id']][$meta['matched_expansion_id']] = $maximumSkillLevel;
            }

            $expansionId = $meta['matched_expansion_id'] ?? 0;

            foreach ($tierDetail->objectList('categories') as $category) {
                $categoryName = trim($category->optionalString('name') ?? '');
                foreach ($category->objectList('recipes') as $recipe) {
                    $recipeId = $recipe->optionalInt('id') ?? 0;
                    $recipeName = trim($recipe->optionalString('name') ?? '');
                    if ($recipeId <= 0) {
                        continue;
                    }

                    if ($recipeName === '') {
                        continue;
                    }

                    $recipes[$recipeId] = [
                        'id' => $recipeId,
                        'name_fr' => $recipeName,
                        'profession_id' => $meta['profession_id'],
                        'expansion_id' => $expansionId,
                        'category_name' => $categoryName,
                        'faction' => $recipeFactionMap[$recipeId] ?? null,
                        'is_active' => true,
                    ];
                }
            }
        }

        return ['recipes' => array_values($recipes), 'incomplete' => array_values($incomplete), 'skill_levels' => $skillLevels];
    }

    /**
     * @param  array<int, array{id: int, name_fr: string, type: string, tiers: list<array{id: int, name: string}>}>  $professions
     * @param  array<int, array<int, int>>  $skillLevels
     * @param  list<int>  $incomplete
     */
    private function saveProfessions(array $professions, array $skillLevels, array $incomplete): void
    {
        /** @var Collection<int, WowProfession> $stored */
        $stored = WowProfession::query()
            ->whereIn('id', array_column($professions, 'id'))
            ->get()
            ->keyBy('id');

        $rows = [];

        foreach ($professions as $profession) {
            /** @var WowProfession|null $existing */
            $existing = $stored->get($profession['id']);

            // A tier that failed to answer keeps the ceiling already known for its expansion.
            $knownLevels = in_array($profession['id'], $incomplete, true) ? ($existing->max_skill_levels ?? []) : [];
            $maxSkillLevels = $this->sortedByExpansion(array_replace($knownLevels, $skillLevels[$profession['id']] ?? []));

            if ($existing instanceof WowProfession
                && $existing->name_fr === $profession['name_fr']
                && $existing->type === $profession['type']
                && $this->sortedByExpansion($existing->max_skill_levels ?? []) === $maxSkillLevels
                && $existing->is_active) {
                continue;
            }

            $rows[] = [
                'id' => $profession['id'],
                'name_fr' => $profession['name_fr'],
                'type' => $profession['type'],
                'max_skill_levels' => json_encode($maxSkillLevels, JSON_THROW_ON_ERROR),
                'is_active' => true,
            ];
        }

        if ($rows !== []) {
            WowProfession::query()->upsert(
                $rows,
                uniqueBy: ['id'],
                update: ['name_fr', 'type', 'max_skill_levels', 'is_active'],
            );
        }

        $this->info(sprintf('%d professions, %d written.', count($professions), count($rows)));
    }

    /**
     * @param  array<int, int>  $levels
     * @return array<int, int>
     */
    private function sortedByExpansion(array $levels): array
    {
        ksort($levels);

        return $levels;
    }

    /**
     * @param  list<int>  $retainedIds
     * @param  list<int>  $unresolvedIds
     */
    private function deactivateProfessionsNotRetained(array $retainedIds, array $unresolvedIds): void
    {
        /** @var list<int> $staleIds */
        $staleIds = WowProfession::query()
            ->where('is_active', true)
            ->whereNotIn('id', [...$retainedIds, ...$unresolvedIds])
            ->pluck('id')
            ->all();

        if ($staleIds !== []) {
            WowProfession::query()->whereIn('id', $staleIds)->update(['is_active' => false]);
        }

        $this->info(sprintf('%d professions deactivated.', count($staleIds)));
    }

    /**
     * `wowhead_spell_id` n'est pas exposé par l'API : la colonne est préservée
     * telle quelle (valeurs historiques), les nouvelles recettes restent sans lien
     * direct (le front retombe sur une recherche Wowhead par nom).
     *
     * @param  list<array{id: int, name_fr: string, profession_id: int, expansion_id: int, category_name: string, faction: string|null, is_active: bool}>  $recipes
     */
    private function saveRecipes(array $recipes): void
    {
        $written = 0;

        foreach (array_chunk($recipes, 500) as $chunk) {
            /** @var Collection<int, WowRecipe> $stored */
            $stored = WowRecipe::query()
                ->whereIn('id', array_column($chunk, 'id'))
                ->get()
                ->keyBy('id');

            $rows = [];

            foreach ($chunk as $recipe) {
                /** @var WowRecipe|null $existing */
                $existing = $stored->get($recipe['id']);

                // Même raison que pour les quêtes : `tagMirrorRecipeFactions()` déduit
                // une faction que la carte de référence ne porte pas, et l'écraser par
                // un nul à chaque passe lui ferait refaire son travail.
                $recipe['faction'] ??= $existing?->faction;

                if ($existing instanceof WowRecipe && $this->isRecipeUnchanged($existing, $recipe)) {
                    continue;
                }

                $rows[] = $recipe;
            }

            if ($rows === []) {
                continue;
            }

            WowRecipe::query()->upsert(
                $rows,
                uniqueBy: ['id'],
                update: ['name_fr', 'profession_id', 'expansion_id', 'category_name', 'faction', 'is_active'],
            );

            $written += count($rows);
        }

        $this->info(sprintf('  %d recipes written.', $written));
    }

    /**
     * Les recettes héritées d'une source antérieure à l'API ne disparaissent qu'ainsi.
     * Un métier dont un appel a échoué est épargné : son lot est partiel.
     *
     * @param  list<int>  $returnedIds
     * @param  list<int>  $uncertainProfessionIds
     */
    private function deactivateRecipesNotReturned(array $returnedIds, array $uncertainProfessionIds): void
    {
        $returned = array_flip($returnedIds);

        /** @var list<int> $staleIds */
        $staleIds = WowRecipe::query()
            ->where('is_active', true)
            ->whereNotIn('profession_id', $uncertainProfessionIds)
            ->pluck('id')
            ->reject(fn (int $id): bool => isset($returned[$id]))
            ->values()
            ->all();

        foreach (array_chunk($staleIds, self::DEACTIVATION_CHUNK) as $chunk) {
            WowRecipe::query()->whereIn('id', $chunk)->update(['is_active' => false]);
        }

        $this->info(sprintf('  %d recipes deactivated.', count($staleIds)));
    }

    /**
     * @param  array{id: int, name_fr: string, profession_id: int, expansion_id: int, category_name: string, faction: string|null, is_active: bool}  $recipe
     */
    private function isRecipeUnchanged(WowRecipe $wowRecipe, array $recipe): bool
    {
        return $wowRecipe->name_fr === $recipe['name_fr']
            && $wowRecipe->profession_id === $recipe['profession_id']
            && $wowRecipe->expansion_id === $recipe['expansion_id']
            && $wowRecipe->category_name === $recipe['category_name']
            && $wowRecipe->faction === $recipe['faction']
            && $wowRecipe->is_active === $recipe['is_active'];
    }

    public function tagMirrorRecipeFactions(): void
    {
        $this->info('Tagging mirror recipe pairs...');

        /** @var array<string, list<array{id: int, faction: string|null}>> $groups */
        $groups = [];
        foreach (WowRecipe::query()->where('is_active', true)->lazy() as $lazyCollection) {
            $key = $lazyCollection->name_fr.'|||'.$lazyCollection->profession_id.'|||'.$lazyCollection->expansion_id;
            $groups[$key][] = ['id' => $lazyCollection->id, 'faction' => $lazyCollection->faction];
        }

        $tagged = 0;
        foreach ($groups as $group) {
            if (count($group) !== 2) {
                continue;
            }

            $tagged += $this->tagMirrorPair($group[0], $group[1]);
        }

        $this->info(sprintf('Mirror recipe tagging complete: %d tagged.', $tagged));
    }

    /**
     * @param  array{id: int, faction: string|null}  $a
     * @param  array{id: int, faction: string|null}  $b
     */
    private function tagMirrorPair(array $a, array $b): int
    {
        if ($a['faction'] !== null && $b['faction'] === null) {
            $opposite = $a['faction'] === 'Alliance' ? 'Horde' : 'Alliance';
            WowRecipe::query()->where('id', $b['id'])->update(['faction' => $opposite]);

            return 1;
        }

        if ($b['faction'] !== null && $a['faction'] === null) {
            $opposite = $b['faction'] === 'Alliance' ? 'Horde' : 'Alliance';
            WowRecipe::query()->where('id', $a['id'])->update(['faction' => $opposite]);

            return 1;
        }

        return 0;
    }
}
