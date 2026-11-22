<?php

declare(strict_types=1);

use App\Http\Localization\AppLocale;
use App\Http\Localization\Exceptions\InvalidLocalizedRouteTable;
use App\Http\Localization\Exceptions\UnknownLocalizedRoute;
use App\Http\Localization\LocalizedRouteTable;

function sampleRouteTable(): LocalizedRouteTable
{
    return new LocalizedRouteTable([
        'home' => ['fr' => '/', 'en' => '/en'],
        'database.mounts' => ['fr' => '/base-de-donnees/montures/{category?}', 'en' => '/en/database/mounts/{category?}'],
        'character' => ['fr' => '/character/{realm}/{name}/{section?}', 'en' => '/en/character/{realm}/{name}/{section?}'],
    ]);
}

test('it gives the path of a route in each locale', function (): void {
    expect(sampleRouteTable()->path('database.mounts', AppLocale::French))->toBe('/base-de-donnees/montures/{category?}')
        ->and(sampleRouteTable()->path('database.mounts', AppLocale::English))->toBe('/en/database/mounts/{category?}');
});

test('it refuses a route it does not know', function (): void {
    sampleRouteTable()->path('unknown', AppLocale::French);
})->throws(UnknownLocalizedRoute::class);

test('it lists the names of its routes', function (): void {
    expect(sampleRouteTable()->names())->toBe(['home', 'database.mounts', 'character']);
});

test('it translates a concrete path into the other locale', function (string $path, AppLocale $target, string $expected): void {
    expect(sampleRouteTable()->alternate($path, $target))->toBe($expected);
})->with([
    'home to english' => ['/', AppLocale::English, '/en'],
    'home to french' => ['/en', AppLocale::French, '/'],
    'without optional parameter' => ['/base-de-donnees/montures', AppLocale::English, '/en/database/mounts'],
    'with optional parameter' => ['/base-de-donnees/montures/racial', AppLocale::English, '/en/database/mounts/racial'],
    'english to french' => ['/en/database/mounts/racial', AppLocale::French, '/base-de-donnees/montures/racial'],
    'same locale' => ['/base-de-donnees/montures', AppLocale::French, '/base-de-donnees/montures'],
    'trailing slash' => ['/base-de-donnees/montures/', AppLocale::English, '/en/database/mounts'],
]);

test('it keeps parameter values untouched, even when they look like a translated segment', function (): void {
    expect(sampleRouteTable()->alternate('/character/dalaran/montures', AppLocale::English))
        ->toBe('/en/character/dalaran/montures');
});

test('it has no alternate for a path outside the table', function (string $path): void {
    expect(sampleRouteTable()->alternate($path, AppLocale::English))->toBeNull();
})->with(['/admin', '/base-de-donnees/montures/a/b', '/en/admin']);

test('it refuses a route missing one of the locales', function (): void {
    new LocalizedRouteTable(['home' => ['fr' => '/']]);
})->throws(InvalidLocalizedRouteTable::class);

test('it refuses english paths outside the english prefix', function (): void {
    new LocalizedRouteTable(['home' => ['fr' => '/', 'en' => '/home']]);
})->throws(InvalidLocalizedRouteTable::class);

test('it refuses two locales whose parameters differ', function (): void {
    new LocalizedRouteTable(['page' => ['fr' => '/page/{slug}', 'en' => '/en/page/{id}']]);
})->throws(InvalidLocalizedRouteTable::class);

test('it reads the table shared with the front end', function (): void {
    $table = LocalizedRouteTable::fromFile(dirname(__DIR__, 4).'/resources/js/i18n/localizedRoutes.json');

    expect($table->alternate('/base-de-donnees/montures', AppLocale::English))->toBe('/en/database/mounts');
});

test('it refuses a file that does not hold a table', function (): void {
    LocalizedRouteTable::fromFile('data://application/json,["not", "a", "table"]');
})->throws(InvalidLocalizedRouteTable::class);
