<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\ValueObjects\ExpansionId;
use App\Models\WowAchievement;
use App\Models\WowAppearance;
use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;
use App\Models\WowProfession;
use App\Models\WowQuest;
use App\Models\WowRecipe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Requêtes de la base de données WoW (montures, hauts-faits, quêtes, mascottes,
 * décorations, garde-robe, professions). Source des données des pages Inertia
 * servies par DatabaseController.
 *
 * @phpstan-type CategoryEntry array{name: string, slug: string, count: int}
 * @phpstan-type ExpansionEntry array{id: int, name: string, slug: string, count: int}
 */
class DatabaseQueryService
{
    /**
     * Libellés FR des slots transmog (clé = valeur stockée en base).
     *
     * @var array<string, string>
     */
    private const SLOT_LABELS = [
        'HEAD' => 'Tête',
        'SHOULDER' => 'Épaules',
        'SHIRT' => 'Chemise',
        'CHEST' => 'Torse',
        'WAIST' => 'Ceinture',
        'LEGS' => 'Jambes',
        'FEET' => 'Pieds',
        'WRIST' => 'Poignets',
        'HAND' => 'Mains',
        'CLOAK' => 'Cape',
        'TABARD' => 'Tabard',
        'WEAPON' => 'Arme',
        'SHIELD' => 'Bouclier',
        'RANGED' => 'Distance',
        'TWOHWEAPON' => 'Arme à deux mains',
        'WEAPONOFFHAND' => 'Arme en main gauche',
        'HOLDABLE' => 'Tenu en main gauche',
    ];

    private const DEFAULT_PER_PAGE = 50;

    private const MAX_PER_PAGE = 100;

    private const COUNTS_CACHE_KEY = 'database_sidebar_counts';

    private const SUBCATEGORIES_CACHE_KEY = 'database_sidebar_subcategories';

    /**
     * Sections dont la sidebar affiche les sous-catégories (accordéon).
     *
     * @var list<string>
     */
    private const SIDEBAR_SECTIONS = ['mounts', 'achievements', 'quests', 'pets', 'decors', 'appearances', 'professions'];

    // The catalogue only changes on an admin import: an hour saves some fifteen aggregate queries per page.
    private const COUNTS_CACHE_SECONDS = 3600;

    /**
     * @return array{items: \Illuminate\Database\Eloquent\Collection<int, WowMount>, categories: list<CategoryEntry>, total: int}
     */
    public function mounts(?string $category = null): array
    {
        $builder = WowMount::query()->where('is_active', true)
            ->select(['id', 'name_fr', 'source', 'category', 'source_spell_id', 'icon_url'])
            ->orderBy('name_fr');

        if ($category !== null && $category !== '') {
            $builder->where('category', $this->deSlugify($category, 'mounts'));
        }

        return [
            'items' => $builder->get(),
            'categories' => $this->buildCategoryList(WowMount::class),
            'total' => WowMount::query()->where('is_active', true)->count(),
        ];
    }

    /**
     * @return array{items: array<int, WowAchievement>, expansions: list<ExpansionEntry>, total: int, current_page: int, last_page: int, per_page: int}
     */
    public function achievements(?string $expansionSlug = null, ?string $search = null, ?int $page = null, ?int $perPage = null): array
    {
        $builder = WowAchievement::query()->where('is_active', true)
            ->select(['id', 'name_fr', 'expansion_id', 'category_name', 'icon_url', 'points', 'faction'])
            ->orderBy('name_fr');

        $this->applyExpansion($builder, $expansionSlug);
        $this->applySearch($builder, $search);

        $lengthAwarePaginator = $builder->paginate($this->resolvePerPage($perPage), ['*'], 'page', $page);

        return [
            'items' => $lengthAwarePaginator->items(),
            'expansions' => $this->buildExpansionList(WowAchievement::class),
            'total' => $lengthAwarePaginator->total(),
            'current_page' => $lengthAwarePaginator->currentPage(),
            'last_page' => $lengthAwarePaginator->lastPage(),
            'per_page' => $lengthAwarePaginator->perPage(),
        ];
    }

    /**
     * @return array{items: array<int, WowQuest>, expansions: list<ExpansionEntry>, total: int, current_page: int, last_page: int, per_page: int}
     */
    public function quests(?string $expansionSlug = null, ?string $search = null, ?int $page = null, ?int $perPage = null): array
    {
        $builder = WowQuest::query()->where('is_active', true)
            ->select(['id', 'name_fr', 'expansion_id', 'zone_name', 'faction'])
            ->orderBy('name_fr');

        $this->applyExpansion($builder, $expansionSlug);
        $this->applySearch($builder, $search);

        $lengthAwarePaginator = $builder->paginate($this->resolvePerPage($perPage), ['*'], 'page', $page);

        return [
            'items' => $lengthAwarePaginator->items(),
            'expansions' => $this->buildExpansionList(WowQuest::class),
            'total' => $lengthAwarePaginator->total(),
            'current_page' => $lengthAwarePaginator->currentPage(),
            'last_page' => $lengthAwarePaginator->lastPage(),
            'per_page' => $lengthAwarePaginator->perPage(),
        ];
    }

