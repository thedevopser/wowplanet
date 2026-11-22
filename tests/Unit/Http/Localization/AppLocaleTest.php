<?php

declare(strict_types=1);

use App\Http\Localization\AppLocale;

test('it reads the locale from the first segment of the path', function (string $path, AppLocale $expected): void {
    expect(AppLocale::fromPath($path))->toBe($expected);
})->with([
    'root' => ['/', AppLocale::French],
    'french page' => ['/base-de-donnees/montures', AppLocale::French],
    'english root' => ['/en', AppLocale::English],
    'english page' => ['/en/database/mounts', AppLocale::English],
    'path without leading slash' => ['en/faq', AppLocale::English],
    'segment that only starts like the prefix' => ['/entretien', AppLocale::French],
]);

test('it names the other locale', function (): void {
    expect(AppLocale::French->other())->toBe(AppLocale::English)
        ->and(AppLocale::English->other())->toBe(AppLocale::French);
});

test('each language names its Blizzard API locale', function (): void {
    expect(AppLocale::French->blizzardLocale())->toBe('fr_FR')
        ->and(AppLocale::English->blizzardLocale())->toBe('en_US');
});

test('a cache key scoped to a language names that language', function (): void {
    expect(AppLocale::English->scopedCacheKey('raid:1307'))->toBe('raid:1307:en')
        ->and(AppLocale::French->scopedCacheKey('raid:1307'))->toBe('raid:1307:fr');
});

test('an api header names a language only when it is a known one', function (?string $header, AppLocale $expected): void {
    expect(AppLocale::fromHeader($header))->toBe($expected);
})->with([
    ['en', AppLocale::English],
    ['fr', AppLocale::French],
    ['de', AppLocale::French],
    [null, AppLocale::French],
]);

test('an english page prefers the english name and falls back to the french one', function (): void {
    expect(AppLocale::English->pick('Loup', 'Wolf'))->toBe('Wolf')
        ->and(AppLocale::English->pick('Loup', null))->toBe('Loup')
        ->and(AppLocale::French->pick('Loup', 'Wolf'))->toBe('Loup');
});

test('each language links to its own Wowhead', function (): void {
    expect(AppLocale::French->wowheadBase())->toBe('https://www.wowhead.com/fr')
        ->and(AppLocale::English->wowheadBase())->toBe('https://www.wowhead.com');
});
