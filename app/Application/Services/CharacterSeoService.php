<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\CharacterProfileDTO;
use App\Models\CharacterVisit;
use App\Models\WowAchievement;
use App\Models\WowMount;
use App\Models\WowPet;
use App\Models\WowQuest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\SitemapIndex;
use Spatie\Sitemap\Tags\Url;

class CharacterSeoService
{
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

        return [
            'title' => 'WowPlanet - Suivi de progression WoW en français',
            'description' => 'Analysez votre personnage World of Warcraft en français : quêtes, hauts-faits, montures, mascottes, décorations et professions.'
                .' Comparez votre progression avec la base de données complète du jeu.',
            'ogTitle' => 'WowPlanet - Suivi de progression WoW en français',
            'ogDescription' => sprintf(
                'Suivez la progression de vos personnages WoW en français. %s quêtes, %s hauts-faits, %s montures et %s mascottes référencées.',
                number_format($questCount, 0, ',', "\u{202f}"),
                number_format($achievementCount, 0, ',', "\u{202f}"),
                number_format($mountCount, 0, ',', "\u{202f}"),
                number_format($petCount, 0, ',', "\u{202f}"),
            ),
            'ogImage' => $appUrl.'/images/og-default.png',
            'ogUrl' => $appUrl,
            'ogType' => 'website',
            'canonicalUrl' => $appUrl,
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

        [$title, $description] = match ($page) {
            'faq' => [
                'FAQ - Questions fréquentes | WowPlanet',
                'Réponses aux questions fréquentes sur WowPlanet : import de personnages, score compte, tâches quotidiennes et base de données WoW.',
            ],
            'cgu' => [
                'Conditions générales d\'utilisation | WowPlanet',
                'Conditions générales d\'utilisation du site WowPlanet.',
            ],
            'privacy' => [
                'Politique de confidentialité | WowPlanet',
                'Politique de confidentialité et gestion des données personnelles sur WowPlanet.',
            ],
            'classements-pvp' => [
                'Classements PvP | WowPlanet',
                'Classements PvP officiels de World of Warcraft : arène 2c2 et 3c3, champs de bataille cotés, mêlée solo et blitz. Cotes de la saison en cours, mises à jour en direct.',
            ],
            'addons' => [
                'Addons WoW | WowPlanet',
                'Découvrez MapTidy, WhatTodo et TankTruckReverse, les addons World of Warcraft développés par WowPlanet : filtrage des marqueurs de quêtes, liste de tâches à faire et bip de recul pour tanks.',
            ],
            default => [
                'WowPlanet',
                'WowPlanet - Suivi de progression World of Warcraft.',
            ],
        };

        $canonicalUrl = $appUrl.'/'.$page;
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

        $canonicalUrl = $appUrl.'/character/'.$realmSlug.'/'.$nameSlug;

        $title = sprintf(
            '%s - %s %s | %s | WowPlanet',
            $characterProfileDTO->name,
            $characterProfileDTO->class,
            $characterProfileDTO->level,
            $characterProfileDTO->realm,
        );
        $description = sprintf(
            '%s, %s %s niveau %s (ilvl %s) sur %s (%s).'
            .' Consultez sa progression : quêtes, hauts-faits, montures et mascottes.',
            $characterProfileDTO->name,
            $characterProfileDTO->race,
            $characterProfileDTO->class,
            $characterProfileDTO->level,
            $characterProfileDTO->ilvl,
            $characterProfileDTO->realm,
            $characterProfileDTO->faction,
        );
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
        $canonicalUrl = $appUrl.'/character/'.$realmSlug.'/'.$nameSlug;
        $displayName = ucfirst($name);
        $displayRealm = ucfirst($realm);
        $title = sprintf('%s - %s | WowPlanet', $displayName, $displayRealm);

        return [
            'title' => $title,
            'description' => sprintf(
                'Profil du personnage %s sur %s.'
                .' Consultez sa progression World of Warcraft sur WowPlanet.',
                $displayName,
                $displayRealm,
            ),
            'ogTitle' => $title,
            'ogDescription' => sprintf('Profil du personnage %s sur %s.', $displayName, $displayRealm),
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
        /** @var string $configUrl */
        $configUrl = config('app.url', '');
        $appUrl = rtrim($configUrl, '/');

        $now = now();

        $sitemap = Sitemap::create()
            ->add(
                Url::create($appUrl)
                    ->setLastModificationDate($now)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                    ->setPriority(1.0),
            );

        $sitemap->add(
            Url::create($appUrl.'/privacy')
                ->setLastModificationDate($now)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_YEARLY)
                ->setPriority(0.3),
        );
        $sitemap->add(
            Url::create($appUrl.'/cgu')
                ->setLastModificationDate($now)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_YEARLY)
                ->setPriority(0.3),
        );
        $sitemap->add(
            Url::create($appUrl.'/faq')
                ->setLastModificationDate($now)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_YEARLY)
                ->setPriority(0.3),
        );
        $sitemap->add(
            Url::create($appUrl.'/addons')
                ->setLastModificationDate($now)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_YEARLY)
                ->setPriority(0.3),
        );
        // Les cotes bougent en continu pendant la saison.
        $sitemap->add(
            Url::create($appUrl.'/classements-pvp')
                ->setLastModificationDate($now)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                ->setPriority(0.6),
        );

        return $sitemap;
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
            'description' => 'Suivez la progression de vos personnages World of Warcraft'
                .' : quêtes, hauts-faits, montures et mascottes.',
            'applicationCategory' => 'GameApplication',
            'operatingSystem' => 'Web',
            'inLanguage' => 'fr',
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
                'description' => sprintf(
                    '%s %s niveau %s sur %s',
                    (string) ($charData['race'] ?? ''),
                    (string) ($charData['class'] ?? ''),
                    (string) ($charData['level'] ?? ''),
                    (string) ($charData['realm'] ?? ''),
                ),
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
                        'text' => 'Le score compte est une note globale sur 100 qui évalue votre progression sur l\'ensemble de vos personnages : quêtes, hauts-faits, montures, mascottes, métiers, décorations et réputations.',
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
                        'text' => 'WowPlanet permet de créer des tâches personnalisées pour chacun de vos personnages. Les tâches quotidiennes se réinitialisent chaque jour à 5h, les hebdomadaires chaque mercredi à 5h et les mensuelles le 1er de chaque mois à 5h.',
                    ],
                ],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    }
}
