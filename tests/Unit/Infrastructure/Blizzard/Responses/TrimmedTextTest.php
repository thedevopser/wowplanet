<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\TrimmedText;

test('it returns the first candidate that carries something', function (): void {
    expect(TrimmedText::firstNonEmpty(null, '', 'Loup des bois', 'Timber Wolf'))->toBe('Loup des bois');
});

test('it trims the padding the API leaves around a value', function (): void {
    expect(TrimmedText::firstNonEmpty("  Zandalar et la manière\r\n"))->toBe('Zandalar et la manière');
});

test('it treats a blank candidate as absent rather than as an empty name', function (): void {
    expect(TrimmedText::firstNonEmpty('   ', 'Timber Wolf'))->toBe('Timber Wolf');
});

test('it returns nothing when no candidate carries anything', function (): void {
    expect(TrimmedText::firstNonEmpty(null, '', '  '))->toBeNull();
});

test('it returns nothing when asked without a candidate at all', function (): void {
    expect(TrimmedText::firstNonEmpty())->toBeNull();
});
