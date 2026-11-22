<?php

declare(strict_types=1);

use App\Http\Localization\AppLocale;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::middleware('api')->get('api/test-locale', fn (): string => app()->getLocale());
    Route::middleware('web')->get('en/test-locale', fn (): string => app()->getLocale());
});

test('an api call speaks the language of the page that sent it', function (): void {
    $this->get('/api/test-locale', [AppLocale::HEADER => 'en'])->assertSee('en');
});

test('an api call without a known page language falls back to french', function (?string $header): void {
    $headers = $header === null ? [] : [AppLocale::HEADER => $header];

    $this->get('/api/test-locale', $headers)->assertSee('fr');
})->with([
    'no header' => [null],
    'unknown language' => ['de'],
]);

test('a page keeps the language of its url whatever the header says', function (): void {
    $this->get('/en/test-locale', [AppLocale::HEADER => 'fr'])->assertSee('en');
});
