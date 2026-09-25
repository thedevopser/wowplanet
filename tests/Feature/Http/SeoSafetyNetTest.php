<?php

declare(strict_types=1);

use App\Application\DTOs\CharacterProfileDTO;
use App\Application\Services\CharacterProfileService;
use App\Application\Services\PvpLeaderboardService;
use App\Application\Services\UserCharacterService;
use App\Models\WowMount;

// Freezes what search engines see on every indexed page type. A phase of the UX overhaul
// that changes one of these values must say why in its story, then update this file.

function seoBaseUrl(): string
{
    return rtrim((string) config('app.url'), '/');
}

function stubSeoDependencies(): void
{
    WowMount::factory()->count(3)->create(['category' => 'Volantes', 'is_active' => true]);

    $auth = test()->mock(UserCharacterService::class);
    /** @var \Mockery\Expectation $authExp */
    $authExp = $auth->shouldReceive('isAuthenticated');
    $authExp->andReturnFalse();

    $profiles = test()->mock(CharacterProfileService::class);
    /** @var \Mockery\Expectation $profileExp */
    $profileExp = $profiles->shouldReceive('getProfile');
    $profileExp->andReturn(new CharacterProfileDTO(
        name: 'Thrall',
        realm: 'Hyjal',
        race: 'Orc',
        class: 'Chaman',
        classId: 7,
        level: 80,
        ilvl: 620,
        faction: 'Horde',
        avatarUrl: 'https://example.com/avatar.jpg',
        classIconUrl: 'https://example.com/class-icon.jpg',
        collections: [],
        mountsCount: 150,
        petsCount: 80,
    ));

    $leaderboard = test()->mock(PvpLeaderboardService::class);
    /** @var \Mockery\Expectation $bracketsExp */
    $bracketsExp = $leaderboard->shouldReceive('availableBrackets');
    $bracketsExp->andReturn([]);
    /** @var \Mockery\Expectation $boardExp */
    $boardExp = $leaderboard->shouldReceive('leaderboard');
    $boardExp->andReturn([
        'bracket' => '3v3',
        'label' => 'Arène 3c3',
        'seasonId' => 40,
        'entries' => [],
        'total' => 0,
        'currentPage' => 1,
        'lastPage' => 1,
        'unavailable' => false,
    ]);
}

dataset('indexed pages', [
    'home' => ['/', 'WowPlanet - Suivi de progression WoW en français', 'Analysez votre personnage World of Warcraft en français : quêtes, hauts-faits, montures, mascottes, décorations et professions. Comparez votre progression avec la base de données complète du jeu.', '', true],
    'character sheet' => ['/character/hyjal/thrall', 'Thrall - Chaman 80 | Hyjal | WowPlanet', 'Thrall, Orc Chaman niveau 80 (ilvl 620) sur Hyjal (Horde). Consultez sa progression : quêtes, hauts-faits, montures et mascottes.', '/character/hyjal/thrall', true],
    'database index' => ['/base-de-donnees', 'Base de données WoW complète | WowPlanet', 'Explorez la base de données complète de World of Warcraft entièrement en français : 3 montures, 0 hauts-faits, 0 quêtes, 0 mascottes, 0 décorations et 0 professions (0 recettes). La référence francophone WoW.', '/base-de-donnees', true],
    'database section' => ['/base-de-donnees/montures', 'Montures WoW — 3 montures | WowPlanet', '3 montures WoW classées par catégorie : terrestres, volantes, aquatiques et plus. Trouvez comment obtenir chaque monture.', '/base-de-donnees/montures', true],
    'database category' => ['/base-de-donnees/montures/volantes', 'Montures WoW Volantes — 3 montures | WowPlanet', "3 montures Volantes WoW en français. Source d'obtention, icône et lien Wowhead pour chaque monture.", '/base-de-donnees/montures/volantes', true],
    'pvp leaderboard' => ['/classements-pvp', 'Classements PvP | WowPlanet', 'Classements PvP officiels de World of Warcraft : arène 2c2 et 3c3, champs de bataille cotés, mêlée solo et blitz. Cotes de la saison en cours, mises à jour en direct.', '/classements-pvp', false],
    'faq' => ['/faq', 'FAQ - Questions fréquentes | WowPlanet', 'Réponses aux questions fréquentes sur WowPlanet : import de personnages, score compte, tâches quotidiennes et base de données WoW.', '/faq', true],
    'terms' => ['/cgu', "Conditions générales d'utilisation | WowPlanet", "Conditions générales d'utilisation du site WowPlanet.", '/cgu', false],
    'privacy' => ['/privacy', 'Politique de confidentialité | WowPlanet', 'Politique de confidentialité et gestion des données personnelles sur WowPlanet.', '/privacy', false],
    'addons' => ['/addons', 'Addons WoW | WowPlanet', 'Découvrez MapTidy, WhatTodo et TankTruckReverse, les addons World of Warcraft développés par WowPlanet : filtrage des marqueurs de quêtes, liste de tâches à faire et bip de recul pour tanks.', '/addons', false],
]);

