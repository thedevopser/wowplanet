<?php

declare(strict_types=1);

use App\Application\Import\ImportStage;
use App\Models\WowImportState;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config([
        'services.blizzard.client_id' => 'test-client-id',
        'services.blizzard.client_secret' => 'test-client-secret',
        'services.blizzard.region' => 'eu',
    ]);
    Cache::flush();
});

/**
 * La prop différée n'est pas dans la réponse initiale : on rejoue la requête partielle
 * que le client enverrait, sans l'en-tête `X-Inertia` — avec lui la réponse devient du
 * JSON, et `assertInertia` attend une vue.
 */
function dashboardBanner(): Assert
{
    $assertable = null;

    test()->withSession(['is_admin' => true])
        ->get('/admin', [
            'X-Inertia-Partial-Component' => 'AdminDashboardPage',
            'X-Inertia-Partial-Data' => 'buildStatus',
        ])
        ->assertInertia(function (Assert $assert) use (&$assertable): void {
            $assertable = $assert;
        });

    return $assertable;
}

test('the dashboard renders for an administrator', function (): void {
    $this->withSession(['is_admin' => true])
        ->get('/admin')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert->component('AdminDashboardPage'));
});

test('the banner is deferred, so a slow upstream never holds the panel back', function (): void {
    $this->withSession(['is_admin' => true])
        ->get('/admin')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->has('pendingTaxonomy')
            ->missing('buildStatus'));
});

test('the deferred banner carries the comparison once the client asks for it', function (): void {
    stubBlizzardBuild('12.1.0_68914');
    stubWagoBuild('12.1.0.69875');
    referenceLoadedOn('12.1.0.69875');

    foreach (ImportStage::catalogue() as $importStage) {
        WowImportState::query()->create([
            'entity' => $importStage->value,
            'build' => $importStage === ImportStage::Mounts ? '12.1.0_68000' : '12.1.0_68914',
            'imported_at' => now(),
        ]);
    }

    dashboardBanner()
        ->where('buildStatus.upstreams.blizzard.build', '12.1.0_68914')
        ->where('buildStatus.upstreams.wago.build', '12.1.0.69875')
        ->where('buildStatus.is_up_to_date', false)
        ->where('buildStatus.behind', ['mounts'])
        ->etc();
});

test('the socle figures in the comparison alongside the catalogue entities', function (): void {
    stubBlizzardBuild('12.1.0_68914');
    stubWagoBuild('12.1.0.69875');

    dashboardBanner()
        ->has('buildStatus.entries', count(ImportStage::chain()))
        ->where('buildStatus.entries.0.stage', 'reference')
        ->etc();
});

test('an unreachable Blizzard API still renders the dashboard, saying the check did not complete', function (): void {
    stubBlizzardBuild(null);
    stubWagoBuild('12.1.0.69875');
    referenceLoadedOn('12.1.0.69875');

    dashboardBanner()
        ->where('buildStatus.is_conclusive', false)
        ->where('buildStatus.is_up_to_date', false)
        ->where('buildStatus.upstreams.blizzard.reachable', false)
        ->etc();
});
