<?php

declare(strict_types=1);

use App\Application\Import\ImportStage;
use App\Application\Import\StageFreshness;
use App\Models\WowImportState;

const BLIZZARD_BUILD = '12.1.0_68914';

const WAGO_BUILD = '12.1.0.69875';

function stageFreshness(): StageFreshness
{
    return resolve(StageFreshness::class);
}

test('a catalogue entity imported under the current Blizzard build is up to date', function (): void {
    WowImportState::factory()->create(['entity' => 'mounts', 'build' => BLIZZARD_BUILD]);

    expect(stageFreshness()->isUpToDate(ImportStage::Mounts, BLIZZARD_BUILD, WAGO_BUILD))->toBeTrue();
});

test('a catalogue entity left on an older Blizzard build is not', function (): void {
    WowImportState::factory()->create(['entity' => 'mounts', 'build' => '12.1.0_68000']);

    expect(stageFreshness()->isUpToDate(ImportStage::Mounts, BLIZZARD_BUILD, WAGO_BUILD))->toBeFalse();
});

test('an entity never imported is never up to date', function (): void {
    expect(stageFreshness()->isUpToDate(ImportStage::Mounts, BLIZZARD_BUILD, WAGO_BUILD))->toBeFalse();
});

test('the socle is judged against wago, never against the Blizzard build', function (): void {
    // Le pipeline inscrit pour l'étape du socle le build Blizzard du moment, comme pour
    // toute autre étape. S'y fier ferait passer pour à jour un socle que wago a dépassé,
    // et le bouton du panneau lancerait un import qui sauterait l'étape.
    WowImportState::factory()->create(['entity' => 'reference', 'build' => BLIZZARD_BUILD]);
    referenceLoadedOn('12.1.0.69587');

    expect(stageFreshness()->isUpToDate(ImportStage::Reference, BLIZZARD_BUILD, WAGO_BUILD))->toBeFalse();
});

test('a socle loaded on the build wago serves is up to date', function (): void {
    referenceLoadedOn(WAGO_BUILD);

    expect(stageFreshness()->isUpToDate(ImportStage::Reference, BLIZZARD_BUILD, WAGO_BUILD))->toBeTrue();
});

test('a socle is not up to date when wago cannot be read', function (): void {
    referenceLoadedOn(WAGO_BUILD);

    expect(stageFreshness()->isUpToDate(ImportStage::Reference, BLIZZARD_BUILD, null))->toBeFalse();
});

test('an unreadable upstream never declares anything up to date', function (): void {
    WowImportState::factory()->create(['entity' => 'mounts', 'build' => BLIZZARD_BUILD]);
    referenceLoadedOn(WAGO_BUILD);

    foreach (ImportStage::chain() as $importStage) {
        expect(stageFreshness()->isUpToDate($importStage, null, null))->toBeFalse();
    }
});
