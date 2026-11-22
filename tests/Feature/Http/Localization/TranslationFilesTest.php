<?php

declare(strict_types=1);

/**
 * @return array<string, string>
 */
function translationsOf(string $locale): array
{
    /** @var array<string, string> $translations */
    $translations = json_decode((string) file_get_contents(lang_path($locale.'.json')), true, flags: JSON_THROW_ON_ERROR);

    return $translations;
}

test('every translation key exists in both languages', function (): void {
    $french = array_keys(translationsOf('fr'));
    $english = array_keys(translationsOf('en'));

    expect(array_values(array_diff($french, $english)))->toBe([], 'Keys missing from en.json')
        ->and(array_values(array_diff($english, $french)))->toBe([], 'Keys missing from fr.json');
});

test('no translation is left empty', function (string $locale): void {
    expect(array_filter(translationsOf($locale), fn (string $text): bool => trim($text) === ''))->toBe([]);
})->with(['fr', 'en']);

test('laravel reads the translation of the current locale', function (): void {
    app()->setLocale('en');

    expect(__('locale.name'))->toBe('English');
});
