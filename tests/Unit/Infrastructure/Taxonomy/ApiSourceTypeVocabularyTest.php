<?php

declare(strict_types=1);

use App\Infrastructure\Taxonomy\ApiSourceTypeVocabulary;

test('it converts every source type the API is known to return', function (string $sourceType, string $pending): void {
    expect(ApiSourceTypeVocabulary::toPendingSource($sourceType))->toBe($pending);
})->with([
    'VENDOR' => ['VENDOR', 'Vendor'],
    'DROP' => ['DROP', 'Drop'],
    'ACHIEVEMENT' => ['ACHIEVEMENT', 'Achievement'],
    'QUEST' => ['QUEST', 'Quest'],
    'PROMOTION' => ['PROMOTION', 'Promotion'],
    'PROFESSION' => ['PROFESSION', 'Profession'],
    'PETSTORE' => ['PETSTORE', 'Blizzard Store'],
    'TCG' => ['TCG', 'Trading Card Game / Auction House'],
    'TRADINGPOST' => ['TRADINGPOST', 'Trading Post'],
    'WORLDEVENT' => ['WORLDEVENT', 'World Events'],
    'WILDPET' => ['WILDPET', 'Wild Pet'],
    'DISCOVERY' => ['DISCOVERY', 'Discovery'],
]);

test('it covers the twelve source types the API survey measured, and no more', function (): void {
    expect(ApiSourceTypeVocabulary::pendingSources())->toHaveCount(12);
});

test('it rejects a source type it does not know rather than inventing a label', function (): void {
    expect(ApiSourceTypeVocabulary::toPendingSource('DELVE'))->toBeNull();
});

test('it treats an absent source type as no pending source at all', function (): void {
    expect(ApiSourceTypeVocabulary::toPendingSource(null))->toBeNull();
});

test('it ignores the casing and the padding of a source type', function (): void {
    expect(ApiSourceTypeVocabulary::toPendingSource(' tradingpost '))->toBe('Trading Post');
});
