<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\HourlyBudgetGuard;
use App\Infrastructure\Blizzard\RateLimitingMiddleware;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Illuminate\Support\ServiceProvider;

class BlizzardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            function (): \App\Infrastructure\Blizzard\BlizzardApiClient {
                $handlerStack = HandlerStack::create();
                $handlerStack->push(new RateLimitingMiddleware(new HourlyBudgetGuard), 'rate_limiter');

                /** @var string $region */
                $region = config('services.blizzard.region', 'eu');
                $client = new Client([
                    'base_uri' => sprintf('https://%s.api.blizzard.com/', $region),
                    'handler' => $handlerStack,
                    'timeout' => 15.0,
                ]);

                return new BlizzardApiClient($client);
            },
        );
    }
}
