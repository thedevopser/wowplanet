<?php

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Reference\ReferenceCatalog;
use App\Infrastructure\Taxonomy\CollectionTaxonomySnapshot;
use App\Models\WowReferenceDownload;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Monolog\Handler\TestHandler;
use Tests\TestCase;

// PostgreSQL is transactional, so each test rolls back instead of re-migrating,
// and the lazy variant spares that cost entirely to tests that never touch the
// database. The budget counter and the import journal left the cache for their
// own Redis indexes, so Cache::flush() no longer resets them: a count leaking
// from one test to the next saturates the quota and sends importers into their
// retry paths, and a journal leaking shifts the cursors of the next test.
pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->beforeEach(function (): void {
        // A request without a double would reach the real service: slow, and failing the
        // day the network does.
        Http::preventStrayRequests();

        clearTestRedis('budget');
        clearTestRedis('imports');
        clearTestRedis('queue');

        // La piste d'audit part dans un journal en mémoire, relu par auditTrail().
        config(['logging.channels.audit' => ['driver' => 'monolog', 'handler' => TestHandler::class, 'level' => 'info']]);

        // L'instantané de la taxonomie est un fichier versionné du dépôt, et tout ce qui
        // écrit en base peut demander son réexport — l'arbitrage du panneau le fait à
        // chaque écriture. Sans cette redirection, une suite de tests réécrit la curation
        // du dépôt avec ses quelques lignes de doublure.
        $this->taxonomySnapshotPath = sys_get_temp_dir().'/pest-taxonomy-'.uniqid().'/'.CollectionTaxonomySnapshot::FILENAME;

        $this->app->bind(
            CollectionTaxonomySnapshot::class,
            fn (): CollectionTaxonomySnapshot => new CollectionTaxonomySnapshot($this->taxonomySnapshotPath),
        );
    })
    ->afterEach(function (): void {
        removeDirectory(dirname((string) $this->taxonomySnapshotPath));
    })
    ->in('Feature');

/**
 * Doublure de l'API Blizzard pour tout ce qui interroge le build servi.
 *
 * Le client reçoit son Guzzle par injection : `Http::fake()` ne l'atteint pas. On pousse
 * donc une instance neuve dans le conteneur — neuve à chaque appel, parce que le client
 * garde en mémoire le build déjà vu et qu'un singleton partagé ferait lire au test
 * suivant la réponse du précédent.
 *
 * Le handler rendu sert d'indicateur : ce qu'il lui reste en file dit combien d'appels
 * sont réellement partis.
 */
function stubBlizzardBuild(?string $build, int $queued = 5): MockHandler
{
    Cache::put('blizzard_access_token', 'test-token', 3600);

    $response = $build === null
        ? new Response(503, [], '{}')
        : new Response(200, ['battlenet-namespace' => 'static-'.$build.'-eu'], '{}');

    $mockHandler = new MockHandler(array_fill(0, $queued, $response));

    app()->instance(BlizzardApiClient::class, new BlizzardApiClient(new Client([
        'handler' => HandlerStack::create($mockHandler),
        'base_uri' => 'https://eu.api.blizzard.com/',
    ])));

    return $mockHandler;
}

function stubWagoBuild(?string $build): void
{
    Http::fake([
        'wago.tools/api/builds' => $build === null
            ? Http::response(status: 503)
            : Http::response(['wow' => [['product' => 'wow', 'version' => $build]]]),
    ]);
}

/**
 * Un socle entièrement chargé sur un build, soit l'état d'une synchronisation aboutie.
 */
function referenceLoadedOn(string $build, ?string $downloadedAt = null): void
{
    foreach ((new ReferenceCatalog)->sources() as $source) {
        WowReferenceDownload::factory()->create([
            'source_table' => $source,
            'build' => $build,
            'downloaded_at' => $downloadedAt ?? now(),
        ]);
    }
}

/**
 * Écrit l'instantané de taxonomie de test, celui que la suite substitue au fichier du dépôt.
 *
 * @param  array<string, array<int, App\Infrastructure\Taxonomy\TaxonomyEntry>>  $entries
 */
