<?php

declare(strict_types=1);

use App\Application\Import\RowTally;

test('a table filled from empty counts every row as created', function (): void {
    $rowTally = RowTally::fromCounts(touched: 10, created: 10, rowsBefore: 0, rowsNow: 10);

    expect($rowTally->created)->toBe(10)
        ->and($rowTally->updated)->toBe(0)
        ->and($rowTally->deleted)->toBe(0);
});

test('rows touched without being created count as updated', function (): void {
    $rowTally = RowTally::fromCounts(touched: 4, created: 0, rowsBefore: 10, rowsNow: 10);

    expect($rowTally->created)->toBe(0)
        ->and($rowTally->updated)->toBe(4)
        ->and($rowTally->deleted)->toBe(0);
});

test('rows missing from the table are counted as deleted', function (): void {
    $rowTally = RowTally::fromCounts(touched: 0, created: 0, rowsBefore: 10, rowsNow: 7);

    expect($rowTally->deleted)->toBe(3);
});

test('a pass that creates, updates and deletes at once splits the three', function (): void {
    $rowTally = RowTally::fromCounts(touched: 8, created: 5, rowsBefore: 10, rowsNow: 12);

    expect($rowTally->created)->toBe(5)
        ->and($rowTally->updated)->toBe(3)
        ->and($rowTally->deleted)->toBe(3);
});

test('a pass that writes nothing tallies nothing', function (): void {
    $rowTally = RowTally::fromCounts(touched: 0, created: 0, rowsBefore: 21983, rowsNow: 21983);

    expect($rowTally)->toEqual(RowTally::none());
});

test('more rows created than touched is refused as an impossible state', function (): void {
    RowTally::fromCounts(touched: 2, created: 5, rowsBefore: 0, rowsNow: 5);
})->throws(InvalidArgumentException::class);

test('a table that grew by more than it created is refused as an impossible state', function (): void {
    RowTally::fromCounts(touched: 1, created: 1, rowsBefore: 10, rowsNow: 12);
})->throws(InvalidArgumentException::class);

test('the passes of a resumable stage add up', function (): void {
    $rowTally = RowTally::fromCounts(touched: 3, created: 3, rowsBefore: 0, rowsNow: 3)
        ->plus(RowTally::fromCounts(touched: 4, created: 1, rowsBefore: 3, rowsNow: 3));

    expect($rowTally->created)->toBe(4)
        ->and($rowTally->updated)->toBe(3)
        ->and($rowTally->deleted)->toBe(1);
});
