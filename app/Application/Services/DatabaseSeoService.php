<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\ValueObjects\ExpansionId;
use App\Http\Localization\AppLocale;
use App\Http\Localization\LocalizedUrls;
use App\Models\WowAchievement;
use App\Models\WowAppearance;
use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;
use App\Models\WowProfession;
use App\Models\WowQuest;
use App\Models\WowRecipe;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Number;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

/**
 * @phpstan-type BreadcrumbJsonLd array{'@type': string, itemListElement: list<array{'@type': string, position: int, name: string, item: string}>}
 * @phpstan-type CollectionPageJsonLd array{'@type': string, name: string, url: string, description: string, inLanguage: string, breadcrumb: BreadcrumbJsonLd}
 */
class DatabaseSeoService
{
    public function __construct(private readonly LocalizedUrls $localizedUrls) {}

    /**
     * @return array<string, string|null>
     */
    public function getIndexMeta(): array
    {
        $appUrl = $this->appUrl();

        $mountCount = WowMount::query()->where('is_active', true)->count();
        $achievementCount = WowAchievement::query()->where('is_active', true)->count();
        $questCount = WowQuest::query()->where('is_active', true)->count();
        $petCount = WowPet::query()->where('is_active', true)->count();
        $decorCount = WowDecor::query()->where('is_active', true)->count();
        $professionCount = WowProfession::query()->withActiveRecipes()->count();
        $recipeCount = WowRecipe::query()->where('is_active', true)->count();

        $title = __('seo.database.index.title');
        $description = __('seo.database.index.description', ['a' => $this->formatCount($mountCount), 'b' => $this->formatCount($achievementCount), 'c' => $this->formatCount($questCount), 'd' => $this->formatCount($petCount), 'e' => $this->formatCount($decorCount), 'f' => (string) $professionCount, 'g' => $this->formatCount($recipeCount)]);

        $canonicalUrl = $this->localizedUrls->current('/base-de-donnees');

        return $this->buildSeoArray($title, $description, $canonicalUrl, $appUrl, 'website', $this->buildJsonLd([
            '@type' => 'CollectionPage',
            'name' => $title,
            'url' => $canonicalUrl,
            'description' => $description,
            'inLanguage' => AppLocale::current()->value,
            'breadcrumb' => $this->buildBreadcrumbJsonLd($appUrl, [
                [__('seo.database.breadcrumb.index'), $canonicalUrl],
            ]),
        ], $appUrl));
    }

    /**
     * @return array<string, string|null>|null
     */
    public function getMountsMeta(?string $categorySlug): ?array
    {
        $appUrl = $this->appUrl();
        $builder = WowMount::query()->where('is_active', true);

        if ($categorySlug !== null) {
            $category = $this->deSlugifyCategory($categorySlug, 'mounts');
            $builder->where('category', $category);
            $count = $builder->count();

            if ($count === 0) {
                return null;
            }

            $title = __('seo.database.mounts.category.title', ['a' => $category, 'b' => (string) $count]);
            $description = __('seo.database.mounts.category.description', ['a' => (string) $count, 'b' => $category]);
            $canonicalUrl = $this->localizedUrls->current('/base-de-donnees/montures/'.$categorySlug);
            $breadcrumbs = [
                [__('seo.database.breadcrumb.index'), $this->localizedUrls->current('/base-de-donnees')],
                [__('seo.database.breadcrumb.mounts'), $this->localizedUrls->current('/base-de-donnees/montures')],
                [$category, $canonicalUrl],
            ];
        } else {
            $count = $builder->count();
            $title = __('seo.database.mounts.all.title', ['a' => $this->formatCount($count)]);
            $description = __('seo.database.mounts.all.description', ['a' => (string) $count]);
            $canonicalUrl = $this->localizedUrls->current('/base-de-donnees/montures');
            $breadcrumbs = [
                [__('seo.database.breadcrumb.index'), $this->localizedUrls->current('/base-de-donnees')],
                [__('seo.database.breadcrumb.mounts'), $canonicalUrl],
            ];
        }

        return $this->buildSeoArray($title, $description, $canonicalUrl, $appUrl, 'website', $this->buildJsonLd([
            '@type' => 'CollectionPage',
            'name' => $title,
            'url' => $canonicalUrl,
            'description' => $description,
            'inLanguage' => AppLocale::current()->value,
            'breadcrumb' => $this->buildBreadcrumbJsonLd($appUrl, $breadcrumbs),
        ], $appUrl));
    }