    /**
     * @return array{items: \Illuminate\Database\Eloquent\Collection<int, WowPet>, categories: list<CategoryEntry>, total: int}
     */
    public function pets(?string $category = null): array
    {
        $builder = WowPet::query()->where('is_active', true)
            ->select(['id', 'name_fr', 'source', 'category', 'creature_id', 'icon_url'])
            ->orderBy('name_fr');

        if ($category !== null && $category !== '') {
            $builder->where('category', $this->deSlugify($category, 'pets'));
        }

        return [
            'items' => $builder->get(),
            'categories' => $this->buildCategoryList(WowPet::class),
            'total' => WowPet::query()->where('is_active', true)->count(),
        ];
    }

    /**
     * @return array{items: \Illuminate\Database\Eloquent\Collection<int, WowDecor>, categories: list<CategoryEntry>, total: int}
     */
    public function decors(?string $category = null): array
    {
        $builder = WowDecor::query()->where('is_active', true)
            ->select(['id', 'name_fr', 'source', 'category', 'item_id', 'icon_url'])
            ->orderBy('name_fr');

        if ($category !== null && $category !== '') {
            $builder->where('category', $this->deSlugify($category, 'decors'));
        }

        return [
            'items' => $builder->get(),
            'categories' => $this->buildCategoryList(WowDecor::class),
            'total' => WowDecor::query()->where('is_active', true)->count(),
        ];
    }

    /**
     * @return array{items: array<int, WowAppearance>, slots: list<CategoryEntry>, total: int, current_page: int, last_page: int, per_page: int}
     */
    public function appearances(?string $slot = null, ?string $quality = null, ?string $search = null, ?int $page = null, ?int $perPage = null): array
    {
        $builder = WowAppearance::query()->where('is_active', true)
            ->select(['id', 'name_fr', 'slot', 'category', 'quality', 'item_id', 'icon_url'])
            ->orderBy('name_fr');

        if ($slot !== null && $slot !== '') {
            $builder->where('slot', $this->deSlugify($slot, 'appearances'));
        }

        if ($quality !== null && $quality !== '') {
            $builder->where('quality', (int) $quality);
        }

        $this->applySearch($builder, $search);

        $lengthAwarePaginator = $builder->paginate($this->resolvePerPage($perPage), ['*'], 'page', $page);

        return [
            'items' => $lengthAwarePaginator->items(),
            'slots' => $this->buildSlotList(),
            'total' => $lengthAwarePaginator->total(),
            'current_page' => $lengthAwarePaginator->currentPage(),
            'last_page' => $lengthAwarePaginator->lastPage(),
            'per_page' => $lengthAwarePaginator->perPage(),
        ];
    }

    /**
     * @return array{professions: array<int, array{id: int, name_fr: string, type: string, slug: string, recipe_count: int}>, total_professions: int, total_recipes: int}
     */
    public function professions(): array
    {
        $result = WowProfession::query()->where('is_active', true)
            ->select(['id', 'name_fr', 'type'])
            ->orderBy('name_fr')
            ->get()
            ->map(fn (WowProfession $wowProfession): array => [
                'id' => $wowProfession->id,
                'name_fr' => $wowProfession->name_fr,
                'type' => $wowProfession->type,
                'slug' => $this->slugify($wowProfession->name_fr),
                'recipe_count' => WowRecipe::query()->where('is_active', true)
                    ->where('profession_id', $wowProfession->id)
                    ->count(),
            ])
            ->all();

        return [
            'professions' => $result,
            'total_professions' => WowProfession::query()->where('is_active', true)->count(),
            'total_recipes' => WowRecipe::query()->where('is_active', true)->count(),
        ];
    }

