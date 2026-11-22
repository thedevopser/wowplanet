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
        app()->setLocale(AppLocale::fromPath($request->path())->value);

        return $next($request);
    }
}
