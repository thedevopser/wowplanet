<?php

declare(strict_types=1);

use App\Infrastructure\Taxonomy\ApiSourceTypeVocabulary;

/**
 * The collection screens keep their translations in resources/js/utils/collections.js,
 * one frozen object per collection.
 */
function collectionDictionary(string $collection): string
{
    $module = (string) file_get_contents(base_path('resources/js/utils/collections.js'));
    $start = strpos($module, sprintf('const %s = Object.freeze({', $collection));
    $end = strpos($module, "\n});", (int) $start);

    return substr($module, (int) $start, (int) $end - (int) $start);
}

test('every pending source label is already translated by the collection screens', function (string $collection): void {
    $dictionary = collectionDictionary($collection);

    $translated = array_filter(
        ApiSourceTypeVocabulary::pendingSources(),
        fn (string $pending): bool => str_contains($dictionary, sprintf('"%s":', $pending)),
    );

    expect($dictionary)->not->toBe('')
        ->and($translated)->not->toBeEmpty();
})->with(['MOUNTS', 'PETS', 'DECOR']);

test('no pending source label is left untranslated across the three screens', function (): void {
    $dictionaries = collect(['MOUNTS', 'PETS', 'DECOR'])->map(collectionDictionary(...))->implode("\n");

    foreach (ApiSourceTypeVocabulary::pendingSources() as $pending) {
        expect($dictionaries)->toContain(sprintf('"%s":', $pending));
    }
});
