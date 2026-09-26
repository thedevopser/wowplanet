<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Build\BuildStatus;
use App\Application\Health\FailedJobNotFoundException;
use App\Application\Health\FailedJobNotRetryableException;
use App\Application\Health\FailedJobs;
use App\Application\Import\ImportAlreadyRunningException;
use App\Application\Import\ImportNotRunningException;
use App\Application\Import\ImportSignal;
use App\Application\Import\ImportStage;
use App\Application\Reference\ReferenceFileInventory;
use App\Application\Services\AdminService;
use App\Application\Taxonomy\TaxonomyArbitration;
use App\Application\Taxonomy\TaxonomySnapshotExporter;
use App\Application\Taxonomy\TaxonomySnapshotMerge;
use App\Application\Taxonomy\UnknownCollectionEntryException;
use App\Http\Controllers\Concerns\ResolvesBnetUser;
use App\Infrastructure\Reference\ReferenceCatalog;
use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Jobs\RunImportJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    use ResolvesBnetUser;

    public function __construct(
        private readonly AdminService $adminService,
        private readonly TaxonomyArbitration $taxonomyArbitration,
    ) {}

    public function status(): JsonResponse
    {
        return response()->json([
            'maintenance' => $this->adminService->isInMaintenance(),
        ]);
    }

    /**
     * Lance un import. La requête décrit ce qu'on veut importer et comment, jamais la
     * commande à exécuter : le panneau envoie des entités et un mode, le serveur traduit.
     */
    public function import(Request $request): JsonResponse
    {
        // Le socle est sélectionnable au même titre que les entités de catalogue : le
        // bandeau de détection de patch le lance en tête du même import, parce qu'elles
        // en dépendent et que `ImportStage::requested()` rétablit l'ordre de la chaîne.
        $stages = implode(',', array_column(ImportStage::chain(), 'value'));

        $request->validate([
            'scope' => ['required', 'string', 'in:all,selection'],
            'mode' => ['required', 'string', 'in:incremental,forced'],
            // La sélection ne compte que pour un import partiel : le panneau l'envoie aussi,
            // vide, quand il demande tout, et elle ne doit pas faire refuser cette demande.
            'stages' => ['exclude_unless:scope,selection', 'required', 'array', 'min:1'],
            'stages.*' => ['required', 'string', 'in:'.$stages],
        ]);

        /** @var list<string> $selection */
        $selection = $request->input('stages', []);

        try {
            $jobId = $this->adminService->startImport(
                $request->input('scope') === 'all',
                $selection,
                $request->input('mode') === 'forced',
                $this->getAuthenticatedUserId() ?? RunImportJob::PANEL_TRIGGER,
            );
        } catch (ImportAlreadyRunningException $importAlreadyRunningException) {
            return response()->json([
                'message' => $importAlreadyRunningException->getMessage(),
                'jobId' => $importAlreadyRunningException->jobId,
                'startedAt' => $importAlreadyRunningException->startedAt,
            ], 409);
        }

        return response()->json(['jobId' => $jobId]);
    }

    /**
     * Rouvre les amonts sans attendre l'expiration du cache.
     *
     * La comparaison n'est pas rendue ici : c'est le rechargement de la prop différée du
     * tableau de bord qui la sert, et un seul sérialiseur pour les deux chemins ne peut
     * pas diverger de lui-même.
     */
    public function checkBuilds(BuildStatus $buildStatus): JsonResponse
    {
        $buildStatus->snapshot(force: true);

        return response()->json(['checked' => true]);
    }

    public function importStatus(Request $request, string $jobId): JsonResponse
    {
        $request->validate([
            'cursor' => ['nullable', 'integer', 'min:0'],
        ]);

        $status = $this->adminService->getImportJobStatus($jobId, (int) $request->integer('cursor'));

        return response()->json($status);
    }

    /**
     * L'import en cours, pour qu'un panneau ouvert après coup — ou rafraîchi, ou dans un
     * second onglet — raccroche le suivi sans avoir lancé quoi que ce soit.
     */
    public function currentImport(): JsonResponse
    {
        return response()->json(['jobId' => $this->adminService->currentImportJobId()]);
    }

    public function pauseImport(string $jobId): JsonResponse
    {
        return $this->steer($jobId, function (string $actor) use ($jobId): void {
            $this->adminService->steerImport($jobId, ImportSignal::Pause, $actor);
        });
    }

    public function cancelImport(string $jobId): JsonResponse
    {
        return $this->steer($jobId, function (string $actor) use ($jobId): void {
            $this->adminService->steerImport($jobId, ImportSignal::Cancel, $actor);
        });
    }

    public function resumeImport(string $jobId): JsonResponse
    {
        return $this->steer($jobId, function (string $actor) use ($jobId): void {
            $this->adminService->resumeImport($jobId, $actor);
        });
    }

    /**
     * L'ordre est posé, pas exécuté : la réponse dit qu'il est enregistré, et c'est le
     * suivi qui montrera l'import s'arrêter à sa frontière de tranche suivante.
     *
     * @param  \Closure(string): void  $order
     */
    private function steer(string $jobId, \Closure $order): JsonResponse
    {
        try {
            $order($this->getAuthenticatedUserId() ?? RunImportJob::PANEL_TRIGGER);
        } catch (ImportNotRunningException $importNotRunningException) {
            return response()->json([
                'message' => $importNotRunningException->getMessage(),
                'jobId' => $importNotRunningException->jobId,
            ], 409);
        }

        return response()->json(['jobId' => $jobId]);
    }

    /**
     * Lance une synchronisation du socle. Comme pour les imports, la requête ne nomme
     * jamais une table réelle : elle envoie un nom de source DB2, que la validation
     * confronte au catalogue côté serveur avant qu'il n'atteigne la commande.
     */
    public function syncReference(Request $request, ReferenceCatalog $referenceCatalog): JsonResponse
    {
        $request->validate([
            'scope' => ['required', 'string', 'in:all,table'],
            'table' => ['required_if:scope,table', 'string', 'in:'.implode(',', $referenceCatalog->sources())],
        ]);

        /** @var string|null $source */
        $source = $request->input('scope') === 'all' ? null : $request->input('table');

        try {
            $jobId = $this->adminService->startReferenceSync($source);
        } catch (ImportAlreadyRunningException $importAlreadyRunningException) {
            return response()->json([
                'message' => $importAlreadyRunningException->getMessage(),
                'jobId' => $importAlreadyRunningException->jobId,
                'startedAt' => $importAlreadyRunningException->startedAt,
            ], 409);
        }

        return response()->json(['jobId' => $jobId]);
    }

    /**
     * Purge le magasin de référence, par balayage des obsolètes ou sur une désignation.
     *
     * Aucun chemin ne vient de la requête : le serveur liste d'abord ce que le disque
     * porte réellement, et la validation confronte chaque nom reçu à cette liste. Un nom
     * de fichier orphelin étant arbitraire, la règle passe par un tableau — la forme
     * `in:a,b,c` casserait silencieusement sur un nom contenant une virgule.
     */
    public function purgeReference(Request $request, ReferenceFileInventory $referenceFileInventory): JsonResponse
    {
        $request->validate([
            'scope' => ['required', 'string', 'in:obsolete,selection'],
            'files' => ['required_if:scope,selection', 'array', 'min:1'],
            'files.*' => ['required', 'string', Rule::in($referenceFileInventory->filenames())],
        ]);

        /** @var list<string> $selection */
        $selection = $request->input('files', []);

        $filenames = $request->input('scope') === 'obsolete'
            ? $referenceFileInventory->sweepableFilenames()
            : $selection;

        try {
            $report = $this->adminService->purgeReferenceFiles(
                $filenames,
                $this->getAuthenticatedUserId() ?? RunImportJob::PANEL_TRIGGER,
            );
        } catch (ImportAlreadyRunningException $importAlreadyRunningException) {
            return response()->json([
                'message' => $importAlreadyRunningException->getMessage(),
                'jobId' => $importAlreadyRunningException->jobId,
                'startedAt' => $importAlreadyRunningException->startedAt,
            ], 409);
        }

        return response()->json($report);
    }

    /**
     * Range des entrées de collection, qu'elles soient en attente ou déjà curées : une
     * réaffectation passe par le même chemin. Une entrée absente du catalogue de la
     * collection est refusée par l'arbitrage lui-même, avant toute écriture.
     */
    public function arbitrateTaxonomy(Request $request): JsonResponse
    {
        $entities = implode(',', array_column(CollectionEntity::cases(), 'value'));

        $request->validate([
            'entity' => ['required', 'string', 'in:'.$entities],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*' => ['required', 'integer'],
            'category' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var string $entity */
        $entity = $request->input('entity');
        $collectionEntity = CollectionEntity::from($entity);

        // La règle `integer` accepte aussi une chaîne numérique, qui traverserait la
        // validation sans changer de type : le rétrécissement se fait donc ici.
        /** @var list<int|string> $raw */
        $raw = $request->input('entries', []);
        $entries = array_map(static fn (int|string $entry): int => (int) $entry, $raw);

        /** @var string|null $category */
        $category = $request->input('category');
        /** @var string|null $source */
        $source = $request->input('source');

        try {
            $report = $this->taxonomyArbitration->arbitrate(
                $collectionEntity,
                $entries,
                $category,
                $source,
                $this->getAuthenticatedUserId() ?? RunImportJob::PANEL_TRIGGER,
            );
        } catch (UnknownCollectionEntryException $unknownCollectionEntryException) {
            return response()->json([
                'message' => $unknownCollectionEntryException->getMessage(),
                'entries' => $unknownCollectionEntryException->entryIds,
            ], 422);
        }

        return response()->json($report);
    }

    /**
     * Recharge en base la curation versionnée, sans rien réécrire de ce qui s'y trouve.
     */
    public function loadTaxonomySnapshot(TaxonomySnapshotMerge $taxonomySnapshotMerge): JsonResponse
    {
        return response()->json($taxonomySnapshotMerge->merge($this->getAuthenticatedUserId() ?? RunImportJob::PANEL_TRIGGER));
    }

    /**
     * L'instantané régénéré depuis la base, à verser au dépôt. C'est le chemin par lequel
     * un arbitrage fait en production revient dans le code : le fichier n'y est pas
     * commitable, il se télécharge.
     */
    public function downloadTaxonomySnapshot(TaxonomySnapshotExporter $taxonomySnapshotExporter): JsonResponse|Response
    {
        try {
            $download = $taxonomySnapshotExporter->download();
        } catch (\RuntimeException $runtimeException) {
            return response()->json(['message' => $runtimeException->getMessage()], 422);
        }

        return response($download['contents'], 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$download['filename'].'"',
        ]);
    }

    /**
     * Remet un job échoué dans sa queue. C'est le worker qui le rejouera, jamais cette
     * requête.
     */
    public function retryFailedJob(FailedJobs $failedJobs, string $uuid): JsonResponse
    {
        try {
            $failedJobs->retry($uuid, $this->getAuthenticatedUserId() ?? RunImportJob::PANEL_TRIGGER);
        } catch (ImportAlreadyRunningException $importAlreadyRunningException) {
            return response()->json([
                'message' => $importAlreadyRunningException->getMessage(),
                'jobId' => $importAlreadyRunningException->jobId,
                'startedAt' => $importAlreadyRunningException->startedAt,
            ], 409);
        } catch (FailedJobNotFoundException $failedJobNotFoundException) {
            return response()->json(['message' => $failedJobNotFoundException->getMessage()], 404);
        } catch (FailedJobNotRetryableException $failedJobNotRetryableException) {
            return response()->json(['message' => $failedJobNotRetryableException->getMessage()], 422);
        }

        return response()->json(['uuid' => $uuid]);
    }

    public function forgetFailedJob(FailedJobs $failedJobs, string $uuid): JsonResponse
    {
        try {
            $failedJobs->forget($uuid, $this->getAuthenticatedUserId() ?? RunImportJob::PANEL_TRIGGER);
        } catch (FailedJobNotFoundException $failedJobNotFoundException) {
            return response()->json(['message' => $failedJobNotFoundException->getMessage()], 404);
        }

        return response()->json(['uuid' => $uuid]);
    }

    public function clearCache(): JsonResponse
    {
        $output = $this->adminService->clearCaches();

        return response()->json(['output' => $output]);
    }

    public function maintenance(Request $request): JsonResponse
    {
        $request->validate([
            'enable' => ['required', 'boolean'],
            'secret' => ['nullable', 'string', 'min:8'],
        ]);

        /** @var string|null $secret */
        $secret = $request->input('secret');

        $this->adminService->toggleMaintenance(
            (bool) $request->input('enable'),
            $secret,
        );

        return response()->json([
            'maintenance' => $this->adminService->isInMaintenance(),
        ]);
    }

    public function discord(Request $request): JsonResponse
    {
        $request->validate([
            'channel' => ['required', 'string', 'in:changelog,discussion'],
            'title' => ['required', 'string', 'max:256'],
            'description' => ['required', 'string', 'max:4096'],
            'color' => ['nullable', 'integer'],
            'fields' => ['nullable', 'array', 'max:25'],
            'fields.*.name' => ['required_with:fields', 'string', 'max:256'],
            'fields.*.value' => ['required_with:fields', 'string', 'max:1024'],
            'fields.*.inline' => ['nullable', 'boolean'],
            'footer' => ['nullable', 'string', 'max:2048'],
        ]);

        /** @var string $title */
        $title = $request->input('title');
        /** @var string $description */
        $description = $request->input('description');
        /** @var string $channel */
        $channel = $request->input('channel');

        /** @var array{title: string, description: string, color?: int, fields?: list<array{name: string, value: string, inline?: bool}>} $embed */
        $embed = [
            'title' => $title,
            'description' => $description,
        ];

        if ($request->input('color') !== null) {
            /** @var int $color */
            $color = $request->input('color');
            $embed['color'] = $color;
        }

        /** @var list<array{name: string, value: string, inline?: bool}>|null $fields */
        $fields = $request->input('fields');
        if ($fields !== null) {
            $embed['fields'] = $fields;
        }

        /** @var string|null $footer */
        $footer = $request->input('footer');
        if ($footer !== null && $footer !== '') {
            $embed['footer'] = ['text' => $footer];
        }

        $success = $this->adminService->sendDiscordEmbed($channel, $embed);

        return response()->json(['success' => $success]);
    }
}