    /**
     * @return array<string, string|null>|null
     */
    public function getAchievementsMeta(?string $expansionSlug): ?array
    {
        $appUrl = $this->appUrl();

        if ($expansionSlug !== null) {
            $expansion = ExpansionId::fromSlug($expansionSlug);

            if (! $expansion instanceof ExpansionId) {
                return null;
            }

            $count = WowAchievement::query()->where('is_active', true)
                ->where('expansion_id', $expansion->value)
                ->count();

            $expansionName = $expansion->toString();
            $title = __('seo.database.achievements.expansion.title', ['a' => $expansionName, 'b' => (string) $count]);
            $description = __('seo.database.achievements.expansion.description', ['a' => (string) $count, 'b' => $expansionName]);
            $canonicalUrl = $this->localizedUrls->current('/base-de-donnees/hauts-faits/'.$expansionSlug);
            $breadcrumbs = [
                [__('seo.database.breadcrumb.index'), $this->localizedUrls->current('/base-de-donnees')],
                [__('seo.database.breadcrumb.achievements'), $this->localizedUrls->current('/base-de-donnees/hauts-faits')],
                [$expansionName, $canonicalUrl],
            ];
        } else {
            $count = WowAchievement::query()->where('is_active', true)->count();
            $title = __('seo.database.achievements.all.title', ['a' => $this->formatCount($count)]);
            $description = __('seo.database.achievements.all.description', ['a' => (string) $count]);
            $canonicalUrl = $this->localizedUrls->current('/base-de-donnees/hauts-faits');
            $breadcrumbs = [
                [__('seo.database.breadcrumb.index'), $this->localizedUrls->current('/base-de-donnees')],
                [__('seo.database.breadcrumb.achievements'), $canonicalUrl],
            ];
        }

        return $this->buildSeoArray($title, $description, $canonicalUrl, $appUrl, 'website', $this->buildJsonLd([
            '@type' => 'CollectionPage',
            'name' => $title,
            'url' => $canonicalUrl,
            'description' => $description,
            'inLanguage' => AppLocale::current()->value,
            'breadcrumb' => $this->buildBreadcrumbJsonLd($appUrl, $breadcrumbs),
        ], $appUrl));
    }

    /**
     * @return array<string, string|null>|null
     */
    public function getQuestsMeta(?string $expansionSlug): ?array
    {
        $appUrl = $this->appUrl();

        if ($expansionSlug !== null) {
            $expansion = ExpansionId::fromSlug($expansionSlug);

            if (! $expansion instanceof ExpansionId) {
                return null;
            }

            $expansionName = $expansion->toString();
            $count = WowQuest::query()->where('is_active', true)
                ->where('expansion_id', $expansion->value)
                ->count();

            $title = __('seo.database.quests.expansion.title', ['a' => $expansionName, 'b' => (string) $count]);
            $description = __('seo.database.quests.expansion.description', ['a' => (string) $count, 'b' => $expansionName]);
            $canonicalUrl = $this->localizedUrls->current('/base-de-donnees/quetes/'.$expansionSlug);
            $breadcrumbs = [
                [__('seo.database.breadcrumb.index'), $this->localizedUrls->current('/base-de-donnees')],
                [__('seo.database.breadcrumb.quests'), $this->localizedUrls->current('/base-de-donnees/quetes')],
                [$expansionName, $canonicalUrl],
            ];
        } else {
            $count = WowQuest::query()->where('is_active', true)->count();
            $title = __('seo.database.quests.all.title', ['a' => $this->formatCount($count)]);
            $description = __('seo.database.quests.all.description', ['a' => $this->formatCount($count)]);
            $canonicalUrl = $this->localizedUrls->current('/base-de-donnees/quetes');
            $breadcrumbs = [
                [__('seo.database.breadcrumb.index'), $this->localizedUrls->current('/base-de-donnees')],
                [__('seo.database.breadcrumb.quests'), $canonicalUrl],
            ];
        }

        return $this->buildSeoArray($title, $description, $canonicalUrl, $appUrl, 'website', $this->buildJsonLd([
            '@type' => 'CollectionPage',
            'name' => $title,
            'url' => $canonicalUrl,
            'description' => $description,
            'inLanguage' => AppLocale::current()->value,
            'breadcrumb' => $this->buildBreadcrumbJsonLd($appUrl, $breadcrumbs),
        ], $appUrl));
    }

