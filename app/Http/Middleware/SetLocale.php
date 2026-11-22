<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Localization\AppLocale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->is('api/*')
            ? AppLocale::fromHeader($request->header(AppLocale::HEADER))
            : AppLocale::fromPath($request->path());

        app()->setLocale($locale->value);

        return $next($request);
    }
}
