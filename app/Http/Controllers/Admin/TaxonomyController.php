<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Taxonomy\CuratedTaxonomyEntries;
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

    private const string MODE_PENDING = 'pending';

    private const string MODE_CURATED = 'curated';

    public function __construct(
        private readonly PendingTaxonomyEntries $pendingTaxonomyEntries,
        private readonly CuratedTaxonomyEntries $curatedTaxonomyEntries,
        private readonly TaxonomyVocabulary $taxonomyVocabulary,
        private readonly TaxonomySnapshotExporter $taxonomySnapshotExporter,
    ) {}

    /**
     * Deux modes sur un même écran : les entrées à arbitrer, par défaut, et les entrées
     * déjà rangées, où l'on retrouve une entrée mal rangée pour la réaffecter.
     */
    public function page(Request $request): InertiaResponse
    {
        $entity = CollectionEntity::tryFrom((string) $request->query('entity')) ?? CollectionEntity::Mount;
        $search = trim((string) $request->query('search'));
        $mode = $request->query('mode') === self::MODE_CURATED ? self::MODE_CURATED : self::MODE_PENDING;

        $shared = [
            'entity' => $entity->value,
            'search' => $search,
            'mode' => $mode,
            'counts' => $this->pendingTaxonomyEntries->counts(),
            'curatedCounts' => $this->curatedTaxonomyEntries->counts(),
            'perPage' => self::PER_PAGE,
            'vocabulary' => $this->taxonomyVocabulary->forEntity($entity),
            'snapshot' => $this->taxonomySnapshotExporter->state(),
        ];

        if ($mode === self::MODE_PENDING) {
            $pending = $this->pendingTaxonomyEntries->forEntity($entity, $search);

            return Inertia::render('AdminTaxonomyPage', [
                ...$shared,
                'entries' => array_slice($pending, 0, self::PER_PAGE),
                'matched' => count($pending),
            ]);
        }

        $category = $this->categoryFilter($request);
        $curated = $this->curatedTaxonomyEntries->forEntity($entity, $search, $category);

        return Inertia::render('AdminTaxonomyPage', [
            ...$shared,
            'entries' => array_slice($curated, 0, self::PER_PAGE),
            'matched' => count($curated),
            'category' => $category,
            'categories' => $this->curatedTaxonomyEntries->categories($entity),
        ]);
    }

    private function categoryFilter(Request $request): ?string
    {
        $raw = $request->query('category');
        $category = is_string($raw) ? trim($raw) : '';

        return $category === '' ? null : $category;
    }
}
