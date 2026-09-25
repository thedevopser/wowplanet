<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Taxonomy\PendingTaxonomyEntries;
use App\Application\Taxonomy\TaxonomySnapshotExporter;
use App\Application\Taxonomy\TaxonomyVocabulary;
use App\Http\Controllers\Controller;
use App\Infrastructure\Taxonomy\CollectionEntity;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TaxonomyController extends Controller
{
    /**
     * Sept cents mascottes en attente ne se parcourent pas d'un bloc : la page en sert une
     * tranche, et la recherche est le vrai outil de navigation.
     */
    private const int PER_PAGE = 50;

    public function __construct(
        private readonly PendingTaxonomyEntries $pendingTaxonomyEntries,
        private readonly TaxonomyVocabulary $taxonomyVocabulary,
        private readonly TaxonomySnapshotExporter $taxonomySnapshotExporter,
    ) {}

    public function page(Request $request): InertiaResponse
    {
        $entity = CollectionEntity::tryFrom((string) $request->query('entity')) ?? CollectionEntity::Mount;
        $search = trim((string) $request->query('search'));
        $pending = $this->pendingTaxonomyEntries->forEntity($entity, $search);

        return Inertia::render('AdminTaxonomyPage', [
            'entity' => $entity->value,
            'search' => $search,
            'counts' => $this->pendingTaxonomyEntries->counts(),
            'entries' => array_slice($pending, 0, self::PER_PAGE),
            'matched' => count($pending),
            'perPage' => self::PER_PAGE,
            'vocabulary' => $this->taxonomyVocabulary->forEntity($entity),
            'snapshot' => $this->taxonomySnapshotExporter->state(),
        ]);
    }
}
