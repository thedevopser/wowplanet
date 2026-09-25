<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Import\ImportWaitReporter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Le suivi d'un import interroge le serveur chaque seconde : une minute de suivi en
     * coûte soixante. Le plafond en laisse autant pour un second onglet et autant pour
     * naviguer, sans quoi suivre un import fermerait le panneau au bout de trente secondes.
     */
    private const int ADMIN_REQUESTS_PER_MINUTE = 180;

    public function register(): void
    {
        // Le rapporteur d'attente porte le job suivi : sept importers et le client API
        // doivent voir le même, sans quoi chacun publierait dans le vide.
        $this->app->singleton(ImportWaitReporter::class);
    }

    public function boot(): void
    {
        /** @var string $appUrl */
        $appUrl = config('app.url', '');
        if (str_contains($appUrl, 'https://')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(60)->by((string) $request->ip()));

        RateLimiter::for('authenticated', fn (Request $request): Limit => Limit::perMinute(30)->by($request->session()->getId()));

        RateLimiter::for('admin', fn (Request $request): Limit => Limit::perMinute(self::ADMIN_REQUESTS_PER_MINUTE)->by($request->session()->getId()));
    }
}