    /**
     * @return array<string, string|null>|null
     */
    public function getPetsMeta(?string $categorySlug): ?array
    {
        $appUrl = $this->appUrl();
        $builder = WowPet::query()->where('is_active', true);

        if ($categorySlug !== null) {
            $category = $this->deSlugifyCategory($categorySlug, 'pets');
            $builder->where('category', $category);
            $count = $builder->count();

            if ($count === 0) {
                return null;
            }

            $title = __('seo.database.pets.category.title', ['a' => $category, 'b' => (string) $count]);
            $description = __('seo.database.pets.category.description', ['a' => (string) $count, 'b' => $category]);
            $canonicalUrl = $this->localizedUrls->current('/base-de-donnees/mascottes/'.$categorySlug);
            $breadcrumbs = [
                [__('seo.database.breadcrumb.index'), $this->localizedUrls->current('/base-de-donnees')],
                [__('seo.database.breadcrumb.pets'), $this->localizedUrls->current('/base-de-donnees/mascottes')],
                [$category, $canonicalUrl],
            ];
        } else {
            $count = $builder->count();
            $title = __('seo.database.pets.all.title', ['a' => $this->formatCount($count)]);
            $description = __('seo.database.pets.all.description', ['a' => (string) $count]);
            $canonicalUrl = $this->localizedUrls->current('/base-de-donnees/mascottes');
            $breadcrumbs = [
                [__('seo.database.breadcrumb.index'), $this->localizedUrls->current('/base-de-donnees')],
                [__('seo.database.breadcrumb.pets'), $canonicalUrl],
            ];
        }

        return $this->buildSeoArray($title, $description, $canonicalUrl, $appUrl, 'website', $this->buildJsonLd([
            '@type' => 'CollectionPage',
            'name' => $title,
            'url' => $canonicalUrl,
            'description' => $description,
            'inLanguage' => AppLocale::current()->value,
            'breadcrumb' => $this->buildBreadcrumbJsonLd($appUrl, $breadcrumbs),
        ], $appUrl));
    }

    /**
     * @return array<string, string|null>|null
     */
    public function getDecorsMeta(?string $categorySlug): ?array
    {
        $appUrl = $this->appUrl();
        $builder = WowDecor::query()->where('is_active', true);

        if ($categorySlug !== null) {
            $category = $this->deSlugifyCategory($categorySlug, 'decors');
            $builder->where('category', $category);
            $count = $builder->count();

            if ($count === 0) {
                return null;
            }

            $title = __('seo.database.decors.category.title', ['a' => $category, 'b' => (string) $count]);
            $description = __('seo.database.decors.category.description', ['a' => (string) $count, 'b' => $category]);
            $canonicalUrl = $this->localizedUrls->current('/base-de-donnees/decorations/'.$categorySlug);
            $breadcrumbs = [
                [__('seo.database.breadcrumb.index'), $this->localizedUrls->current('/base-de-donnees')],
                [__('seo.database.breadcrumb.decors'), $this->localizedUrls->current('/base-de-donnees/decorations')],
                [$category, $canonicalUrl],
            ];
        } else {
            $count = $builder->count();
            $title = __('seo.database.decors.all.title', ['a' => $this->formatCount($count)]);
            $description = __('seo.database.decors.all.description', ['a' => (string) $count]);
            $canonicalUrl = $this->localizedUrls->current('/base-de-donnees/decorations');
            $breadcrumbs = [
                [__('seo.database.breadcrumb.index'), $this->localizedUrls->current('/base-de-donnees')],
                [__('seo.database.breadcrumb.decors'), $canonicalUrl],
            ];
        }

        return $this->buildSeoArray($title, $description, $canonicalUrl, $appUrl, 'website', $this->buildJsonLd([
            '@type' => 'CollectionPage',
            'name' => $title,
            'url' => $canonicalUrl,
            'description' => $description,
            'inLanguage' => AppLocale::current()->value,
            'breadcrumb' => $this->buildBreadcrumbJsonLd($appUrl, $breadcrumbs),
        ], $appUrl));
    }

