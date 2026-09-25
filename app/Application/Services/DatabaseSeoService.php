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

/**
 * @phpstan-type BreadcrumbJsonLd array{'@type': string, itemListElement: list<array{'@type': string, position: int, name: string, item: string}>}
 * @phpstan-type CollectionPageJsonLd array{'@type': string, name: string, url: string, description: string, inLanguage: string, breadcrumb: BreadcrumbJsonLd}
 */
class DatabaseSeoService
{
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
        $professionCount = WowProfession::query()->where('is_active', true)->count();
        $recipeCount = WowRecipe::query()->where('is_active', true)->count();

        $title = 'Base de données WoW complète | WowPlanet';
        $description = sprintf(
            'Explorez la base de données complète de World of Warcraft entièrement en français : %s montures, %s hauts-faits, %s quêtes, %s mascottes, %s décorations et %d professions (%s recettes). La référence francophone WoW.',
            number_format($mountCount, 0, ',', ' '),
            number_format($achievementCount, 0, ',', ' '),
            number_format($questCount, 0, ',', ' '),
            number_format($petCount, 0, ',', ' '),
            number_format($decorCount, 0, ',', ' '),
            $professionCount,
            number_format($recipeCount, 0, ',', ' '),
        );

        $canonicalUrl = $appUrl.'/base-de-donnees';

