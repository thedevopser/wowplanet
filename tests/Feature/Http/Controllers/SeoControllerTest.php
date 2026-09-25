<?php

declare(strict_types=1);

use App\Application\Services\CharacterSeoService;
use App\Models\WowMount;
use Inertia\Testing\AssertableInertia as Assert;

test('home renders HomePage via inertia with meta', function (): void {
    $mock = $this->mock(CharacterSeoService::class);
    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('getHomeMeta');
    $exp->once()->andReturn([
        'title' => 'WowPlanet',
        'description' => 'Test description',
        'ogTitle' => 'WowPlanet',
        'ogDescription' => 'Test',
        'ogImage' => 'https://example.com/image.png',
        'ogUrl' => 'https://example.com',
        'ogType' => 'website',
        'canonicalUrl' => 'https://example.com',
        'jsonLd' => '{}',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('HomePage')
            ->where('meta.title', 'WowPlanet')
            ->where('auth.isAuthenticated', false)
            ->where('auth.isAdmin', false)
        );
});

test('unknown url renders NotFoundPage with 404 status', function (): void {
    $this->get('/une-page-qui-nexiste-pas')
        ->assertStatus(404)
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('NotFoundPage')
        );
});

test('sitemap returns xml', function (): void {
    $mock = $this->mock(CharacterSeoService::class);
    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('generateSitemapIndex');
    $exp->once()->andReturn('<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></sitemapindex>');

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
});

test('robots returns plain text', function (): void {
    $testResponse = $this->get('/robots.txt');

    $testResponse->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

    $content = $testResponse->getContent();
    expect($content)->toBeString()
        ->toContain('User-agent: *')
        ->toContain('Disallow: /api/')
        ->toContain('Allow: /character/')
        ->not->toContain('Disallow: /character/')
        ->toContain('Sitemap:');
});

test('faq page renders FaqPage via inertia with unique meta and json-ld', function (): void {
    $this->get('/faq')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('FaqPage')
            ->where('meta.title', 'FAQ - Questions fréquentes | WowPlanet')
            ->where('meta.canonicalUrl', rtrim((string) config('app.url'), '/').'/faq')
            ->has('meta.jsonLd')
        );
});

test('cgu page renders CguPage via inertia with unique meta', function (): void {
    $this->get('/cgu')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('CguPage')
            ->where('meta.title', 'Conditions générales d\'utilisation | WowPlanet')
            ->where('meta.jsonLd', null)
        );
});

test('privacy page renders PrivacyPage via inertia with unique meta', function (): void {
    $this->get('/privacy')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('PrivacyPage')
            ->where('meta.title', 'Politique de confidentialité | WowPlanet')
            ->where('meta.jsonLd', null)
        );
});

test('addons page renders AddonsPage via inertia with unique meta', function (): void {
    $this->get('/addons')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('AddonsPage')
            ->where('meta.title', 'Addons WoW | WowPlanet')
            ->where('meta.canonicalUrl', rtrim((string) config('app.url'), '/').'/addons')
            ->where('meta.jsonLd', null)
        );
});

test('faq json-ld no longer promises a character search', function (): void {
    /** @var array{props: array{meta: array{jsonLd: string}}} $page */
    $page = $this->get('/faq')->viewData('page');

    expect($page['props']['meta']['jsonLd'])
        ->not->toContain('recherche de personnages')
        ->toContain('fiches publiques des personnages');
});

test('faq json-ld lists the three task reset frequencies', function (): void {
    /** @var array{props: array{meta: array{jsonLd: string}}} $page */
    $page = $this->get('/faq')->viewData('page');

    expect($page['props']['meta']['jsonLd'])
        ->toContain('quotidiennes se réinitialisent chaque jour à 5h')
        ->toContain('hebdomadaires chaque mercredi à 5h')
        ->toContain('mensuelles le 1er de chaque mois à 5h');
});

test('home serves the real catalogue counts instead of written figures', function (): void {
    WowMount::factory()->count(3)->create(['is_active' => true]);
    WowMount::factory()->create(['is_active' => false]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('HomePage')
            ->where('counts.mounts', 3)
            ->has('counts.appearances')
            ->has('counts.recipes')
        );
});
