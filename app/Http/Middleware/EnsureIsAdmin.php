<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureIsAdmin
{
    public const string REFUSAL_MESSAGE = 'Accès réservé à l’administration.';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('is_admin', false)) {
            return $next($request);
        }

        // Une visite Inertia annonce `Accept: text/html` : elle repart en
        // redirection, quand un appel XHR garde son refus en JSON.
        return $request->expectsJson()
            ? response()->json(['error' => 'Forbidden'], 403)
            : redirect('/')->with('error', self::REFUSAL_MESSAGE);
    }
}
