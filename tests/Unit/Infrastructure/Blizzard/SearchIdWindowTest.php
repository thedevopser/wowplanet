<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\SearchIdWindow;

test('the window count covers the whole range of ids', function (): void {
    expect(SearchIdWindow::countFor(285062))->toBe(286)
        ->and(SearchIdWindow::countFor(999))->toBe(1)
        ->and(SearchIdWindow::countFor(1000))->toBe(2)
        ->and(SearchIdWindow::countFor(0))->toBe(1);
});

test('a negative highest id is rejected', function (): void {
    SearchIdWindow::countFor(-1);
})->throws(InvalidArgumentException::class, 'A highest id cannot be negative, got -1.');

test('a window carries the page size, the order and its own interval', function (): void {
    expect(SearchIdWindow::query(0))->toBe('_pageSize=1000&orderby=id&id=[0,999]')
        ->and(SearchIdWindow::query(41))->toBe('_pageSize=1000&orderby=id&id=[41000,41999]');
});

test('a negative window index is rejected', function (): void {
    SearchIdWindow::query(-1);
})->throws(InvalidArgumentException::class, 'A window index cannot be negative, got -1.');

test('a negative id belongs to no window', function (): void {
    SearchIdWindow::holding(-1);
})->throws(InvalidArgumentException::class, 'An id cannot be negative, got -1.');

test('the window holding an id is the one whose interval contains it', function (): void {
    expect(SearchIdWindow::holding(0))->toBe(0)
        ->and(SearchIdWindow::holding(999))->toBe(0)
        ->and(SearchIdWindow::holding(1000))->toBe(1)
        ->and(SearchIdWindow::holding(41802))->toBe(41);
});
