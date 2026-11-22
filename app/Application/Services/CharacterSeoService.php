<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\CharacterProfileDTO;
use App\Http\Localization\AppLocale;
use App\Http\Localization\LocalizedUrls;
use App\Models\CharacterVisit;
use App\Models\WowAchievement;
use App\Models\WowMount;
use App\Models\WowPet;
use App\Models\WowQuest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\SitemapIndex;
use Spatie\Sitemap\Tags\Url;

class CharacterSeoService
{
    /** @var array<string, string> */
    private const array STATIC_PAGE_KEYS = [
        'faq' => 'faq',
        'cgu' => 'terms',
        'privacy' => 'privacy',
        'classements-pvp' => 'pvp',
        'addons' => 'addons',
    ];

    public function __construct(private readonly LocalizedUrls $localizedUrls) {}

    /**
     * @return array<string, string|null>
     */
    public function getHomeMeta(): array
    {
        /** @var string $configUrl */
        $configUrl = config('app.url', '');
        $appUrl = rtrim($configUrl, '/');

        $mountCount = WowMount::query()->where('is_active', true)->count();
        $achievementCount = WowAchievement::query()->where('is_active', true)->count();
        $questCount = WowQuest::query()->where('is_active', true)->count();
        $petCount = WowPet::query()->where('is_active', true)->count();

        $canonicalUrl = $this->localizedUrls->current('/');
        $title = __('seo.home.title');

        return [
            'title' => $title,
            'description' => __('seo.home.description'),
            'ogTitle' => $title,
            'ogDescription' => __('seo.home.og_description', [
                'quests' => $this->formatCount($questCount),
                'achievements' => $this->formatCount($achievementCount),
                'mounts' => $this->formatCount($mountCount),
                'pets' => $this->formatCount($petCount),
            ]),
            'ogImage' => $appUrl.'/images/og-default.png',
            'ogUrl' => $canonicalUrl,
            'ogType' => 'website',
            'canonicalUrl' => $canonicalUrl,
            'jsonLd' => $this->getHomeJsonLd($appUrl),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function getStaticPageMeta(string $page): array
    {
        /** @var string $configUrl */
        $configUrl = config('app.url', '');
        $appUrl = rtrim($configUrl, '/');

        $key = self::STATIC_PAGE_KEYS[$page] ?? 'default';
        $title = __('seo.'.$key.'.title');
        $description = __('seo.'.$key.'.description');

        $canonicalUrl = $this->localizedUrls->current('/'.$page);
        $jsonLd = $page === 'faq' ? $this->getFaqJsonLd($appUrl) : null;

        return [
            'title' => $title,
            'description' => $description,
            'ogTitle' => $title,
            'ogDescription' => $description,
            'ogImage' => $appUrl.'/images/og-default.png',
            'ogUrl' => $canonicalUrl,
            'ogType' => 'website',
            'canonicalUrl' => $canonicalUrl,
            'jsonLd' => $jsonLd,
        ];
    }

    /**
     * Construit la meta SEO d'un personnage à partir du profil déjà récupéré
     * (aucun appel Blizzard supplémentaire) et enregistre la visite pour le sitemap.
     *
     * @return array<string, string|null>
     */
    public function buildCharacterMeta(CharacterProfileDTO $characterProfileDTO, string $realm, string $name): array
    {
        /** @var string $configUrl */
        $configUrl = config('app.url', '');
        $appUrl = rtrim($configUrl, '/');
        $realmSlug = mb_strtolower($realm);
        $nameSlug = mb_strtolower($name);

        /** @var array<string, string|int|bool> $charData */
        $charData = [
            'name' => $characterProfileDTO->name,
            'realm' => $characterProfileDTO->realm,
            'level' => $characterProfileDTO->level,
            'race' => $characterProfileDTO->race,
            'class' => $characterProfileDTO->class,
            'faction' => $characterProfileDTO->faction,
            'ilvl' => $characterProfileDTO->ilvl,
            'avatarUrl' => $characterProfileDTO->avatarUrl,
            'found' => true,
        ];

        $this->trackCharacterVisit($realmSlug, $nameSlug, $charData);

        $canonicalUrl = $this->localizedUrls->current('/character/'.$realmSlug.'/'.$nameSlug);

        $placeholders = [
            'name' => $characterProfileDTO->name,
            'race' => $characterProfileDTO->race,
            'class' => $characterProfileDTO->class,
            'level' => (string) $characterProfileDTO->level,
            'ilvl' => (string) $characterProfileDTO->ilvl,
            'realm' => $characterProfileDTO->realm,
            'faction' => $characterProfileDTO->faction,
        ];
        $title = __('seo.character.title', $placeholders);
        $description = __('seo.character.description', $placeholders);
        $ogImage = $characterProfileDTO->avatarUrl !== '' ? $characterProfileDTO->avatarUrl : $appUrl.'/images/og-default.png';

        return [
            'title' => $title,
            'description' => $description,
            'ogTitle' => $title,
            'ogDescription' => $description,
            'ogImage' => $ogImage,
            'ogUrl' => $canonicalUrl,
            'ogType' => 'profile',
            'canonicalUrl' => $canonicalUrl,
            'jsonLd' => $this->getCharacterJsonLd($charData, $canonicalUrl),
        ];
    }

    /**
     * Meta SEO pour un personnage introuvable (réponse 404).
     *
     * @return array<string, string|null>
     */
    public function buildNotFoundCharacterMeta(string $realm, string $name): array
    {
        /** @var string $configUrl */
        $configUrl = config('app.url', '');
        $appUrl = rtrim($configUrl, '/');
        $realmSlug = mb_strtolower($realm);
        $nameSlug = mb_strtolower($name);
        $canonicalUrl = $this->localizedUrls->current('/character/'.$realmSlug.'/'.$nameSlug);
        $placeholders = ['name' => ucfirst($name), 'realm' => ucfirst($realm)];
        $title = __('seo.character.not_found.title', $placeholders);

        return [
            'title' => $title,
            'description' => __('seo.character.not_found.description', $placeholders),
            'ogTitle' => $title,
            'ogDescription' => __('seo.character.not_found.og_description', $placeholders),
            'ogImage' => $appUrl.'/images/og-default.png',
            'ogUrl' => $canonicalUrl,
            'ogType' => 'profile',
            'canonicalUrl' => $canonicalUrl,
            'jsonLd' => null,
        ];
    }

    public function generateSitemapIndex(): string
    {
        /** @var string $xml */
        $xml = Cache::remember('sitemap_index_xml', 3600, function (): string {
            /** @var string $configUrl */
            $configUrl = config('app.url', '');
            $appUrl = rtrim($configUrl, '/');

            return SitemapIndex::create()
                ->add($appUrl.'/sitemap-pages.xml')
                ->add($appUrl.'/sitemap-database.xml')
                ->render();
        });

        return $xml;
    }

    public function generatePagesSitemap(): string
    {
        /** @var string $xml */
        $xml = Cache::remember('sitemap_pages_xml', 86400, fn (): string => $this->buildPagesSitemap()->render());

        return $xml;
    }

    private function buildPagesSitemap(): Sitemap
    {
        config('app.url', '');
        $now = now();

        $sitemap = Sitemap::create();

        foreach ($this->sitemapPages() as $path => [$frequency, $priority]) {
            foreach ($this->localizedUrls->sitemapEntries($path) as $url) {
                $sitemap->add($url->setLastModificationDate($now)->setChangeFrequency($frequency)->setPriority($priority));
            }
        }

        return $sitemap;
    }

    /**
     * Les cotes PvP bougent en continu pendant la saison, les pages de contenu rarement.
     *
     * @return array<string, array{string, float}>
     */
    private function sitemapPages(): array
    {
        return [
            '/' => [Url::CHANGE_FREQUENCY_WEEKLY, 1.0],
            '/privacy' => [Url::CHANGE_FREQUENCY_YEARLY, 0.3],
            '/cgu' => [Url::CHANGE_FREQUENCY_YEARLY, 0.3],
            '/faq' => [Url::CHANGE_FREQUENCY_YEARLY, 0.3],
            '/addons' => [Url::CHANGE_FREQUENCY_YEARLY, 0.3],
            '/classements-pvp' => [Url::CHANGE_FREQUENCY_DAILY, 0.6],
        ];
    }

    private function formatCount(int $count): string
    {
        return (string) Number::format($count, locale: AppLocale::current()->value);
    }

    /**
     * @param  array<string, string|int|bool>  $charData
     */
    private function trackCharacterVisit(
        string $realmSlug,
        string $nameSlug,
        array $charData,
    ): void {
        try {
            CharacterVisit::query()->updateOrCreate([
                'realm_slug' => $realmSlug,
                'character_name' => $nameSlug,
            ], [
                'display_name' => (string) ($charData['name'] ?? ucfirst($nameSlug)),
                'display_realm' => (string) ($charData['realm'] ?? ucfirst($realmSlug)),
                'class_name' => (string) ($charData['class'] ?? ''),
                'level' => (int) ($charData['level'] ?? 0),
                'last_visited_at' => now(),
            ]);
        } catch (\Exception $exception) {
            Log::debug(
                sprintf('SEO: Failed to track visit for %s/%s: ', $realmSlug, $nameSlug)
                .$exception->getMessage(),
            );
        }
    }

    private function getHomeJsonLd(string $appUrl): string
    {
        return (string) json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'WebApplication',
            'name' => 'WowPlanet',
            'url' => $appUrl,
            'description' => __('seo.home.json_ld_description'),
            'applicationCategory' => 'GameApplication',
            'operatingSystem' => 'Web',
            'inLanguage' => AppLocale::current()->value,
            'offers' => [
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => 'EUR',
            ],
            'about' => [
                '@type' => 'VideoGame',
                'name' => 'World of Warcraft',
                'gamePlatform' => 'PC',
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    }

    /**
     * @param  array<string, string|int|bool>  $charData
     */
    private function getCharacterJsonLd(array $charData, string $url): string
    {
        return (string) json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'ProfilePage',
            'name' => ($charData['name']).' - WowPlanet',
            'url' => $url,
            'mainEntity' => [
                '@type' => 'Person',
                'name' => (string) $charData['name'],
                'description' => __('seo.character.json_ld_description', [
                    'race' => (string) ($charData['race'] ?? ''),
                    'class' => (string) ($charData['class'] ?? ''),
                    'level' => (string) ($charData['level'] ?? ''),
                    'realm' => (string) ($charData['realm'] ?? ''),
                ]),
                'image' => ((string) ($charData['avatarUrl'] ?? '')) ?: null,
            ],
            'isPartOf' => [
                '@type' => 'WebApplication',
                'name' => 'WowPlanet',
            ],
            'about' => [
                '@type' => 'VideoGame',
                'name' => 'World of Warcraft',
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    }

    private function getFaqJsonLd(string $appUrl): string
    {
        return (string) json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'url' => $appUrl.'/faq',
            'inLanguage' => 'fr',
            'mainEntity' => [
                [
                    '@type' => 'Question',
                    'name' => "Qu'est-ce que WowPlanet ?",
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => 'WowPlanet est un site fan gratuit de suivi de progression pour World of Warcraft. Il permet de visualiser la progression de vos personnages et de comparer vos accomplissements avec la base de données complète du jeu, entièrement en français.',
                    ],
                ],
                [
                    '@type' => 'Question',
                    'name' => 'Comment importer mes personnages ?',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => 'Cliquez sur « Se connecter avec Battle.net » en haut du site. Une fois authentifié, tous vos personnages sont automatiquement importés et synchronisés.',
                    ],
                ],
                [
                    '@type' => 'Question',
                    'name' => "Qu'est-ce que le score compte ?",
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => "Le score compte est une note globale sur 100 qui évalue votre progression sur l'ensemble de vos personnages : quêtes, hauts-faits, montures, mascottes, métiers, décorations et réputations.",
                    ],
                ],
                [
                    '@type' => 'Question',
                    'name' => 'Peut-on utiliser le site sans se connecter ?',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => 'Oui. La base de données complète, les classements PvP et les fiches publiques des personnages sont consultables sans connexion. La connexion Battle.net est nécessaire uniquement pour importer vos propres personnages.',
                    ],
                ],
                [
                    '@type' => 'Question',
                    'name' => 'Comment fonctionnent les tâches quotidiennes ?',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => 'WowPlanet permet de créer des tâches personnalisées pour chacun de vos personnages. Les tâches quotidiennes se réinitialisent chaque jour à 4 h UTC, les hebdomadaires chaque mercredi à 4 h UTC et les mensuelles le 1er de chaque mois à 4 h UTC, comme la remise à zéro de Blizzard en Europe : 5 h à Paris en hiver, 6 h en été.',
                    ],
                ],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    }
}