        return $this->buildSeoArray($title, $description, $canonicalUrl, $appUrl, 'website', $this->buildJsonLd([
            '@type' => 'CollectionPage',
            'name' => $title,
            'url' => $canonicalUrl,
            'description' => $description,
            'inLanguage' => 'fr',
            'breadcrumb' => $this->buildBreadcrumbJsonLd($appUrl, [
                ['Base de données', $canonicalUrl],
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

            $title = sprintf('Montures WoW %s — %d montures | WowPlanet', $category, $count);
            $description = sprintf(
                '%d montures %s WoW en français. Source d\'obtention, icône et lien Wowhead pour chaque monture.',
                $count,
                $category,
            );
            $canonicalUrl = $appUrl.'/base-de-donnees/montures/'.$categorySlug;
            $breadcrumbs = [
                ['Base de données', $appUrl.'/base-de-donnees'],
                ['Montures', $appUrl.'/base-de-donnees/montures'],
                [$category, $canonicalUrl],
            ];
        } else {
            $count = $builder->count();
            $title = sprintf('Montures WoW — %s montures | WowPlanet', number_format($count, 0, ',', "\u{202f}"));
            $description = sprintf(
                '%d montures WoW classées par catégorie : terrestres, volantes, aquatiques et plus. Trouvez comment obtenir chaque monture.',
                $count,
            );
            $canonicalUrl = $appUrl.'/base-de-donnees/montures';
            $breadcrumbs = [
                ['Base de données', $appUrl.'/base-de-donnees'],
                ['Montures', $canonicalUrl],
            ];
        }

        return $this->buildSeoArray($title, $description, $canonicalUrl, $appUrl, 'website', $this->buildJsonLd([
            '@type' => 'CollectionPage',
            'name' => $title,
            'url' => $canonicalUrl,
            'description' => $description,
            'inLanguage' => 'fr',
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

            if (! $expansion instanceof \App\Domain\ValueObjects\ExpansionId) {
                return null;
            }

            $count = WowAchievement::query()->where('is_active', true)
                ->where('expansion_id', $expansion->value)
                ->count();

            $expansionName = $expansion->toString();
            $title = sprintf('Hauts-faits WoW %s — %d | WowPlanet', $expansionName, $count);
            $description = sprintf(
                'Tous les %d hauts-faits de %s dans World of Warcraft en français. Classés par catégorie avec points et faction.',
                $count,
                $expansionName,
            );
            $canonicalUrl = $appUrl.'/base-de-donnees/hauts-faits/'.$expansionSlug;
            $breadcrumbs = [
                ['Base de données', $appUrl.'/base-de-donnees'],
                ['Hauts-faits', $appUrl.'/base-de-donnees/hauts-faits'],
                [$expansionName, $canonicalUrl],
            ];
        } else {
            $count = WowAchievement::query()->where('is_active', true)->count();
            $title = sprintf('Hauts-faits WoW — %s hauts-faits | WowPlanet', number_format($count, 0, ',', "\u{202f}"));
            $description = sprintf(
                'Tous les %d hauts-faits de World of Warcraft en français. Classés par extension et catégorie avec points. La référence francophone.',
                $count,
            );
            $canonicalUrl = $appUrl.'/base-de-donnees/hauts-faits';
            $breadcrumbs = [
                ['Base de données', $appUrl.'/base-de-donnees'],
                ['Hauts-faits', $canonicalUrl],
            ];
        }

        return $this->buildSeoArray($title, $description, $canonicalUrl, $appUrl, 'website', $this->buildJsonLd([
            '@type' => 'CollectionPage',
            'name' => $title,
            'url' => $canonicalUrl,
            'description' => $description,
            'inLanguage' => 'fr',
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

            if (! $expansion instanceof \App\Domain\ValueObjects\ExpansionId) {
                return null;
            }

            $expansionName = $expansion->toString();
            $count = WowQuest::query()->where('is_active', true)
                ->where('expansion_id', $expansion->value)
                ->count();

            $title = sprintf('Quêtes WoW %s — %d quêtes | WowPlanet', $expansionName, $count);
            $description = sprintf(
                'Toutes les %d quêtes de %s dans World of Warcraft en français. Triées par zone avec liens Wowhead.',
                $count,
                $expansionName,
            );
            $canonicalUrl = $appUrl.'/base-de-donnees/quetes/'.$expansionSlug;
            $breadcrumbs = [
                ['Base de données', $appUrl.'/base-de-donnees'],
                ['Quêtes', $appUrl.'/base-de-donnees/quetes'],
                [$expansionName, $canonicalUrl],
            ];
        } else {
            $count = WowQuest::query()->where('is_active', true)->count();
            $title = sprintf('Quêtes WoW — %s quêtes | WowPlanet', number_format($count, 0, ',', "\u{202f}"));
            $description = sprintf(
                'Toutes les %s quêtes de World of Warcraft en français. Triées par extension. La référence francophone.',
                number_format($count, 0, ',', ' '),
            );
            $canonicalUrl = $appUrl.'/base-de-donnees/quetes';
            $breadcrumbs = [
                ['Base de données', $appUrl.'/base-de-donnees'],
                ['Quêtes', $canonicalUrl],
            ];
        }

        return $this->buildSeoArray($title, $description, $canonicalUrl, $appUrl, 'website', $this->buildJsonLd([
            '@type' => 'CollectionPage',
            'name' => $title,
            'url' => $canonicalUrl,
            'description' => $description,
            'inLanguage' => 'fr',
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

            $title = sprintf('Mascottes WoW %s — %d mascottes | WowPlanet', $category, $count);
            $description = sprintf(
                'Découvrez les %d mascottes de combat %s dans World of Warcraft en français. Liste complète avec source et lien Wowhead.',
                $count,
                $category,
            );
            $canonicalUrl = $appUrl.'/base-de-donnees/mascottes/'.$categorySlug;
            $breadcrumbs = [
                ['Base de données', $appUrl.'/base-de-donnees'],
                ['Mascottes', $appUrl.'/base-de-donnees/mascottes'],
                [$category, $canonicalUrl],
            ];
        } else {
            $count = $builder->count();
            $title = sprintf('Mascottes WoW — %s mascottes | WowPlanet', number_format($count, 0, ',', "\u{202f}"));
            $description = sprintf(
                'Toutes les %d mascottes de combat de World of Warcraft en français. Triées par catégorie avec source d\'obtention et lien Wowhead.',
                $count,
            );
            $canonicalUrl = $appUrl.'/base-de-donnees/mascottes';
            $breadcrumbs = [
                ['Base de données', $appUrl.'/base-de-donnees'],
                ['Mascottes', $canonicalUrl],
            ];
        }

        return $this->buildSeoArray($title, $description, $canonicalUrl, $appUrl, 'website', $this->buildJsonLd([
            '@type' => 'CollectionPage',
            'name' => $title,
            'url' => $canonicalUrl,
            'description' => $description,
            'inLanguage' => 'fr',
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

            $title = sprintf('Décorations WoW %s — %d | WowPlanet', $category, $count);
            $description = sprintf(
                'Découvrez les %d décorations %s dans World of Warcraft en français. Liste complète avec source et lien Wowhead.',
                $count,
                $category,
            );
            $canonicalUrl = $appUrl.'/base-de-donnees/decorations/'.$categorySlug;
            $breadcrumbs = [
                ['Base de données', $appUrl.'/base-de-donnees'],
                ['Décorations', $appUrl.'/base-de-donnees/decorations'],
                [$category, $canonicalUrl],
            ];
        } else {
            $count = $builder->count();
            $title = sprintf('Décorations WoW — %s décorations | WowPlanet', number_format($count, 0, ',', "\u{202f}"));
            $description = sprintf(
                'Toutes les %d décorations de World of Warcraft en français. Triées par catégorie avec source d\'obtention et lien Wowhead.',
                $count,
            );
            $canonicalUrl = $appUrl.'/base-de-donnees/decorations';
            $breadcrumbs = [
                ['Base de données', $appUrl.'/base-de-donnees'],
                ['Décorations', $canonicalUrl],
            ];
        }

        return $this->buildSeoArray($title, $description, $canonicalUrl, $appUrl, 'website', $this->buildJsonLd([
            '@type' => 'CollectionPage',
            'name' => $title,
            'url' => $canonicalUrl,
            'description' => $description,
            'inLanguage' => 'fr',
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

            $title = sprintf('Transmogrification WoW %s — %d apparences | WowPlanet', $slot, $count);
            $description = sprintf(
                '%d apparences d\'équipement %s WoW en français. Icône et lien Wowhead pour chaque apparence transmog.',
                $count,
                $slot,
            );
            $canonicalUrl = $appUrl.'/base-de-donnees/garde-robe/'.$slotSlug;
            $breadcrumbs = [
                ['Base de données', $appUrl.'/base-de-donnees'],
                ['Garde-robe', $appUrl.'/base-de-donnees/garde-robe'],
                [$slot, $canonicalUrl],
            ];
        } else {
            $count = $builder->count();
            $title = sprintf('Transmogrification WoW — %s apparences | WowPlanet', number_format($count, 0, ',', "\u{202f}"));
            $description = sprintf(
                'Toutes les %d apparences d\'équipement (transmog) de World of Warcraft en français. Classées par emplacement avec lien Wowhead.',
                $count,
            );
            $canonicalUrl = $appUrl.'/base-de-donnees/garde-robe';
            $breadcrumbs = [
                ['Base de données', $appUrl.'/base-de-donnees'],
                ['Garde-robe', $canonicalUrl],
            ];
        }

        return $this->buildSeoArray($title, $description, $canonicalUrl, $appUrl, 'website', $this->buildJsonLd([
            '@type' => 'CollectionPage',
            'name' => $title,
            'url' => $canonicalUrl,
            'description' => $description,
            'inLanguage' => 'fr',
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
            $profession = WowProfession::query()->where('is_active', true)
                ->where('name_fr', $professionName)
                ->first();

            if (! $profession instanceof WowProfession) {
                return null;
            }

            $recipeCount = WowRecipe::query()->where('is_active', true)
                ->where('profession_id', $profession->id)
                ->count();

            $title = sprintf('%s WoW - %d recettes en français | WowPlanet', $professionName, $recipeCount);
            $description = sprintf(
                '%d recettes de %s WoW en français. Classées par extension avec liens Wowhead.',
                $recipeCount,
                $professionName,
            );
            $canonicalUrl = $appUrl.'/base-de-donnees/professions/'.$professionSlug;
            $breadcrumbs = [
                ['Base de données', $appUrl.'/base-de-donnees'],
                ['Professions', $appUrl.'/base-de-donnees/professions'],
                [$professionName, $canonicalUrl],
            ];
        } else {
            $professionCount = WowProfession::query()->where('is_active', true)->count();
            $recipeCount = WowRecipe::query()->where('is_active', true)->count();
            $title = sprintf('Professions WoW — %d métiers, %s recettes | WowPlanet', $professionCount, number_format($recipeCount, 0, ',', "\u{202f}"));
            $description = sprintf(
                'Toutes les %d professions de World of Warcraft en français avec %s recettes. Classées par type et extension. La référence francophone.',
                $professionCount,
                number_format($recipeCount, 0, ',', ' '),
            );
            $canonicalUrl = $appUrl.'/base-de-donnees/professions';
            $breadcrumbs = [
                ['Base de données', $appUrl.'/base-de-donnees'],
                ['Professions', $canonicalUrl],
            ];
        }

        return $this->buildSeoArray($title, $description, $canonicalUrl, $appUrl, 'website', $this->buildJsonLd([
            '@type' => 'CollectionPage',
            'name' => $title,
            'url' => $canonicalUrl,
            'description' => $description,
            'inLanguage' => 'fr',
            'breadcrumb' => $this->buildBreadcrumbJsonLd($appUrl, $breadcrumbs),
        ], $appUrl));
    }

    /**
     * @return array<array{url: string, label: string, lastmod: string|null}>
     */
    public function getSitemapUrls(): array
    {
        $appUrl = $this->appUrl();
        $urls = [];

        $urls[] = ['url' => $appUrl.'/base-de-donnees', 'label' => 'index', 'lastmod' => null];
        $urls[] = ['url' => $appUrl.'/base-de-donnees/montures', 'label' => 'mounts', 'lastmod' => null];
        $urls[] = ['url' => $appUrl.'/base-de-donnees/hauts-faits', 'label' => 'achievements', 'lastmod' => null];
        $urls[] = ['url' => $appUrl.'/base-de-donnees/quetes', 'label' => 'quests', 'lastmod' => null];
        $urls[] = ['url' => $appUrl.'/base-de-donnees/mascottes', 'label' => 'pets', 'lastmod' => null];
        $urls[] = ['url' => $appUrl.'/base-de-donnees/decorations', 'label' => 'decors', 'lastmod' => null];
        $urls[] = ['url' => $appUrl.'/base-de-donnees/garde-robe', 'label' => 'appearances', 'lastmod' => null];
        $urls[] = ['url' => $appUrl.'/base-de-donnees/professions', 'label' => 'professions', 'lastmod' => null];

        /** @var list<string> $mountCategories */
        $mountCategories = WowMount::query()->where('is_active', true)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->all();

        foreach ($mountCategories as $mountCategory) {
            $urls[] = [
                'url' => $appUrl.'/base-de-donnees/montures/'.$this->slugify($mountCategory),
                'label' => 'mount-'.$mountCategory,
                'lastmod' => null,
            ];
        }

        /** @var list<string> $petCategories */
        $petCategories = WowPet::query()->where('is_active', true)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->all();

        foreach ($petCategories as $petCategory) {
            $urls[] = [
                'url' => $appUrl.'/base-de-donnees/mascottes/'.$this->slugify($petCategory),
                'label' => 'pet-'.$petCategory,
                'lastmod' => null,
            ];
        }

        /** @var list<string> $decorCategories */
        $decorCategories = WowDecor::query()->where('is_active', true)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->all();

        foreach ($decorCategories as $decorCategory) {
            $urls[] = [
                'url' => $appUrl.'/base-de-donnees/decorations/'.$this->slugify($decorCategory),
                'label' => 'decor-'.$decorCategory,
                'lastmod' => null,
            ];
        }

        /** @var list<string> $appearanceSlots */
        $appearanceSlots = WowAppearance::query()->where('is_active', true)
            ->whereNotNull('slot')
            ->distinct()
            ->pluck('slot')
            ->all();

        foreach ($appearanceSlots as $appearanceSlot) {
            $urls[] = [
                'url' => $appUrl.'/base-de-donnees/garde-robe/'.$this->slugify($appearanceSlot),
                'label' => 'appearance-'.$appearanceSlot,
                'lastmod' => null,
            ];
        }

        /** @var list<string> $professionNames */
        $professionNames = WowProfession::query()->where('is_active', true)
            ->pluck('name_fr')
            ->all();

        foreach ($professionNames as $professionName) {
            $urls[] = [
                'url' => $appUrl.'/base-de-donnees/professions/'.$this->slugify($professionName),
                'label' => 'profession-'.$professionName,
                'lastmod' => null,
            ];
        }

        foreach (ExpansionId::allSlugs() as $id => $slug) {
            $achievementCount = WowAchievement::query()->where('is_active', true)->where('expansion_id', $id)->count();
            if ($achievementCount > 0) {
                $urls[] = [
                    'url' => $appUrl.'/base-de-donnees/hauts-faits/'.$slug,
                    'label' => 'achievement-'.$slug,
                    'lastmod' => null,
                ];
            }

            $questCount = WowQuest::query()->where('is_active', true)->where('expansion_id', $id)->count();
            if ($questCount > 0) {
                $urls[] = [
                    'url' => $appUrl.'/base-de-donnees/quetes/'.$slug,
                    'label' => 'quest-'.$slug,
                    'lastmod' => null,
                ];
            }
        }

        return $urls;
    }

    public function generateSitemap(): string
    {
        /** @var string $xml */
        $xml = \Illuminate\Support\Facades\Cache::remember('sitemap_database_xml', 86400, function (): string {
            $sitemap = \Spatie\Sitemap\Sitemap::create();
            $now = now();

            foreach ($this->getSitemapUrls() as $entry) {
                $sitemap->add(
                    \Spatie\Sitemap\Tags\Url::create($entry['url'])
                        ->setLastModificationDate($now)
                        ->setChangeFrequency(\Spatie\Sitemap\Tags\Url::CHANGE_FREQUENCY_WEEKLY)
                        ->setPriority(0.7),
                );
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
