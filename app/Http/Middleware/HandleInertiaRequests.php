<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Theme\ThemeChoice;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Le template Blade racine chargé au premier rendu (contient @inertia / @inertiaHead).
     */
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
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'authRequired' => fn (): bool => $request->session()->get('auth_required') === true,
            ],
        ];
    }
}