test('indexed page keeps its status and metadata', function (string $url, string $title, string $description, string $canonicalPath, bool $hasJsonLd): void {
    stubSeoDependencies();

    $response = $this->get($url)->assertOk();

    /** @var array{props: array{meta: array{title: string, description: string, canonicalUrl: string, jsonLd: ?string}}} $page */
    $page = $response->viewData('page');
    $meta = $page['props']['meta'];

    expect($meta['title'])->toBe($title)
        ->and($meta['description'])->toBe($description)
        ->and($meta['canonicalUrl'])->toBe(seoBaseUrl().$canonicalPath)
        ->and($meta['jsonLd'] !== null && $meta['jsonLd'] !== '')->toBe($hasJsonLd);
})->with('indexed pages');

test('robots.txt keeps its rules', function (): void {
    expect($this->get('/robots.txt')->getContent())->toBe(implode("\n", [
        'User-agent: *',
        'Allow: /',
        'Allow: /base-de-donnees/',
        'Allow: /character/',
        'Allow: /classements-pvp/',
        'Disallow: /api/',
        'Disallow: /auth/',
        'Disallow: /admin',
        'Disallow: /mon-compte',
        'Disallow: /my-characters',
        'Disallow: /my-score',
        'Disallow: /class-stats',
        '',
        'Sitemap: '.seoBaseUrl().'/sitemap.xml',
        '',
    ]));
});

/**
 * @return list<string>
 */
function sitemapLocations(string $xml): array
{
    preg_match_all('#<loc>([^<]+)</loc>#', $xml, $matches);

    return $matches[1];
}

test('sitemap index keeps listing the pages and database sitemaps', function (): void {
    expect(sitemapLocations((string) $this->get('/sitemap.xml')->getContent()))->toBe([
        seoBaseUrl().'/sitemap-pages.xml',
        seoBaseUrl().'/sitemap-database.xml',
    ]);
});

test('pages sitemap keeps its urls', function (): void {
    expect(sitemapLocations((string) $this->get('/sitemap-pages.xml')->getContent()))->toBe([
        seoBaseUrl(),
        seoBaseUrl().'/privacy',
        seoBaseUrl().'/cgu',
        seoBaseUrl().'/faq',
        seoBaseUrl().'/addons',
        seoBaseUrl().'/classements-pvp',
    ]);
});

test('database sitemap keeps its urls', function (): void {
    WowMount::factory()->count(3)->create(['category' => 'Volantes', 'is_active' => true]);

    expect(sitemapLocations((string) $this->get('/sitemap-database.xml')->getContent()))->toBe([
        seoBaseUrl().'/base-de-donnees',
        seoBaseUrl().'/base-de-donnees/montures',
        seoBaseUrl().'/base-de-donnees/hauts-faits',
        seoBaseUrl().'/base-de-donnees/quetes',
        seoBaseUrl().'/base-de-donnees/mascottes',
        seoBaseUrl().'/base-de-donnees/decorations',
        seoBaseUrl().'/base-de-donnees/garde-robe',
        seoBaseUrl().'/base-de-donnees/professions',
        seoBaseUrl().'/base-de-donnees/montures/volantes',
    ]);
});
