<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

test('it pings search engines', function (): void {
    Http::fake([
        'www.bing.com/ping*' => Http::response('OK', 200),
    ]);

    $this->artisan('app:ping-search-engines')
        ->assertExitCode(0);

    Http::assertSent(fn (\Illuminate\Http\Client\Request $request): bool => str_contains($request->url(), 'www.bing.com/ping'));
});

test('it handles failures gracefully', function (): void {
    Http::fake([
        'www.bing.com/ping*' => Http::response('Server Error', 500),
    ]);

    // Command always returns SUCCESS regardless of Bing response
    $this->artisan('app:ping-search-engines')
        ->assertExitCode(0);
});
