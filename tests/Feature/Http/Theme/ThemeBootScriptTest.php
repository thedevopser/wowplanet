<?php

declare(strict_types=1);

use App\Http\Theme\ThemeBootScript;

test('it inlines the boot script exactly as it is written in resources', function (): void {
    $expected = trim((string) file_get_contents(resource_path('js/themeBoot.js')));

    expect((new ThemeBootScript)->source())->toBe($expected);
});

test('it gives the content security policy the hash of the inlined script', function (): void {
    $script = new ThemeBootScript;
    $hash = base64_encode(hash('sha256', $script->source(), true));

    expect($script->cspSource())->toBe(sprintf("'sha256-%s'", $hash));
});
