<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardNamespace;

test('it extracts the build from a static namespace', function (): void {
    expect(BlizzardNamespace::build('static-12.1.0_68914-eu'))->toBe('12.1.0_68914');
});

test('it extracts the build from a dynamic namespace', function (): void {
    expect(BlizzardNamespace::build('dynamic-12.1.0_68914-us'))->toBe('12.1.0_68914');
});

test('a namespace without a build carries none', function (): void {
    expect(BlizzardNamespace::build('profile-eu'))->toBeNull()
        ->and(BlizzardNamespace::build('static-eu'))->toBeNull();
});

test('an empty header carries no build', function (): void {
    expect(BlizzardNamespace::build(''))->toBeNull();
});

test('an unexpected header shape carries no build rather than a wrong one', function (): void {
    expect(BlizzardNamespace::build('static-12.1.0_68914-eu-extra'))->toBeNull();
});
