<?php

declare(strict_types=1);

use App\Infrastructure\Reference\CopyText;

test('it joins values with a tabulation', function (): void {
    expect(CopyText::line(['1', '2', 'Hurlevent']))->toBe("1\t2\tHurlevent");
});

test('it writes a null as the marker COPY expects', function (): void {
    expect(CopyText::line(['1', null]))->toBe("1\t\\N");
});

test('it keeps an empty string distinct from a null', function (): void {
    expect(CopyText::line(['1', '']))->toBe("1\t");
});

test('it escapes the characters that would break the row layout', function (string $raw, string $encoded): void {
    expect(CopyText::line([$raw]))->toBe($encoded);
})->with([
    'backslash' => ['a\\b', 'a\\\\b'],
    'tabulation' => ["a\tb", 'a\\tb'],
    'line feed' => ["a\nb", 'a\\nb'],
    'carriage return' => ["a\rb", 'a\\rb'],
]);

test('it escapes a backslash before anything else so an escape is never re-escaped', function (): void {
    expect(CopyText::line(["\\\t"]))->toBe('\\\\\\t');
});

test('it leaves quotes and commas alone, which the text format does not treat as special', function (): void {
    expect(CopyText::line(['Gilde "des" braves, tome 1']))->toBe('Gilde "des" braves, tome 1');
});
