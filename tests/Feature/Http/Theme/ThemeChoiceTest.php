<?php

declare(strict_types=1);

use App\Http\Theme\ThemeChoice;
use Illuminate\Http\Request;

function requestWithThemeCookie(?string $value): Request
{
    return Request::create('/', cookies: $value === null ? [] : [ThemeChoice::COOKIE => $value]);
}

test('it reads each of the three choices from the cookie', function (string $value, ThemeChoice $themeChoice): void {
    expect(ThemeChoice::fromRequest(requestWithThemeCookie($value)))->toBe($themeChoice);
})->with([
    'system' => ['system', ThemeChoice::System],
    'dark' => ['dark', ThemeChoice::Dark],
    'light' => ['light', ThemeChoice::Light],
]);

test('it follows the system when the visitor has no cookie', function (): void {
    expect(ThemeChoice::fromRequest(requestWithThemeCookie(null)))->toBe(ThemeChoice::System);
});

test('it follows the system rather than trusting an unknown cookie value', function (string $value): void {
    expect(ThemeChoice::fromRequest(requestWithThemeCookie($value)))->toBe(ThemeChoice::System);
})->with(['purple', 'DARK', '', 'dark"><script>']);

test('it follows the system when the cookie arrives as an array', function (): void {
    $request = Request::create('/', cookies: [ThemeChoice::COOKIE => ['dark']]);

    expect(ThemeChoice::fromRequest($request))->toBe(ThemeChoice::System);
});

test('only the dark choice lets the server render the dark class', function (): void {
    expect(ThemeChoice::Dark->rendersDark())->toBeTrue()
        ->and(ThemeChoice::Light->rendersDark())->toBeFalse()
        ->and(ThemeChoice::System->rendersDark())->toBeFalse();
});
