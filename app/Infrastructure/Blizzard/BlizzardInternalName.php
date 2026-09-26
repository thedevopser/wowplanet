<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard;

/**
 * Development names that the Blizzard API serves as is: placeholders, unreleased or
 * deprecated objects. Markers only count in upper case, so French names such as
 * « Test de courage » stay released.
 */
final class BlizzardInternalName
{
    private const string LEADING_TAG = '/^[\[<]/u';

    private const string LEADING_PATCH_NUMBER = '/^\d+\.\d+(\.\d+)*\s/u';

    private const string UPPER_CASE_MARKER = '/(?<![\p{L}\d])(TBD|NYI|DNT|TXT|TEST)(?![\p{L}\d])/u';

    /**
     * @var list<string>
     */
    // @pest-mutate-ignore
    private const array BARE_TEST_NAMES = ['Test', 'Test Quest'];

    public static function isInternal(string $name): bool
    {
        $trimmed = trim($name);

        return in_array($trimmed, self::BARE_TEST_NAMES, true)
            || preg_match(self::LEADING_TAG, $trimmed) === 1
            || preg_match(self::LEADING_PATCH_NUMBER, $trimmed) === 1
            || preg_match(self::UPPER_CASE_MARKER, $trimmed) === 1;
    }
}
