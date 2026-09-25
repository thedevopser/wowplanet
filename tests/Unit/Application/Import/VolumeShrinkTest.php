<?php

declare(strict_types=1);

use App\Application\Import\VolumeShrink;

test('losing a tenth of the rows or more is a shrink', function (): void {
    expect(VolumeShrink::between(previous: 1_000, current: 899))->toBeTrue();
});

test('losing less than a tenth of the rows is not a shrink', function (): void {
    expect(VolumeShrink::between(previous: 1_000, current: 900))->toBeFalse();
});

test('growing is never a shrink', function (): void {
    expect(VolumeShrink::between(previous: 1_000, current: 1_200))->toBeFalse();
});

test('a table that was empty cannot have shrunk', function (): void {
    expect(VolumeShrink::between(previous: 0, current: 0))->toBeFalse();
});

test('a negative count cannot be compared', function (): void {
    VolumeShrink::between(previous: -1, current: 0);
})->throws(InvalidArgumentException::class);

test('a negative current count cannot be compared either', function (): void {
    VolumeShrink::between(previous: 10, current: -1);
})->throws(InvalidArgumentException::class, 'A row count cannot be negative.');

test('a table of a single row that empties has shrunk', function (): void {
    expect(VolumeShrink::between(previous: 1, current: 0))->toBeTrue();
});
