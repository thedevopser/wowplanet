<?php

declare(strict_types=1);

use App\Http\Localization\AppLocale;
use App\Http\Localization\LocaleSuggestion;
use Illuminate\Http\Request;

function requestWith(?string $acceptLanguage, ?string $localeCookie = null): Request
{
    $request = Request::create('/', cookies: $localeCookie === null ? [] : [AppLocale::COOKIE => $localeCookie]);

    $request->headers->set('Accept-Language', $acceptLanguage);

    return $request;
}

test('it suggests the language of the browser when the page is in the other one', function (string $acceptLanguage, AppLocale $page, AppLocale $expected): void {
    expect(LocaleSuggestion::for(requestWith($acceptLanguage), $page))->toBe($expected);
})->with([
    'english browser on a french page' => ['en-US,en;q=0.9', AppLocale::French, AppLocale::English],
    'german browser on a french page' => ['de-DE,de;q=0.9,en;q=0.8', AppLocale::French, AppLocale::English],
    'french browser on an english page' => ['fr-FR,fr;q=0.9,en;q=0.8', AppLocale::English, AppLocale::French],
    'belgian french browser on an english page' => ['fr-BE', AppLocale::English, AppLocale::French],
]);

test('it suggests nothing when the page is already in the language of the browser', function (string $acceptLanguage, AppLocale $page): void {
    expect(LocaleSuggestion::for(requestWith($acceptLanguage), $page))->toBeNull();
})->with([
    ['fr-FR,fr;q=0.9', AppLocale::French],
    ['en-GB,en;q=0.9', AppLocale::English],
    ['de-DE', AppLocale::English],
]);

test('it suggests nothing to a client that announces no language, as crawlers do', function (): void {
    expect(LocaleSuggestion::for(requestWith(null), AppLocale::French))->toBeNull();
});

test('it suggests nothing once the visitor has chosen a language', function (string $choice): void {
    expect(LocaleSuggestion::for(requestWith('en-US', $choice), AppLocale::French))->toBeNull();
})->with(['fr', 'en']);

test('it ignores a cookie holding an unknown language', function (): void {
    expect(LocaleSuggestion::for(requestWith('en-US', 'de'), AppLocale::French))->toBe(AppLocale::English);
});
