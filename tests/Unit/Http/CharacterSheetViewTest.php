<?php

declare(strict_types=1);

use App\Http\Character\CharacterSheetSection;
use App\Http\Character\CharacterSheetView;
use App\Http\Character\UnknownCharacterSheetSegment;

test('no segment opens the overview', function (): void {
    $characterSheetView = CharacterSheetView::fromSegments(null, null);

    expect($characterSheetView->section)->toBeNull()
        ->and($characterSheetView->sub)->toBeNull()
        ->and($characterSheetView->isOverview())->toBeTrue();
});

test('a section alone opens its first sub-tab', function (string $section, string $first): void {
    $characterSheetView = CharacterSheetView::fromSegments($section, null);

    expect($characterSheetView->section)->toBe(CharacterSheetSection::from($section))
        ->and($characterSheetView->sub)->toBe($first)
        ->and($characterSheetView->isOverview())->toBeFalse();
})->with([
    ['progression', 'quetes'],
    ['endgame', 'mythique-plus'],
    ['collections', 'montures'],
]);

test('each section knows its sub-tabs, in the order of the sheet', function (): void {
    expect(CharacterSheetSection::Progression->subTabs())->toBe(['quetes', 'hauts-faits', 'reputations', 'metiers'])
        ->and(CharacterSheetSection::Endgame->subTabs())->toBe(['mythique-plus', 'raids', 'pvp', 'equipement'])
        ->and(CharacterSheetSection::Collections->subTabs())->toBe(['montures', 'mascottes', 'decorations', 'garde-robe']);
});

test('a known sub-tab is kept', function (): void {
    $characterSheetView = CharacterSheetView::fromSegments('collections', 'garde-robe');

    expect($characterSheetView->section)->toBe(CharacterSheetSection::Collections)
        ->and($characterSheetView->sub)->toBe('garde-robe');
});

test('an unknown segment is refused', function (?string $section, ?string $sub): void {
    CharacterSheetView::fromSegments($section, $sub);
})->with([
    'unknown section' => ['inventaire', null],
    'sub-tab of another section' => ['progression', 'montures'],
    'unknown sub-tab' => ['endgame', 'arene'],
    'sub-tab without section' => [null, 'quetes'],
])->throws(UnknownCharacterSheetSegment::class);