    /**
     * @return array<string, string|null>|null
     */
    public function getAppearancesMeta(?string $slotSlug): ?array
    {
        $appUrl = $this->appUrl();
        $builder = WowAppearance::query()->where('is_active', true);

        if ($slotSlug !== null) {
            $slot = $this->deSlugifyCategory($slotSlug, 'appearances');
            $builder->where('slot', $slot);
            $count = $builder->count();

            if ($count === 0) {
                return null;
            }

            $title = __('seo.database.appearances.slot.title', ['a' => $slot, 'b' => (string) $count]);
            $description = __('seo.database.appearances.slot.description', ['a' => (string) $count, 'b' => $slot]);
            $canonicalUrl = $this->localizedUrls->current('/base-de-donnees/garde-robe/'.$slotSlug);
            $breadcrumbs = [
                [__('seo.database.breadcrumb.index'), $this->localizedUrls->current('/base-de-donnees')],
                [__('seo.database.breadcrumb.appearances'), $this->localizedUrls->current('/base-de-donnees/garde-robe')],
                [$slot, $canonicalUrl],
            ];
        } else {
            $count = $builder->count();
            $title = __('seo.database.appearances.all.title', ['a' => $this->formatCount($count)]);
            $description = __('seo.database.appearances.all.description', ['a' => (string) $count]);
            $canonicalUrl = $this->localizedUrls->current('/base-de-donnees/garde-robe');
            $breadcrumbs = [
                [__('seo.database.breadcrumb.index'), $this->localizedUrls->current('/base-de-donnees')],
                [__('seo.database.breadcrumb.appearances'), $canonicalUrl],
            ];
        }

        return $this->buildSeoArray($title, $description, $canonicalUrl, $appUrl, 'website', $this->buildJsonLd([
            '@type' => 'CollectionPage',
            'name' => $title,
            'url' => $canonicalUrl,
            'description' => $description,
            'inLanguage' => AppLocale::current()->value,
            'breadcrumb' => $this->buildBreadcrumbJsonLd($appUrl, $breadcrumbs),
        ], $appUrl));
    }

    /**
     * @return array<string, string|null>|null
     */
    public function getProfessionsMeta(?string $professionSlug): ?array
    {
        $appUrl = $this->appUrl();

        if ($professionSlug !== null) {
            $professionName = $this->deSlugifyCategory($professionSlug, 'professions');
            $profession = WowProfession::query()->withActiveRecipes()
                ->where('name_fr', $professionName)
                ->first();

            if (! $profession instanceof WowProfession) {
                return null;
            }

            $recipeCount = WowRecipe::query()->where('is_active', true)
                ->where('profession_id', $profession->id)
                ->count();

            $displayedName = AppLocale::current() === AppLocale::English ? $profession->name_en ?? $profession->name_fr : $profession->name_fr;
            $title = __('seo.database.professions.profession.title', ['a' => $displayedName, 'b' => (string) $recipeCount]);
            $description = __('seo.database.professions.profession.description', ['a' => (string) $recipeCount, 'b' => $displayedName]);
            $canonicalUrl = $this->localizedUrls->current('/base-de-donnees/professions/'.$professionSlug);
            $breadcrumbs = [
                [__('seo.database.breadcrumb.index'), $this->localizedUrls->current('/base-de-donnees')],
                [__('seo.database.breadcrumb.professions'), $this->localizedUrls->current('/base-de-donnees/professions')],
                [$displayedName, $canonicalUrl],
            ];
        } else {
            $professionCount = WowProfession::query()->withActiveRecipes()->count();
            $recipeCount = WowRecipe::query()->where('is_active', true)->count();
            $title = __('seo.database.professions.all.title', ['a' => (string) $professionCount, 'b' => $this->formatCount($recipeCount)]);
            $description = __('seo.database.professions.all.description', ['a' => (string) $professionCount, 'b' => $this->formatCount($recipeCount)]);
            $canonicalUrl = $this->localizedUrls->current('/base-de-donnees/professions');
            $breadcrumbs = [
                [__('seo.database.breadcrumb.index'), $this->localizedUrls->current('/base-de-donnees')],
                [__('seo.database.breadcrumb.professions'), $canonicalUrl],
            ];
        }

        return $this->buildSeoArray($title, $description, $canonicalUrl, $appUrl, 'website', $this->buildJsonLd([
            '@type' => 'CollectionPage',
            'name' => $title,
            'url' => $canonicalUrl,
            'description' => $description,
            'inLanguage' => AppLocale::current()->value,
            'breadcrumb' => $this->buildBreadcrumbJsonLd($appUrl, $breadcrumbs),
        ], $appUrl));
    }