function writeTestTaxonomySnapshot(array $entries): void
{
    resolve(CollectionTaxonomySnapshot::class)->write($entries);
}

/**
 * Une entrée curée par collection : ce qu'il faut pour qu'une étape de collection accepte
 * d'importer, sans que le test ait à se soucier du rangement.
 */
function seedCollectionTaxonomy(): void
{
    $entry = new App\Infrastructure\Taxonomy\TaxonomyEntry('Test', 'Test');

    writeTestTaxonomySnapshot(['mount' => [999_001 => $entry], 'pet' => [999_002 => $entry], 'decor' => [999_003 => $entry]]);
}

/**
 * La suite tourne en queue `sync`, qui n'a ni attente ni file : ce que la page de santé
 * mesure n'existe que sur la queue Redis, isolée sur son index de test.
 */
function useRedisQueue(): void
{
    config(['queue.default' => 'redis']);
}

/**
 * La suite tourne sur le store `array`, qui ne sérialise rien : ce qui doit survivre à un
 * aller-retour dans Redis s'éprouve sur le vrai store, isolé sur son index de test et vidé ici.
 */
function useRedisCache(): void
{
    config(['cache.default' => 'redis']);
    clearTestRedis('cache');
}

/**
 * Préfixe des clés Redis du processus de test. Les processus parallèles se partagent les
 * index de test : chacun travaille sous le sien, numéroté par paratest (`TEST_TOKEN`).
 */
function testRedisPrefix(): string
{
    return 'wowplanet-test-'.(getenv('TEST_TOKEN') ?: '0').':';
}

/**
 * Vide les clés du processus sur une connexion, et elles seules : un `FLUSHDB` effacerait
 * celles des processus qui tournent en même temps. Le motif passe en argument d'un script
 * pour échapper au préfixe que phpredis ajouterait aux clés d'une commande ordinaire.
 */
function clearTestRedis(string $connection): void
{
    Redis::connection($connection)->eval(
        "local keys = redis.call('keys', ARGV[1]) for i = 1, #keys, 5000 do redis.call('del', unpack(keys, i, math.min(i + 4999, #keys))) end return #keys",
        0,
        testRedisPrefix().'*',
    );
}

/**
 * Inscrit un job échoué comme le ferait le worker, et rend son uuid.
 */
function recordFailedJob(string $displayName = \App\Jobs\RunImportJob::class, string $message = 'boom'): string
{
    $uuid = (string) Illuminate\Support\Str::uuid();

    resolve('queue.failer')->log('redis', 'imports', (string) json_encode([
        'uuid' => $uuid,
        'displayName' => $displayName,
        'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
        'data' => ['commandName' => $displayName, 'command' => 'O:8:"stdClass":0:{}'],
    ]), new RuntimeException($message));

    return $uuid;
}

/**
 * Ce que la piste d'audit a reçu depuis le début du test.
 *
 * @return list<array{message: string, context: array<string, mixed>}>
 */
function auditTrail(): array
{
    $handler = Log::channel('audit')->getLogger()->getHandlers()[0];
    throw_unless($handler instanceof TestHandler, RuntimeException::class, 'The audit channel is not the in-memory one.');

    return array_map(
        static fn (Monolog\LogRecord $logRecord): array => ['message' => $logRecord->message, 'context' => $logRecord->context],
        $handler->getRecords(),
    );
}

function removeDirectory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }

    rmdir($directory);
}

/**
 * Hashes of the favicons tracked in the repository, to prove a test run leaves them alone.
 *
 * @return array<string, string>
 */
function faviconFingerprints(string $publicPath): array
{
    $fingerprints = [];

    // GLOB_BRACE est absent des PHP liés à musl, dont l'image Alpine du projet.
    foreach (['favicon.ico', '*-*x*.png', 'apple-touch-icon.png'] as $pattern) {
        foreach (glob($publicPath.'/'.$pattern) ?: [] as $file) {
            $fingerprints[basename($file)] = (string) md5_file($file);
        }
    }

    return $fingerprints;
}
