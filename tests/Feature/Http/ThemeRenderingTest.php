<?php

declare(strict_types=1);

use App\Http\Theme\ThemeBootScript;
use App\Http\Theme\ThemeChoice;
use Inertia\Testing\AssertableInertia;

function htmlOpeningTag(string $content): string
{
    preg_match('/<html[^>]*>/', $content, $match);

    return $match[0] ?? '';
}

test('it renders the dark class for a visitor who chose the dark theme', function (): void {
    $response = $this->withUnencryptedCookie(ThemeChoice::COOKIE, 'dark')->get('/faq');

    expect(htmlOpeningTag((string) $response->getContent()))->toContain('class="dark"');
});

test('it renders no dark class for a visitor who chose the light theme', function (): void {
    $response = $this->withUnencryptedCookie(ThemeChoice::COOKIE, 'light')->get('/faq');

    expect(htmlOpeningTag((string) $response->getContent()))->not->toContain('dark');
});

test('it leaves the class to the boot script when the visitor follows the system', function (?string $cookie): void {
    $request = $cookie === null ? $this : $this->withUnencryptedCookie(ThemeChoice::COOKIE, $cookie);

    $content = (string) $request->get('/faq')->getContent();

    expect(htmlOpeningTag($content))->not->toContain('dark')
        ->and($content)->toContain('<script>'.(new ThemeBootScript)->source().'</script>');
})->with(['no cookie' => [null], 'system' => ['system'], 'unknown value' => ['purple']]);

test('it runs the boot script before any stylesheet', function (): void {
    $content = (string) $this->get('/faq')->getContent();

    $script = strpos($content, (new ThemeBootScript)->source());
    $stylesheet = strpos($content, 'rel="stylesheet"');

    expect($script)->toBeInt()
        ->and($stylesheet === false || $script < $stylesheet)->toBeTrue();
});

test('it shares the validated choice with the pages', function (?string $cookie, string $expected): void {
    $request = $cookie === null ? $this : $this->withUnencryptedCookie(ThemeChoice::COOKIE, $cookie);

    $request->get('/faq')->assertInertia(
        fn (AssertableInertia $assertableInertia): AssertableInertia => $assertableInertia->where('theme', $expected)
    );
})->with([
    'no cookie' => [null, 'system'],
    'dark' => ['dark', 'dark'],
    'light' => ['light', 'light'],
    'unknown value' => ['purple', 'system'],
]);

test('it gives the browser the page background of each theme', function (): void {
    $content = (string) $this->get('/faq')->getContent();

    expect($content)->toContain('name="theme-color" content="#0b0f1a" media="(prefers-color-scheme: dark)"')
        ->and($content)->toContain('name="theme-color" content="#f7f4ec" media="(prefers-color-scheme: light)"');
});

test('the installed site opens on the dark page background', function (): void {
    /** @var array{theme_color: string, background_color: string} $manifest */
    $manifest = json_decode((string) file_get_contents(public_path('site.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['theme_color'])->toBe('#0b0f1a')
        ->and($manifest['background_color'])->toBe('#0b0f1a');
});

test('the theme cookie is read in clear, as the boot script writes it', function (): void {
    $response = $this->withCookie(ThemeChoice::COOKIE, 'dark')->get('/faq');

    expect(htmlOpeningTag((string) $response->getContent()))->not->toContain('class="dark"');
});
