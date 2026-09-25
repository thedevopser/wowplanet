<?php

declare(strict_types=1);

use App\Infrastructure\Reference\ReferenceStore;
use App\Models\WowReferenceDownload;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    // Le build courant vit une heure en cache : sans purge, un test sert celui du
    // précédent et la page ne dit plus ce que ce test croit vérifier.
    Cache::flush();

    fakeLiveBuild('12.1.0.69875');
});

/**
 * Http::fake() empile les doublures et la première qui correspond gagne : sans remise à
 * zéro, un test qui veut une panne amont resservirait la réponse posée en beforeEach.
 */
function fakeLiveBuild(?string $build): void
{
    app()->forgetInstance(Factory::class);
    Http::clearResolvedInstances();

    Http::fake([
        'wago.tools/api/builds' => $build === null
            ? Http::response(status: 503)
            : Http::response(['wow' => [['product' => 'wow', 'version' => $build]]]),
    ]);
}

function loaded(string $source, string $build, int $rows, string $at): WowReferenceDownload
{
    return WowReferenceDownload::factory()->create([
        'source_table' => $source,
        'build' => $build,
        'bytes' => 1_000,
        'row_count' => $rows,
        'downloaded_at' => $at,
    ]);
}

test('the reference page renders AdminReferencePage for an administrator', function (): void {
    $this->withSession(['is_admin' => true])
        ->get('/admin/reference')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert->component('AdminReferencePage'));
});

test('the reference page is closed to anyone else', function (): void {
    $this->get('/admin/reference')->assertRedirect('/');
});

test('the page lists the eight tables of the socle', function (): void {
    $this->withSession(['is_admin' => true])
        ->get('/admin/reference')
        ->assertInertia(fn (Assert $assert): Assert => $assert->has('tables', 8)->etc());
});

test('the page carries the build wago serves, to situate the socle against it', function (): void {
    $this->withSession(['is_admin' => true])
        ->get('/admin/reference')
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('liveBuild', '12.1.0.69875')->etc());
});

test('a socle left behind on an older build reaches the page flagged', function (): void {
    loaded('Faction', '12.1.0.69587', 868, '2026-09-12 16:17:46');

    $this->withSession(['is_admin' => true])
        ->get('/admin/reference')
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('tables.0.source', 'Faction')
            ->where('tables.0.build', '12.1.0.69587')
            ->where('tables.0.is_stale', true)
            ->etc());
});

test('the page carries the contents of the store, so the disk stops drifting unseen', function (): void {
    Storage::fake(ReferenceStore::DISK);

    $wowReferenceDownload = loaded('Faction', '12.1.0.69875', 868, '2026-09-20 08:55:02');
    $obsolete = loaded('Faction', '12.1.0.69587', 868, '2026-09-12 16:17:46');
    Storage::disk(ReferenceStore::DISK)->put($wowReferenceDownload->filename, str_repeat('x', 100));
    Storage::disk(ReferenceStore::DISK)->put($obsolete->filename, str_repeat('x', 90));

    $this->withSession(['is_admin' => true])
        ->get('/admin/reference')
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->has('store.files', 2)
            ->where('store.totals.bytes', 190)
            ->where('store.totals.sweepable_bytes', 90)
            ->etc());
});

test('an empty store still renders the page', function (): void {
    Storage::fake(ReferenceStore::DISK);

    $this->withSession(['is_admin' => true])
        ->get('/admin/reference')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert->has('store.files', 0)->etc());
});

test('an unreachable wago still renders the page, without a build to compare to', function (): void {
    fakeLiveBuild(null);

    $this->withSession(['is_admin' => true])
        ->get('/admin/reference')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('liveBuild', null)->etc());
});
