<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard;

use GuzzleHttp\Promise\EachPromise;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Attend un lot de requêtes asynchrones et rend le sort de chacune, sans qu'un échec
 * n'interrompe les autres.
 *
 * Ce que Guzzle rend d'une promesse n'est pas typé : la valeur et le motif de rejet
 * sont rétrécis ici, une fois pour toutes.
 *
 * @phpstan-type SettledRequest array{state: 'fulfilled', value: ResponseInterface}|array{state: 'rejected', reason: \Throwable}
 */
class PromiseSettler
{
    /**
     * Sans concurrence donnée, toutes les requêtes partent d'un coup. Les résultats
     * sont rendus dans l'ordre où les requêtes se terminent.
     *
     * @template TKey of array-key
     *
     * @param  iterable<TKey, PromiseInterface>  $promises
     * @param  positive-int|null  $concurrency
     * @return array<TKey, SettledRequest>
     */
    public static function settle(iterable $promises, ?int $concurrency = null): array
    {
        // Filled by reference from the callbacks below, under the keys of $promises.
        /** @var array<TKey, SettledRequest> $settled */
        $settled = [];

        $eachPromise = new EachPromise($promises, [
            ...($concurrency === null ? [] : ['concurrency' => $concurrency]),
            'fulfilled' => function (mixed $value, string|int $key) use (&$settled): void {
                $settled[$key] = $value instanceof ResponseInterface
                    ? ['state' => 'fulfilled', 'value' => $value]
                    : ['state' => 'rejected', 'reason' => new \UnexpectedValueException('The request was fulfilled with something else than a response.')];
            },
            'rejected' => function (mixed $reason, string|int $key) use (&$settled): void {
                $settled[$key] = ['state' => 'rejected', 'reason' => $reason instanceof \Throwable ? $reason : new \RuntimeException('unknown rejection')];
            },
        ]);

        $eachPromise->promise()->wait();

        return $settled;
    }
}
