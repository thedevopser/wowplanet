<?php

declare(strict_types=1);

test('maintenance page renders on its own, with both themes', function (): void {
    $html = view('errors.503')->render();

    expect($html)->toContain('<h1>Maintenance en cours</h1>')
        ->and($html)->toContain('prefers-color-scheme: dark')
        ->and($html)->toContain('&copy; '.date('Y').' WowPlanet');
});
