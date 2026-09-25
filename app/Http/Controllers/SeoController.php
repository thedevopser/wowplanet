<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\CharacterSeoService;
use App\Application\Services\DatabaseQueryService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SeoController extends Controller
{
    public function __construct(
        private readonly CharacterSeoService $characterSeoService,
        private readonly DatabaseQueryService $databaseQueryService,
    ) {}

    public function home(): InertiaResponse
    {
        return Inertia::render('HomePage', [
            'meta' => $this->characterSeoService->getHomeMeta(),
            'counts' => $this->databaseQueryService->cachedCounts(),
        ]);
    }

    /**
     * Page 404 Inertia (catch-all des URLs inconnues). Renvoie le statut HTTP 404.
     */
    public function notFound(Request $request): SymfonyResponse
    {
        return Inertia::render('NotFoundPage')
            ->toResponse($request)
            ->setStatusCode(404);
    }

    public function faqPage(): InertiaResponse
    {
        return Inertia::render('FaqPage', [
            'meta' => $this->characterSeoService->getStaticPageMeta('faq'),
        ]);
    }

    public function cguPage(): InertiaResponse
    {
        return Inertia::render('CguPage', [
            'meta' => $this->characterSeoService->getStaticPageMeta('cgu'),
        ]);
    }

    public function privacyPage(): InertiaResponse
    {
        return Inertia::render('PrivacyPage', [
            'meta' => $this->characterSeoService->getStaticPageMeta('privacy'),
        ]);
    }

    public function addonsPage(): InertiaResponse
    {
        return Inertia::render('AddonsPage', [
            'meta' => $this->characterSeoService->getStaticPageMeta('addons'),
        ]);
    }

    public function sitemap(): Response
    {
        $xml = $this->characterSeoService->generateSitemapIndex();

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    public function sitemapPages(): Response
    {
        $xml = $this->characterSeoService->generatePagesSitemap();

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    public function robots(): Response
    {
        /** @var string $configUrl */
        $configUrl = config('app.url', '');
        $appUrl = rtrim($configUrl, '/');

        $content = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Allow: /base-de-donnees/',
            'Allow: /character/',
            'Allow: /classements-pvp/',
            'Disallow: /api/',
            'Disallow: /auth/',
            'Disallow: /admin',
            'Disallow: /mon-compte',
            'Disallow: /my-characters',
            'Disallow: /my-score',
            'Disallow: /class-stats',
            '',
            sprintf('Sitemap: %s/sitemap.xml', $appUrl),
            '',
        ]);

        return response($content, 200, [
            'Content-Type' => 'text/plain',
        ]);
    }
}
