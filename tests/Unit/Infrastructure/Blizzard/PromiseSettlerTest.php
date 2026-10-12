<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\PromiseSettler;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

test('a fulfilled request is settled with its response under its own key', function (): void {
    $response = new Response(200);

    $settled = PromiseSettler::settle(['mounts' => Create::promiseFor($response)]);

    expect($settled)->toBe(['mounts' => ['state' => 'fulfilled', 'value' => $response]]);
});

test('a rejected request is settled with its exception without stopping the others', function (): void {
    $notFound = new ClientException('Not Found', new Request('GET', 'pets/2'), new Response(404));
    $response = new Response(200);

    $settled = PromiseSettler::settle([
        2 => Create::rejectionFor($notFound),
        3 => Create::promiseFor($response),
    ]);

    expect($settled)->toBe([
        2 => ['state' => 'rejected', 'reason' => $notFound],
        3 => ['state' => 'fulfilled', 'value' => $response],
    ]);
});

test('a rejection that carries no exception is settled with one', function (): void {
    $settled = PromiseSettler::settle(['quests' => Create::rejectionFor('connection reset')]);

    expect($settled['quests']['state'])->toBe('rejected')
        ->and($settled['quests']['reason'])->toBeInstanceOf(RuntimeException::class)
        ->and($settled['quests']['reason']->getMessage())->toBe('unknown rejection');
});

test('a fulfilment that is not a response is settled as a rejection', function (): void {
    $settled = PromiseSettler::settle(['quests' => Create::promiseFor('not a response')]);

    expect($settled['quests']['state'])->toBe('rejected')
        ->and($settled['quests']['reason'])->toBeInstanceOf(UnexpectedValueException::class);
});

/**
 * Requests that only complete once waited for, and record how many of them had been
 * started by the time the first one was.
 *
 * @param  list<int>  $ids
 */
function lazyRequests(array $ids, int &$startedAtFirstWait): Generator
{
    $started = 0;

    foreach ($ids as $id) {
        $started++;

        $promise = new Promise(function () use (&$promise, &$started, &$startedAtFirstWait): void {
            $startedAtFirstWait = $startedAtFirstWait === 0 ? $started : $startedAtFirstWait;
            $promise->resolve(new Response(200));
        });

        yield $id => $promise;
    }
}

test('requests are held back until the concurrency leaves them room', function (): void {
    $startedAtFirstWait = 0;

    $settled = PromiseSettler::settle(lazyRequests([1, 2, 3, 4, 5, 6, 7, 8], $startedAtFirstWait), 2);

    expect(array_keys($settled))->toEqualCanonicalizing([1, 2, 3, 4, 5, 6, 7, 8])
        ->and($startedAtFirstWait)->toBeLessThan(8);
});

test('every request is started at once when no concurrency is given', function (): void {
    $startedAtFirstWait = 0;

    $settled = PromiseSettler::settle(lazyRequests([1, 2, 3, 4, 5, 6, 7, 8], $startedAtFirstWait));

    expect($settled)->toHaveCount(8)
        ->and($startedAtFirstWait)->toBe(8);
});
