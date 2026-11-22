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
