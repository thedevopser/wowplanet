<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia;

function htmlLangOf(string $content): string
{
    preg_match('/<html[^>]*\blang="([^"]*)"/', $content, $match);

    return $match[1] ?? '';
}

test('it keeps serving the french pages at their historical urls', function (string $path, string $component): void {
    $this->get($path)->assertOk()->assertInertia(
        fn (AssertableInertia $page): AssertableInertia => $page->component($component)->where('locale', 'fr')
    );
})->with([
    ['/', 'HomePage'],
    ['/faq', 'FaqPage'],
    ['/cgu', 'CguPage'],
    ['/privacy', 'PrivacyPage'],
    ['/addons', 'AddonsPage'],
]);

test('it serves the english version of a page under the english prefix', function (string $path, string $component): void {
    $this->get($path)->assertOk()->assertInertia(
        fn (AssertableInertia $page): AssertableInertia => $page->component($component)->where('locale', 'en')
    );
})->with([
    ['/en', 'HomePage'],
    ['/en/faq', 'FaqPage'],
    ['/en/terms', 'CguPage'],
    ['/en/privacy', 'PrivacyPage'],
    ['/en/addons', 'AddonsPage'],
]);

test('it applies the locale of the url to the application', function (): void {
    $this->get('/en/faq');

    expect(app()->getLocale())->toBe('en');
});

test('it declares the language of the page on the html element', function (string $path, string $lang): void {
    expect(htmlLangOf((string) $this->get($path)->getContent()))->toBe($lang);
})->with([['/faq', 'fr'], ['/en/faq', 'en']]);

test('it shares the same page in both locales, query string included', function (): void {
    $this->get('/en/faq?tab=import')->assertInertia(
        fn (AssertableInertia $page): AssertableInertia => $page
            ->where('alternateUrls.fr', url('/faq').'?tab=import')
            ->where('alternateUrls.en', url('/en/faq').'?tab=import')
    );
});

test('it renders the not found page in the language of an unknown url', function (string $path, string $locale): void {
    $this->get($path)->assertNotFound()->assertInertia(
        fn (AssertableInertia $page): AssertableInertia => $page->component('NotFoundPage')
            ->where('locale', $locale)
            ->where('alternateUrls', null)
    );
})->with([['/page-inexistante', 'fr'], ['/en/missing-page', 'en']]);

test('it offers no english version of the pages kept out of translation', function (string $path): void {
    $this->get($path)->assertNotFound();
})->with(['/en/admin', '/en/docs', '/en/ui', '/en/mon-compte', '/en/base-de-donnees']);
