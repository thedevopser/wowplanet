<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Localization\AppLocale;
use App\Http\Localization\LocalizedRouteTable;
use App\Http\Theme\ThemeChoice;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(private readonly LocalizedRouteTable $localizedRouteTable) {}

    /**
     * Le template Blade racine chargé au premier rendu (contient @inertia / @inertiaHead).
     */
    #[\Override]
    protected $rootView = 'app';

    /**
     * Props partagées avec toutes les pages Inertia.
     *
     * Lit directement la session (clés canoniques de UserCharacterService) pour
     * exposer l'état d'auth au premier rendu, sans dépendre du service (couplé à
     * l'API Blizzard) sur chaque requête.
     *
     * Le type de retour est celui du contrat d'Inertia, dont les props partagées par
     * défaut ne sont pas typées.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'isAuthenticated' => fn (): bool => $request->session()->has('blizzard_user_token'),
                'isAdmin' => fn (): bool => (bool) $request->session()->get('is_admin', false),
                'battletag' => function () use ($request): string {
                    $battletag = $request->session()->get('bnet_battletag');

                    return is_string($battletag) ? $battletag : '';
                },
            ],
            'theme' => ThemeChoice::fromRequest($request)->value,
            'locale' => fn (): string => app()->getLocale(),
            'alternateUrls' => fn (): ?array => $this->alternateUrls($request),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'authRequired' => fn (): bool => $request->session()->get('auth_required') === true,
            ],
        ];
    }

    /**
     * The current page in each locale, query string included, or null for a page that
     * exists in one language only.
     *
     * @return array{fr: string, en: string}|null
     */
    private function alternateUrls(Request $request): ?array
    {
        $query = $request->getQueryString();
        $urls = [];

        foreach (AppLocale::cases() as $locale) {
            $path = $this->localizedRouteTable->alternate('/'.$request->path(), $locale);

            if ($path === null) {
                return null;
            }

            $urls[$locale->value] = url($path).($query === null ? '' : '?'.$query);
        }

        return ['fr' => $urls['fr'], 'en' => $urls['en']];
    }
}
