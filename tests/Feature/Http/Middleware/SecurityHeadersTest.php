<?php

declare(strict_types=1);

use App\Http\Theme\ThemeBootScript;

test('the production content security policy allows the inlined theme boot script and nothing else inline', function (): void {
    $this->app['env'] = 'production';

    $policy = (string) $this->get('/faq')->headers->get('Content-Security-Policy');

    preg_match('/script-src ([^;]+);/', $policy, $scriptSources);

    expect($scriptSources[1] ?? '')->toContain((new ThemeBootScript)->cspSource())
        ->not->toContain("'unsafe-inline'");
});

test('the hash in the policy matches the script actually served in the page', function (): void {
    $this->app['env'] = 'production';

    $response = $this->get('/faq');
    preg_match('/<script>(.*?)<\/script>/s', (string) $response->getContent(), $inline);
    $hash = base64_encode(hash('sha256', $inline[1] ?? '', true));

    expect((string) $response->headers->get('Content-Security-Policy'))->toContain(sprintf("'sha256-%s'", $hash));
});

test('the production content security policy no longer opens the door to Google Fonts', function (): void {
    $this->app['env'] = 'production';

    $policy = (string) $this->get('/faq')->headers->get('Content-Security-Policy');

    expect($policy)->not->toContain('fonts.googleapis.com')
        ->not->toContain('fonts.gstatic.com')
        ->toContain("font-src 'self';");
});

test('no content security policy is sent outside production', function (): void {
    expect($this->get('/faq')->headers->has('Content-Security-Policy'))->toBeFalse();
});
