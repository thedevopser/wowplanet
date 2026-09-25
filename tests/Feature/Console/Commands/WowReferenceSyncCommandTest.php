<?php

declare(strict_types=1);

use App\Infrastructure\Reference\ReferenceCatalog;
use App\Infrastructure\Reference\ReferenceTable;
use App\Models\WowReferenceDownload;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

const LIVE_BUILD = '12.1.0.69587';

/**
 * @param  array<string, int>  $rowCounts  Nombre de lignes à servir, par table source
 * @param  array<string, int>  $failures  Statut HTTP à servir avec un corps vide, par table source
 * @param  list<string>  $droppedColumns
 */
function fakeWago(array $rowCounts = [], array $failures = [], array $droppedColumns = [], ?string $build = LIVE_BUILD): void
{
    // Http::fake() empile les doublures et la première qui correspond gagne : sans remise
    // à zéro, un second appel dans le même test resservirait la réponse du premier.
    app()->forgetInstance(Factory::class);
    Http::clearResolvedInstances();

    Http::fake([
        'wago.tools/api/builds' => Http::response($build === null ? [] : ['wow' => [['product' => 'wow', 'version' => $build]]]),
        'wago.tools/db2/*' => function (Request $request) use ($rowCounts, $failures, $droppedColumns) {
            $source = referenceSourceFromUrl($request->url());

            if (isset($failures[$source])) {
                return Http::response('', $failures[$source]);
            }

            return Http::response(fakeDb2Csv($source, $rowCounts[$source] ?? 3, $droppedColumns));
        },
    ]);
}

function referenceSourceFromUrl(string $url): string
{
    preg_match('#/db2/([^/]+)/csv#', $url, $matches);

    return $matches[1];
}

/**
 * @param  list<string>  $droppedColumns
 */
function fakeDb2Csv(string $source, int $rows, array $droppedColumns = []): string
{
    $referenceTable = referenceTableNamed($source);
    $headers = array_values(array_diff($referenceTable->sourceHeaders(), $droppedColumns));

    $lines = [implode(',', $headers)];

    for ($id = 1; $id <= $rows; $id++) {
        $lines[] = implode(',', array_map(
            static fn (string $header): string => $header === 'ID' ? (string) $id : ($header === 'Name_lang' || str_ends_with($header, '_lang') ? 'Libellé '.$id : (string) ($id * 2)),
            $headers,
        ));
    }

    return implode("\n", $lines)."\n";
}

function referenceTableNamed(string $source): ReferenceTable
{
    $table = (new ReferenceCatalog)->find($source);

    throw_unless($table instanceof ReferenceTable, InvalidArgumentException::class, $source);

    return $table;
}

beforeEach(function (): void {
    Storage::fake('reference');
});

test('it loads every reference table from the live build', function (): void {
    fakeWago(['Faction' => 5, 'ContentTuning' => 4]);

    $this->artisan('app:wow-reference-sync')->assertSuccessful();

    expect(DB::table('wow_ref_faction')->count())->toBe(5)
        ->and(DB::table('wow_ref_content_tuning')->count())->toBe(4)
        ->and(DB::table('wow_ref_area_table')->count())->toBe(3)
        ->and(DB::table('wow_ref_quest_v2_cli_task')->count())->toBe(3)
        ->and(DB::table('wow_ref_skill_line_ability')->count())->toBe(3)
        ->and(DB::table('wow_ref_currency_types')->count())->toBe(3);
});

test('it pins the live product so wago never serves a PTR build', function (): void {
    fakeWago();

    $this->artisan('app:wow-reference-sync')->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => ! str_contains($request->url(), '/db2/')
        || str_contains($request->url(), 'product=wow'));
});

test('it asks for the french locale only on the tables that carry labels', function (): void {
    fakeWago();

    $this->artisan('app:wow-reference-sync')->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => ! str_contains($request->url(), '/db2/Faction/')
        || str_contains($request->url(), 'locale=frFR'));
    Http::assertSent(fn (Request $request): bool => ! str_contains($request->url(), '/db2/ContentTuning/')
        || ! str_contains($request->url(), 'locale='));
});

test('it keeps the downloaded file in its own store, named after the build', function (): void {
    fakeWago();

    $this->artisan('app:wow-reference-sync')->assertSuccessful();

    Storage::disk('reference')->assertExists('faction-'.LIVE_BUILD.'.csv');
});

test('it records the build, the size and the volume of every downloaded file', function (): void {
    fakeWago(['Faction' => 5]);

    $this->artisan('app:wow-reference-sync')->assertSuccessful();

    $wowReferenceDownload = WowReferenceDownload::query()->where('source_table', 'Faction')->sole();

    expect($wowReferenceDownload->build)->toBe(LIVE_BUILD)
        ->and($wowReferenceDownload->row_count)->toBe(5)
        ->and($wowReferenceDownload->bytes)->toBeGreaterThan(0)
        ->and($wowReferenceDownload->filename)->toBe('faction-'.LIVE_BUILD.'.csv');
});

