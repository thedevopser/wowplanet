<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Vite;

const INTER_LATIN = 'node_modules/@fontsource-variable/inter/files/inter-latin-wght-normal.woff2';

test('it preloads the latin subset of Inter from the site itself', function (): void {
    $content = (string) $this->get('/faq')->getContent();

    expect($content)->toContain(sprintf(
        '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>',
        Vite::asset(INTER_LATIN),
    ));
});

test('it preloads the two weights of Cinzel that title every page', function (string $weight): void {
    $content = (string) $this->get('/faq')->getContent();

    expect($content)->toContain(sprintf(
        '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>',
        Vite::asset(sprintf('node_modules/@fontsource/cinzel/files/cinzel-latin-%s-normal.woff2', $weight)),
    ));
})->with(['600', '700']);

test('it no longer calls Google Fonts nor forces Outfit on the body', function (): void {
    $content = (string) $this->get('/faq')->getContent();

    expect($content)->not->toContain('fonts.googleapis.com')
        ->not->toContain('fonts.gstatic.com')
        ->not->toContain('Outfit');
});

test('the maintenance page no longer calls Google Fonts either', function (): void {
    $content = view('errors.503')->render();

    expect($content)->not->toContain('fonts.googleapis.com')
        ->not->toContain('fonts.gstatic.com')
        ->not->toContain('Outfit');
});
