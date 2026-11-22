<?php

declare(strict_types=1);

use App\Application\Services\CharacterSeoService;
use Inertia\Testing\AssertableInertia;

/**
 * @return array<string, string>
 */
function hreflangLinksOf(string $content): array
{
    preg_match_all('/<link rel="alternate" hreflang="([^"]+)" href="([^"]+)">/', $content, $matches, PREG_SET_ORDER);

    return array_column($matches, 2, 1);
}

test('every page announces both language versions to search engines', function (string $path): void {
    expect(hreflangLinksOf((string) $this->get($path)->getContent()))->toBe([
        'fr' => url('/faq'),
        'en' => url('/en/faq'),
        'x-default' => url('/faq'),
    ]);
})->with(['/faq', '/en/faq']);

test('the language versions announced to search engines drop the query string', function (): void {
    expect(hreflangLinksOf((string) $this->get('/en/faq?utm_source=x')->getContent())['en'])->toBe(url('/en/faq'));
});

test('a page that exists in one language only announces no alternate version', function (): void {
    expect(hreflangLinksOf((string) $this->get('/page-inexistante')->getContent()))->toBe([]);
});

test('an english page carries english meta and its own canonical url', function (string $path, string $title): void {
    $this->get($path)->assertInertia(
        fn (AssertableInertia $page): AssertableInertia => $page
            ->where('meta.title', $title)
            ->where('meta.canonicalUrl', url($path))
            ->where('meta.ogUrl', url($path))
    );
})->with([
    ['/en/faq', 'FAQ - Frequently asked questions | WowPlanet'],
    ['/en/terms', 'Terms of use | WowPlanet'],
    ['/en/privacy', 'Privacy policy | WowPlanet'],
    ['/en/addons', 'WoW addons | WowPlanet'],
    ['/en/pvp-leaderboards', 'PvP leaderboards | WowPlanet'],
]);

test('the english home page carries english meta', function (): void {
    $this->get('/en')->assertInertia(
        fn (AssertableInertia $page): AssertableInertia => $page
            ->where('meta.title', 'WowPlanet - World of Warcraft progress tracker')
            ->where('meta.canonicalUrl', url('/en'))
    );
});

test('a french page keeps its french meta and canonical url', function (): void {
    $this->get('/cgu')->assertInertia(
        fn (AssertableInertia $page): AssertableInertia => $page
            ->where('meta.title', "Conditions générales d'utilisation | WowPlanet")
            ->where('meta.canonicalUrl', url('/cgu'))
    );
});

test('the meta of a character follow the language of the page', function (): void {
    app()->setLocale('en');

    $meta = resolve(CharacterSeoService::class)->buildNotFoundCharacterMeta('dalaran', 'gothdroid');

    expect($meta['canonicalUrl'])->toBe(url('/en/character/dalaran/gothdroid'))
        ->and($meta['description'])->toStartWith('Profile of the character Gothdroid on Dalaran.');
});

test('the pages sitemap lists both language versions of each page with their alternates', function (): void {
    $xml = (string) $this->get('/sitemap-pages.xml')->getContent();

    expect($xml)->toContain('<loc>'.url('/en/faq').'</loc>')
        ->and($xml)->toContain('<loc>'.url('/faq').'</loc>')
        ->and($xml)->toContain('hreflang="en" href="'.url('/en/faq').'"')
        ->and($xml)->toContain('hreflang="x-default" href="'.url('/faq').'"');
});

test('the database sitemap lists both language versions of each page', function (): void {
    $xml = (string) $this->get('/sitemap-database.xml')->getContent();

    expect($xml)->toContain('<loc>'.url('/en/database').'</loc>')
        ->and($xml)->toContain('hreflang="fr" href="'.url('/base-de-donnees').'"');
});

test('the structured faq follows the language of the page', function (): void {
    $this->get('/en/faq')->assertInertia(
        fn (AssertableInertia $page): AssertableInertia => $page->where('meta.jsonLd', fn (string $jsonLd): bool => str_contains($jsonLd, 'What is WowPlanet?') && str_contains($jsonLd, '"inLanguage":"en"'))
    );
});
