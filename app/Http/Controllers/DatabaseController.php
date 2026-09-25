<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\DatabaseQueryService;
use App\Application\Services\DatabaseSeoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DatabaseController extends Controller
{
    /**
     * Sections dont la sidebar affiche les sous-catégories (accordéon).
     *
     * @var list<string>
     */
    private const SIDEBAR_SECTIONS = ['mounts', 'achievements', 'quests', 'pets', 'decors', 'appearances', 'professions'];

    public function __construct(
        private readonly DatabaseSeoService $databaseSeoService,
        private readonly DatabaseQueryService $databaseQueryService,
    ) {}

    public function index(): InertiaResponse
    {
        return Inertia::render('DatabaseIndexPage', [
            'meta' => $this->databaseSeoService->getIndexMeta(),
            ...$this->sidebarProps(),
        ]);
    }

    public function mounts(?string $category = null): InertiaResponse|RedirectResponse
    {
        $meta = $this->databaseSeoService->getMountsMeta($category);

        if ($meta === null) {
            return redirect('/base-de-donnees/montures', 301);
        }

        return Inertia::render('DatabaseMountsPage', [
            'meta' => $meta,
            'category' => $category,
            ...$this->databaseQueryService->mounts($category),
            ...$this->sidebarProps(),
        ]);
    }

    public function achievements(Request $request, ?string $expansion = null): InertiaResponse|RedirectResponse
    {
        $meta = $this->databaseSeoService->getAchievementsMeta($expansion);

        if ($meta === null) {
            return redirect('/base-de-donnees/hauts-faits', 301);
        }

        return Inertia::render('DatabaseAchievementsPage', [
            'meta' => $meta,
            'expansion' => $expansion,
            'search' => $this->search($request),
            ...$this->databaseQueryService->achievements($expansion, $this->search($request), $this->page($request)),
            ...$this->sidebarProps(),
        ]);
    }

    public function quests(Request $request, ?string $expansion = null): InertiaResponse|RedirectResponse
    {
        $meta = $this->databaseSeoService->getQuestsMeta($expansion);

        if ($meta === null) {
            return redirect('/base-de-donnees/quetes', 301);
        }

        return Inertia::render('DatabaseQuestsPage', [
            'meta' => $meta,
            'expansion' => $expansion,
            'search' => $this->search($request),
            ...$this->databaseQueryService->quests($expansion, $this->search($request), $this->page($request)),
            ...$this->sidebarProps(),
        ]);
    }

    public function pets(?string $category = null): InertiaResponse|RedirectResponse
    {
        $meta = $this->databaseSeoService->getPetsMeta($category);

        if ($meta === null) {
            return redirect('/base-de-donnees/mascottes', 301);
        }

        return Inertia::render('DatabasePetsPage', [
            'meta' => $meta,
            'category' => $category,
            ...$this->databaseQueryService->pets($category),
            ...$this->sidebarProps(),
        ]);
    }

    public function decors(?string $category = null): InertiaResponse|RedirectResponse
    {
        $meta = $this->databaseSeoService->getDecorsMeta($category);

        if ($meta === null) {
            return redirect('/base-de-donnees/decorations', 301);
        }

        return Inertia::render('DatabaseDecorsPage', [
            'meta' => $meta,
            'category' => $category,
            ...$this->databaseQueryService->decors($category),
            ...$this->sidebarProps(),
        ]);
    }

    public function appearances(Request $request, ?string $slot = null): InertiaResponse|RedirectResponse
    {
        $meta = $this->databaseSeoService->getAppearancesMeta($slot);

        if ($meta === null) {
            return redirect('/base-de-donnees/garde-robe', 301);
        }

        return Inertia::render('DatabaseTransmogPage', [
            'meta' => $meta,
            'slot' => $slot,
            'search' => $this->search($request),
            ...$this->databaseQueryService->appearances($slot, null, $this->search($request), $this->page($request)),
            ...$this->sidebarProps(),
        ]);
    }

    public function professions(Request $request, ?string $profession = null): InertiaResponse|RedirectResponse
    {
        $meta = $this->databaseSeoService->getProfessionsMeta($profession);

        if ($meta === null) {
            return redirect('/base-de-donnees/professions', 301);
        }

        return Inertia::render('DatabaseProfessionsPage', [
            'meta' => $meta,
            'profession' => $profession,
            'expansion' => $this->stringQuery($request, 'expansion'),
            'search' => $this->search($request),
            ...$this->databaseQueryService->professions(),
            'recipes' => $profession === null
                ? null
                : $this->databaseQueryService->professionRecipes(
                    $profession,
                    $this->stringQuery($request, 'expansion'),
                    $this->search($request),
                    $this->page($request),
                ),
            ...$this->sidebarProps(),
        ]);
    }

    public function sitemap(): \Illuminate\Http\Response
    {
        $xml = $this->databaseSeoService->generateSitemap();

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    /**
     * Props de la sidebar (counts + sous-catégories) partagées par toutes les pages
     * database. Closures lazy : non évaluées lors des rechargements partiels Inertia
     * (pagination/recherche) qui ne les incluent pas dans `only`.
     *
     * Ces données ne changent qu'à l'import admin → mises en cache (1 h) pour éviter
     * de recalculer ~15 requêtes d'agrégation à chaque navigation dans la base.
     *
     * @return array<string, \Closure>
     */
    private function sidebarProps(): array
    {
        return [
            'counts' => $this->databaseQueryService->cachedCounts(...),
            'subCategories' => fn (): array => Cache::remember(
                'database_sidebar_subcategories',
                3600,
                function (): array {
                    $map = [];
                    foreach (self::SIDEBAR_SECTIONS as $section) {
                        $map[$section] = $this->databaseQueryService->subcategories($section) ?? [];
                    }

                    return $map;
                },
            ),
        ];
    }

    private function search(Request $request): ?string
    {
        return $this->stringQuery($request, 'search');
    }

    private function page(Request $request): ?int
    {
        /** @var string|null $value */
        $value = $request->query('page');

        return ($value === null || $value === '') ? null : (int) $value;
    }

    private function stringQuery(Request $request, string $key): ?string
    {
        /** @var string|null $value */
        $value = $request->query($key);

        return ($value === null || $value === '') ? null : $value;
    }
}
