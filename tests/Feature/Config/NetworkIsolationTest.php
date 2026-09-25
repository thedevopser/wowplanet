<?php

declare(strict_types=1);

use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;

test('a request without a test double fails instead of reaching the network', function (): void {
    expect(fn () => Http::get('https://wago.tools/api/builds'))
        ->toThrow(StrayRequestException::class, 'https://wago.tools/api/builds');
});

test('a request with a test double is still answered', function (): void {
    Http::fake(['wago.tools/*' => Http::response(['wow' => []])]);

    expect(Http::get('https://wago.tools/api/builds')->json())->toBe(['wow' => []]);
});
