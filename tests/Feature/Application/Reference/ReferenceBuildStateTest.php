<?php

declare(strict_types=1);

use App\Application\Reference\ReferenceBuildState;
use App\Infrastructure\Reference\ReferenceCatalog;
use App\Models\WowReferenceDownload;

function referenceBuildState(): ReferenceBuildState
{
    return resolve(ReferenceBuildState::class);
}

test('it reports the build of the socle last load', function (): void {
    referenceLoadedOn('12.1.0.69875');

    expect(referenceBuildState()->current('12.1.0.69875')['build'])->toBe('12.1.0.69875');
});

test('a socle fully loaded on the live build is current', function (): void {
    referenceLoadedOn('12.1.0.69875');

    $state = referenceBuildState()->current('12.1.0.69875');

    expect($state['tables_behind'])->toBe(0)
        ->and($state['tables_total'])->toBe(count((new ReferenceCatalog)->sources()));
});

test('a table left on an older build puts the socle behind', function (): void {
    referenceLoadedOn('12.1.0.69875', '2026-09-21 10:00:00');
    // Faction est restée sur le build précédent : c'est son dernier chargement, même si
    // les autres tables ont été rechargées depuis.
    WowReferenceDownload::factory()->create([
        'source_table' => 'Faction',
        'build' => '12.1.0.69587',
        'downloaded_at' => '2026-09-21 11:00:00',
    ]);

    expect(referenceBuildState()->current('12.1.0.69875')['tables_behind'])->toBe(1);
});

test('a table never loaded puts the socle behind', function (): void {
    foreach ((new ReferenceCatalog)->sources() as $source) {
        if ($source === 'Faction') {
            continue;
        }

        WowReferenceDownload::factory()->create([
            'source_table' => $source,
            'build' => '12.1.0.69875',
            'downloaded_at' => '2026-09-20 10:00:00',
        ]);
    }

    expect(referenceBuildState()->current('12.1.0.69875')['tables_behind'])->toBe(1);
});

test('without a live build to compare to, nothing is behind', function (): void {
    referenceLoadedOn('12.1.0.69587');

    $state = referenceBuildState()->current(null);

    expect($state['tables_behind'])->toBe(0)
        ->and($state['build'])->toBe('12.1.0.69587');
});

test('it dates the socle by its most recent load', function (): void {
    referenceLoadedOn('12.1.0.69587', '2026-09-20 10:00:00');
    WowReferenceDownload::factory()->create([
        'source_table' => 'Faction',
        'build' => '12.1.0.69875',
        'downloaded_at' => '2026-09-21 08:30:00',
    ]);

    $state = referenceBuildState()->current('12.1.0.69875');

    expect($state['loaded_at'])->toBe(now()->parse('2026-09-21 08:30:00')->toIso8601String())
        ->and($state['build'])->toBe('12.1.0.69875');
});

test('a socle never loaded at all is behind on every table', function (): void {
    $state = referenceBuildState()->current('12.1.0.69875');

    expect($state['build'])->toBeNull()
        ->and($state['loaded_at'])->toBeNull()
        ->and($state['tables_behind'])->toBe(count((new ReferenceCatalog)->sources()));
});
