<?php

declare(strict_types=1);

use App\Infrastructure\Reference\ReferenceCatalog;
use App\Infrastructure\Reference\ReferenceTable;
use App\Models\WowReferenceDownload;

test('an inventory row names its file the way the store writes it', function (string $source): void {
    $referenceTable = (new ReferenceCatalog)->find($source);

    expect($referenceTable)->toBeInstanceOf(ReferenceTable::class);

    $wowReferenceDownload = WowReferenceDownload::factory()->create([
        'source_table' => $source,
        'build' => '12.1.0.69875',
    ]);

    expect($wowReferenceDownload->filename)->toBe($referenceTable->filename('12.1.0.69875'));
})->with(['Faction', 'AreaTable', 'ContentTuning', 'QuestV2CliTask', 'SkillLineAbility', 'CurrencyTypes', 'Mount', 'SpellMisc']);

test('an inventory row keeps the filename it is given', function (): void {
    $wowReferenceDownload = WowReferenceDownload::factory()->create([
        'filename' => 'leftover.csv',
    ]);

    expect($wowReferenceDownload->filename)->toBe('leftover.csv');
});
