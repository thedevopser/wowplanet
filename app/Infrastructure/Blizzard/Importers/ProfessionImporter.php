<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Importers;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Concerns\ImportsFromBlizzardApi;
use App\Infrastructure\Blizzard\ExpansionTierMatcher;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;
use App\Models\WowProfession;
use App\Models\WowRecipe;

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
        if (! $index instanceof \App\Infrastructure\Blizzard\Responses\ResponsePayload) {
            $this->info('ERROR: Could not fetch profession index.');

            return;
        }

        $professions = $this->fetchProfessions($index->objectList('professions'));
        if ($professions === []) {
            $this->info('ERROR: No primary/secondary professions found.');

            return;
        }

        $this->info(sprintf('Found %d professions. Fetching skill tiers...', count($professions)));

        $recipes = $this->fetchRecipes($professions, $recipeFactionMap);
        $this->info(sprintf('Built %d recipes.', count($recipes)));

        $this->saveProfessions($professions);
        $this->saveRecipes($recipes);

        $this->info(sprintf('Profession import complete: %d professions, %d recipes.', count($professions), count($recipes)));
    }

    /**
     * Détails des métiers, filtrés sur les types PRIMARY/SECONDARY (écarte les
     * pseudo-métiers d'événements).
     *
     * @param  list<ResponsePayload>  $indexEntries
     * @return array<int, array{id: int, name_fr: string, type: string, tiers: list<array{id: int, name: string}>}>
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
        foreach ($details as $id => $detail) {
            if ($detail === null) {
                continue;
            }

            $typeValue = strtoupper($detail->optionalObject('type')?->optionalString('type') ?? '');
            if (! in_array($typeValue, ['PRIMARY', 'SECONDARY'], true)) {
                continue;
            }

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

            $professions[(int) $id] = [
                'id' => (int) $id,
                'name_fr' => $name,
                'type' => strtolower($typeValue),
                'tiers' => $tiers,
            ];
        }

        return $professions;
    }

    /**
     * Recettes par tier : catégories → recettes, extension déduite du nom du tier.
     *
     * @param  array<int, array{id: int, name_fr: string, type: string, tiers: list<array{id: int, name: string}>}>  $professions
     * @param  array<int, string>  $recipeFactionMap
     * @return list<array{id: int, name_fr: string, profession_id: int, expansion_id: int, category_name: string, faction: string|null, is_active: bool}>
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
                    'expansion_id' => ExpansionTierMatcher::match($tier['name']) ?? 0,
                ];
            }
        }

        $tierDetails = $this->fetchBatchAsync($endpoints);

        $recipes = [];
        foreach ($tierDetails as $key => $tierDetail) {
            if ($tierDetail === null) {
                continue;
            }

            if (! isset($tierMeta[$key])) {
                continue;
            }

            $meta = $tierMeta[$key];

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
                        'expansion_id' => $meta['expansion_id'],
                        'category_name' => $categoryName,
                        'faction' => $recipeFactionMap[$recipeId] ?? null,
                        'is_active' => true,
                    ];
                }
            }
        }

        return array_values($recipes);
    }

    /**
     * @param  array<int, array{id: int, name_fr: string, type: string, tiers: list<array{id: int, name: string}>}>  $professions
     */
    private function saveProfessions(array $professions): void
    {
        /** @var \Illuminate\Support\Collection<int, WowProfession> $stored */
        $stored = WowProfession::query()
            ->whereIn('id', array_column($professions, 'id'))
            ->get()
            ->keyBy('id');

        $rows = [];

        foreach ($professions as $profession) {
            /** @var WowProfession|null $existing */
            $existing = $stored->get($profession['id']);

            if ($existing instanceof WowProfession
                && $existing->name_fr === $profession['name_fr']
                && $existing->type === $profession['type']
                && $existing->is_active) {
                continue;
            }

            $rows[] = [
                'id' => $profession['id'],
                'name_fr' => $profession['name_fr'],
                'type' => $profession['type'],
                'max_skill_levels' => json_encode([]),
                'is_active' => true,
            ];
        }

        if ($rows !== []) {
            WowProfession::query()->upsert(
                $rows,
                uniqueBy: ['id'],
                update: ['name_fr', 'type', 'is_active'],
            );
        }

        $this->info(sprintf('%d professions, %d written.', count($professions), count($rows)));
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
            /** @var \Illuminate\Support\Collection<int, WowRecipe> $stored */
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
