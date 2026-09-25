<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\UserCharacterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class AccountController extends Controller
{
    public const string CHARACTERS_VIEW = 'personnages';

    /**
     * Segment d'URL de chaque vue du hub ; la vue des personnages est l'adresse de base.
     *
     * @var array<string, string>
     */
    private const array VIEWS_BY_SEGMENT = [
        'score' => 'score',
        'classes' => 'classes',
    ];

    public function __construct(
        private readonly UserCharacterService $userCharacterService,
    ) {}

    /**
     * Hub « Mon compte » : personnages, score du compte et classes, sur une seule page.
     * Sans connexion, retient la page demandée, que le callback OAuth rouvrira, et
     * redirige vers l'accueil avec un flash que le front affiche en toast.
     */
    public function hub(Request $request, ?string $view = null): InertiaResponse|RedirectResponse|Response
    {
        if ($view !== null && ! array_key_exists($view, self::VIEWS_BY_SEGMENT)) {
            return Inertia::render('NotFoundPage')->toResponse($request)->setStatusCode(404);
        }

        if (! $this->userCharacterService->isAuthenticated()) {
            $request->session()->put(AuthController::INTENDED_URL_SESSION_KEY, $request->getRequestUri());

            return redirect('/')->with('auth_required', true);
        }

        return Inertia::render('AccountPage', [
            'view' => $view === null ? self::CHARACTERS_VIEW : self::VIEWS_BY_SEGMENT[$view],
        ]);
    }
}