    /**
     * @return list<string>
     */
    public function getSitemapUrls(): array
    {
        $appUrl = $this->appUrl();
        $urls = [];

        $urls[] = $appUrl.'/base-de-donnees';
        $urls[] = $appUrl.'/base-de-donnees/montures';
        $urls[] = $appUrl.'/base-de-donnees/hauts-faits';
        $urls[] = $appUrl.'/base-de-donnees/quetes';
        $urls[] = $appUrl.'/base-de-donnees/mascottes';
        $urls[] = $appUrl.'/base-de-donnees/decorations';
        $urls[] = $appUrl.'/base-de-donnees/garde-robe';
        $urls[] = $appUrl.'/base-de-donnees/professions';

        /** @var list<string> $mountCategories */
        $mountCategories = WowMount::query()->where('is_active', true)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->all();

        foreach ($mountCategories as $mountCategory) {
            $urls[] = $appUrl.'/base-de-donnees/montures/'.$this->slugify($mountCategory);
        }

        /** @var list<string> $petCategories */
        $petCategories = WowPet::query()->where('is_active', true)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->all();

        foreach ($petCategories as $petCategory) {
            $urls[] = $appUrl.'/base-de-donnees/mascottes/'.$this->slugify($petCategory);
        }

        /** @var list<string> $decorCategories */
        $decorCategories = WowDecor::query()->where('is_active', true)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->all();

        foreach ($decorCategories as $decorCategory) {
            $urls[] = $appUrl.'/base-de-donnees/decorations/'.$this->slugify($decorCategory);
        }

        /** @var list<string> $appearanceSlots */
        $appearanceSlots = WowAppearance::query()->where('is_active', true)
            ->whereNotNull('slot')
            ->distinct()
            ->pluck('slot')
            ->all();

        foreach ($appearanceSlots as $appearanceSlot) {
            $urls[] = $appUrl.'/base-de-donnees/garde-robe/'.$this->slugify($appearanceSlot);
        }

        /** @var list<string> $professionNames */
        $professionNames = WowProfession::query()->withActiveRecipes()
            ->pluck('name_fr')
            ->all();

        foreach ($professionNames as $professionName) {
            $urls[] = $appUrl.'/base-de-donnees/professions/'.$this->slugify($professionName);
        }

        foreach (ExpansionId::allSlugs() as $id => $slug) {
            $achievementCount = WowAchievement::query()->where('is_active', true)->where('expansion_id', $id)->count();
            if ($achievementCount > 0) {
                $urls[] = $appUrl.'/base-de-donnees/hauts-faits/'.$slug;
            }

            $questCount = WowQuest::query()->where('is_active', true)->where('expansion_id', $id)->count();
            if ($questCount > 0) {
                $urls[] = $appUrl.'/base-de-donnees/quetes/'.$slug;
            }
        }

        return $urls;
    }

    public function generateSitemap(): string
    {
        /** @var string $xml */
        $xml = Cache::remember('sitemap_database_xml', 86400, function (): string {
            $sitemap = Sitemap::create();
            $now = now();

            foreach ($this->getSitemapUrls() as $frenchUrl) {
                foreach ($this->localizedUrls->sitemapEntries((string) parse_url($frenchUrl, PHP_URL_PATH)) as $url) {
                    $sitemap->add($url->setLastModificationDate($now)->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)->setPriority(0.7));
                }
            }

            return $sitemap->render();
        });

        return $xml;
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

    private function deSlugifyCategory(string $slug, string $context): string
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
            'professions' => WowProfession::query()->withActiveRecipes()
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

    private function formatCount(int $count): string
    {
        return (string) Number::format($count, locale: AppLocale::current()->value);
    }

    private function appUrl(): string
    {
        /** @var string $configUrl */
        $configUrl = config('app.url', '');

        return rtrim($configUrl, '/');
    }

    /**
     * @param  CollectionPageJsonLd  $schema
     */
    private function buildJsonLd(array $schema, string $appUrl): string
    {
        $jsonLd = array_merge([
            '@context' => 'https://schema.org',
        ], $schema, [
            'isPartOf' => [
                '@type' => 'WebApplication',
                'name' => 'WowPlanet',
                'url' => $appUrl,
            ],
        ]);

        return (string) json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $items
     * @return BreadcrumbJsonLd
     */
    private function buildBreadcrumbJsonLd(string $appUrl, array $items): array
    {
        $elements = [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'WowPlanet',
                'item' => $appUrl,
            ],
        ];

        foreach ($items as $index => $item) {
            $elements[] = [
                '@type' => 'ListItem',
                'position' => $index + 2,
                'name' => $item[0],
                'item' => $item[1],
            ];
        }

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function buildSeoArray(
        string $title,
        string $description,
        string $canonicalUrl,
        string $appUrl,
        string $ogType,
        string $jsonLd,
    ): array {
        return [
            'title' => $title,
            'description' => $description,
            'ogTitle' => $title,
            'ogDescription' => $description,
            'ogImage' => $appUrl.'/images/og-default.png',
            'ogUrl' => $canonicalUrl,
            'ogType' => $ogType,
            'canonicalUrl' => $canonicalUrl,
            'jsonLd' => $jsonLd,
        ];
    }
}