    /**
     * Une profession inconnue rend une page vide, sans pagination.
     *
     * @return array{items: array{}, expansions: array{}}|array{items: array<int, WowRecipe>, expansions: list<ExpansionEntry>, profession: array{id: int, name_fr: string, type: string}, total: int, current_page: int, last_page: int, per_page: int}
     */
    public function professionRecipes(?string $professionSlug = null, ?string $expansionSlug = null, ?string $search = null, ?int $page = null, ?int $perPage = null): array
    {
        if ($professionSlug === null || $professionSlug === '') {
            return ['items' => [], 'expansions' => []];
        }

        $professionName = $this->deSlugify($professionSlug, 'professions');
        $profession = WowProfession::query()->where('is_active', true)
            ->where('name_fr', $professionName)
            ->first();

        if (! $profession instanceof WowProfession) {
            return ['items' => [], 'expansions' => []];
        }

        $builder = WowRecipe::query()->where('is_active', true)
            ->where('profession_id', $profession->id)
            ->select(['id', 'name_fr', 'expansion_id', 'category_name', 'faction', 'wowhead_spell_id'])
            ->orderBy('name_fr');

        $this->applyExpansion($builder, $expansionSlug);
        $this->applySearch($builder, $search);

        $lengthAwarePaginator = $builder->paginate($this->resolvePerPage($perPage), ['*'], 'page', $page);

        return [
            'items' => $lengthAwarePaginator->items(),
            'expansions' => $this->buildExpansionList(WowRecipe::class, $profession->id),
            'profession' => [
                'id' => $profession->id,
                'name_fr' => $profession->name_fr,
                'type' => $profession->type,
            ],
            'total' => $lengthAwarePaginator->total(),
            'current_page' => $lengthAwarePaginator->currentPage(),
            'last_page' => $lengthAwarePaginator->lastPage(),
            'per_page' => $lengthAwarePaginator->perPage(),
        ];
    }

    /**
     * Sous-catégories d'une section pour la sidebar. Retourne null si la section est inconnue.
     *
     * @return array<int, array{name: string, slug: string, count: int, type?: string}>|null
     */
    public function subcategories(string $section): ?array
    {
        return match ($section) {
            'mounts' => $this->buildCategoryList(WowMount::class),
            'pets' => $this->buildCategoryList(WowPet::class),
            'decors' => $this->buildCategoryList(WowDecor::class),
            'appearances' => $this->buildSlotList(),
            'achievements' => array_map(fn (array $e): array => [
                'name' => $e['name'],
                'slug' => $e['slug'],
                'count' => $e['count'],
            ], $this->buildExpansionList(WowAchievement::class)),
            'quests' => array_map(fn (array $e): array => [
                'name' => $e['name'],
                'slug' => $e['slug'],
                'count' => $e['count'],
            ], $this->buildExpansionList(WowQuest::class)),
            'professions' => WowProfession::query()->where('is_active', true)
                ->select(['id', 'name_fr', 'type'])
                ->orderBy('name_fr')
                ->get()
                ->map(fn (WowProfession $wowProfession): array => [
                    'name' => $wowProfession->name_fr,
                    'slug' => $this->slugify($wowProfession->name_fr),
                    'count' => WowRecipe::query()->where('is_active', true)
                        ->where('profession_id', $wowProfession->id)->count(),
                    'type' => $wowProfession->type,
                ])
                ->all(),
            default => null,
        };
    }

    /**
     * @return array<string, int>
     */
    public function cachedCounts(): array
    {
        return Cache::remember(self::COUNTS_CACHE_KEY, self::COUNTS_CACHE_SECONDS, $this->counts(...));
    }

    /**
     * @return array<string, array<int, array{name: string, slug: string, count: int, type?: string}>>
     */
    public function cachedSubcategories(): array
    {
        return Cache::remember(self::SUBCATEGORIES_CACHE_KEY, self::COUNTS_CACHE_SECONDS, function (): array {
            $map = [];
            foreach (self::SIDEBAR_SECTIONS as $section) {
                $map[$section] = $this->subcategories($section) ?? [];
            }

            return $map;
        });
    }

    /**
     * A taxonomy arbitration moves entries between categories outside of any import: the
     * sidebar must not keep showing the old ranking for the rest of the hour.
     */
    public function forgetSidebar(): void
    {
        Cache::forget(self::COUNTS_CACHE_KEY);
        Cache::forget(self::SUBCATEGORIES_CACHE_KEY);
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        return [
            'mounts' => WowMount::query()->where('is_active', true)->count(),
            'achievements' => WowAchievement::query()->where('is_active', true)->count(),
            'quests' => WowQuest::query()->where('is_active', true)->count(),
            'pets' => WowPet::query()->where('is_active', true)->count(),
            'decors' => WowDecor::query()->where('is_active', true)->count(),
            'appearances' => WowAppearance::query()->where('is_active', true)->count(),
            'professions' => WowProfession::query()->where('is_active', true)->count(),
            'recipes' => WowRecipe::query()->where('is_active', true)->count(),
        ];
    }

