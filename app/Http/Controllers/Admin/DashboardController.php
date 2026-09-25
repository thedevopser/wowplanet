<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Build\BuildStatus;
use App\Application\Taxonomy\PendingTaxonomyEntries;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DashboardController extends Controller
{
    public function __construct(
        private readonly PendingTaxonomyEntries $pendingTaxonomyEntries,
        private readonly BuildStatus $buildStatus,
    ) {}

    public function page(): InertiaResponse
    {
        return Inertia::render('AdminDashboardPage', [
            // Le rangement des collections se dégrade patch après patch sans que rien ne le
            // dise : ce compteur est la seule façon de ne pas l'oublier.
            'pendingTaxonomy' => $this->pendingTaxonomyEntries->counts(),
            // Différée : la comparaison coûte jusqu'à deux requêtes sortantes, et un amont
            // lent doit retarder un bandeau, jamais le panneau tout entier.
            'buildStatus' => Inertia::defer(fn (): array => $this->buildStatus->snapshot()),
        ]);
    }
}