test('it can be replayed without changing the outcome', function (): void {
    fakeWago(['Faction' => 5]);

    $this->artisan('app:wow-reference-sync')->assertSuccessful();
    $this->artisan('app:wow-reference-sync')->assertSuccessful();

    expect(DB::table('wow_ref_faction')->count())->toBe(5)
        ->and(WowReferenceDownload::query()->where('source_table', 'Faction')->count())->toBe(1);
});

test('it reports the volume loaded table by table', function (): void {
    fakeWago(['Faction' => 5, 'ContentTuning' => 4]);

    // Sortie relue d'un bloc plutôt qu'assertion ligne à ligne : Mockery apparie chaque
    // attente à la première ligne qui la satisfait, et deux volumétries se chevauchent.
    Artisan::call('app:wow-reference-sync');
    $output = Artisan::output();

    expect($output)->toContain('Faction')->toContain('5')
        ->toContain('ContentTuning')->toContain('4');
});

test('it shows how the volume moved since the previous load', function (): void {
    fakeWago(['Faction' => 5]);
    $this->artisan('app:wow-reference-sync')->assertSuccessful();

    fakeWago(['Faction' => 7]);
    Artisan::call('app:wow-reference-sync');

    expect(Artisan::output())->toContain('+2');
});

test('it leaves the existing socle untouched when a download fails', function (): void {
    fakeWago(['Faction' => 5]);
    $this->artisan('app:wow-reference-sync')->assertSuccessful();

    fakeWago(['Faction' => 9], ['AreaTable' => 503]);
    $this->artisan('app:wow-reference-sync')->assertFailed();

    expect(DB::table('wow_ref_faction')->count())->toBe(5);
});

test('it leaves the existing socle untouched when a source lost a column', function (): void {
    fakeWago(['Faction' => 5]);
    $this->artisan('app:wow-reference-sync')->assertSuccessful();

    fakeWago(['Faction' => 9], [], ['ReputationRaceMasks0_0']);
    $this->artisan('app:wow-reference-sync')->assertFailed();

    expect(DB::table('wow_ref_faction')->count())->toBe(5);
});

test('it names the column a patch removed', function (): void {
    fakeWago([], [], ['ReputationRaceMasks0_0']);

    expect(Artisan::call('app:wow-reference-sync'))->toBe(1)
        ->and(Artisan::output())->toContain('ReputationRaceMasks0_0');
});

test('it refuses a source whose volume collapsed rather than emptying the socle', function (): void {
    fakeWago(['Faction' => 100]);
    $this->artisan('app:wow-reference-sync')->assertSuccessful();

    fakeWago(['Faction' => 3]);
    $this->artisan('app:wow-reference-sync')->assertFailed();

    expect(DB::table('wow_ref_faction')->count())->toBe(100);
});

test('it accepts a volume that merely shrank a little', function (): void {
    fakeWago(['Faction' => 100]);
    $this->artisan('app:wow-reference-sync')->assertSuccessful();

    fakeWago(['Faction' => 95]);
    $this->artisan('app:wow-reference-sync')->assertSuccessful();

    expect(DB::table('wow_ref_faction')->count())->toBe(95);
});

test('it fails without touching anything when the live build is unknown', function (): void {
    fakeWago(build: null);

    $this->artisan('app:wow-reference-sync')->assertFailed();

    expect(DB::table('wow_ref_faction')->count())->toBe(0);
});

test('it can synchronise a single table', function (): void {
    fakeWago(['Faction' => 5]);

    $this->artisan('app:wow-reference-sync', ['--table' => 'Faction'])->assertSuccessful();

    expect(DB::table('wow_ref_faction')->count())->toBe(5)
        ->and(DB::table('wow_ref_content_tuning')->count())->toBe(0);
});

test('it rejects a table name that is not in the catalog', function (): void {
    fakeWago();

    expect(Artisan::call('app:wow-reference-sync', ['--table' => 'Inconnue']))->toBe(1)
        ->and(Artisan::output())->toContain('Inconnue');
});

test('it refuses a file served empty', function (): void {
    fakeWago([], ['AreaTable' => 200]);

    expect(Artisan::call('app:wow-reference-sync'))->toBe(1)
        ->and(Artisan::output())->toContain('AreaTable');
});

test('it leaves the existing socle untouched when a file is served empty', function (): void {
    fakeWago(['Faction' => 5]);
    $this->artisan('app:wow-reference-sync')->assertSuccessful();

    fakeWago(['Faction' => 9], ['AreaTable' => 200]);
    $this->artisan('app:wow-reference-sync')->assertFailed();

    expect(DB::table('wow_ref_faction')->count())->toBe(5);
});