    private function resolvePerPage(?int $perPage): int
    {
        return min($perPage ?: self::DEFAULT_PER_PAGE, self::MAX_PER_PAGE);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $builder
     */
    private function applyExpansion(Builder $builder, ?string $expansionSlug): void
    {
        if ($expansionSlug === null || $expansionSlug === '') {
            return;
        }

        $expansion = ExpansionId::fromSlug($expansionSlug);
        if ($expansion instanceof ExpansionId) {
            $builder->where('expansion_id', $expansion->value);
        }
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $builder
     */
    private function applySearch(Builder $builder, ?string $search): void
    {
        if ($search !== null && $search !== '') {
            // PostgreSQL makes LIKE case-sensitive where SQLite did not: without
            // this, searching "bouclier" would stop matching "Bouclier".
            $builder->whereLike('name_fr', '%'.$search.'%', caseSensitive: false);
        }
    }

    /**
     * Liste des slots transmog pour la sous-navigation (ex: Tête, Épaules…).
     *
     * @return list<array{name: string, slug: string, count: int}>
     */
    private function buildSlotList(): array
    {
        /** @var array<int, array{slot: string, items_count: int}> $rawSlots */
        $rawSlots = WowAppearance::query()->where('is_active', true)
            ->whereNotNull('slot')
            ->selectRaw('slot, COUNT(*) as items_count')
            ->groupBy('slot')
            ->orderBy('slot')
            ->get()
            ->toArray();

        return array_values(array_map(fn (array $row): array => [
            'name' => self::SLOT_LABELS[$row['slot']] ?? $row['slot'],
            'slug' => $this->slugify($row['slot']),
            'count' => $row['items_count'],
        ], $rawSlots));
    }

    /**
     * @param  class-string<WowAchievement|WowQuest|WowRecipe>  $modelClass
     * @return list<array{id: int, name: string, slug: string, count: int}>
     */
    private function buildExpansionList(string $modelClass, ?int $professionId = null): array
    {
        $expansions = [];

        foreach (ExpansionId::allSlugs() as $id => $slug) {
            /** @var Builder<WowAchievement|WowQuest|WowRecipe> $query */
            $query = $modelClass::query()->where('is_active', true)->where('expansion_id', $id);

            if ($professionId !== null) {
                $query->where('profession_id', $professionId);
            }

            $count = $query->count();
            if ($count > 0) {
                $expansions[] = [
                    'id' => $id,
                    'name' => (new ExpansionId($id))->toString(),
                    'slug' => $slug,
                    'count' => $count,
                ];
            }
        }

        return $expansions;
    }

    /**
     * @param  class-string<WowMount|WowPet|WowDecor>  $modelClass
     * @return list<array{name: string, slug: string, count: int}>
     */
    private function buildCategoryList(string $modelClass): array
    {
        /** @var array<int, array{category: string, items_count: int}> $rawCategories */
        $rawCategories = $modelClass::query()->where('is_active', true)
            ->whereNotNull('category')
            ->selectRaw('category, COUNT(*) as items_count')
            ->groupBy('category')
            ->orderBy('category')
            ->get()
            ->toArray();

        return array_values(array_map(fn (array $row): array => [
            'name' => $row['category'],
            'slug' => $this->slugify($row['category']),
            'count' => $row['items_count'],
        ], $rawCategories));
    }

    private function slugify(string $text): string
    {
        $slug = mb_strtolower($text);
        $slug = str_replace(
            [' ', "'", "\u{2019}", ':', '(', ')', ',', '.'],
            ['-', '-', '-', '', '', '', '', ''],
            $slug,
        );
        $slug = (string) preg_replace('/[^a-z0-9\-]/', '-', $slug);
        $slug = (string) preg_replace('/-+/', '-', $slug);

        return trim($slug, '-');
    }

    private function deSlugify(string $slug, string $context): string
    {
        /** @var list<string> $candidates */
        $candidates = match ($context) {
            'mounts' => WowMount::query()->where('is_active', true)
                ->whereNotNull('category')
                ->distinct()
                ->pluck('category')
                ->all(),
            'pets' => WowPet::query()->where('is_active', true)
                ->whereNotNull('category')
                ->distinct()
                ->pluck('category')
                ->all(),
            'decors' => WowDecor::query()->where('is_active', true)
                ->whereNotNull('category')
                ->distinct()
                ->pluck('category')
                ->all(),
            'appearances' => WowAppearance::query()->where('is_active', true)
                ->whereNotNull('slot')
                ->distinct()
                ->pluck('slot')
                ->all(),
            'professions' => WowProfession::query()->where('is_active', true)
                ->pluck('name_fr')
                ->all(),
            default => [],
        };

        foreach ($candidates as $candidate) {
            if ($this->slugify($candidate) === $slug) {
                return $candidate;
            }
        }

        $text = str_replace('-', ' ', $slug);

        return mb_convert_case($text, MB_CASE_TITLE, 'UTF-8');
    }
}
