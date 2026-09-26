<?php

declare(strict_types=1);

/**
 * Parallel test processes share the system temporary directory: each test works in a
 * directory of its own, separated by process and by test.
 */
test('two paths asked under the same name never collide', function (): void {
    expect(testTempPath('taxonomy'))->not->toBe(testTempPath('taxonomy'));
});

test('the path lives under the directory of its test process', function (): void {
    expect(testTempPath('taxonomy'))
        ->toStartWith(sys_get_temp_dir().'/wowplanet-test-'.(getenv('TEST_TOKEN') ?: '0').'/');
});

test('the path ends with the requested name followed by a random suffix', function (): void {
    expect(testTempPath('taxonomy'))->toMatch('#/taxonomy-[0-9a-f]{16}$#');
});

test('the directory of the test process exists, so a file can be written straight into it', function (): void {
    expect(dirname(testTempPath('coverage-crap')))->toBeDirectory();
});

test('a temporary path without a name is refused', function (): void {
    testTempPath('');
})->throws(InvalidArgumentException::class);
