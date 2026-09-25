<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Sleep;
use Psr\Http\Message\RequestInterface;

class RateLimitingMiddleware
{
    private const MAX_REQUESTS = 80;

    private const BACKOFF_US = 50_000; // 50ms

    /** @var list<float> */
    private array $timestamps = [];

    public function __construct(
        private readonly HourlyBudgetGuard $hourlyBudgetGuard,
    ) {}

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            $this->waitForSlot();

            // Point de passage unique de tous les appels Blizzard (site + imports) :
            // on compte ici pour que le budget horaire reflète l'usage réel total.
            $this->hourlyBudgetGuard->consume(1);

            /** @var PromiseInterface $promise */
            $promise = $handler($request, $options);

            return $promise;
        };
    }

    private function waitForSlot(): void
    {
        while (true) {
            $now = microtime(true);

            // Remove timestamps older than 1 second
            $this->timestamps = array_values(array_filter(
                $this->timestamps,
                static fn (float $ts): bool => ($now - $ts) < 1.0,
            ));

            if (count($this->timestamps) < self::MAX_REQUESTS) {
                $this->timestamps[] = $now;

                return;
            }

            Sleep::usleep(self::BACKOFF_US);
        }
    }
}
