<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Concerns\FetchesProfileEndpoints;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectTimeoutException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ResponseTimeoutException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Sleep;
use Mockery\MockInterface;

beforeEach(function (): void {
    Sleep::fake();
});

function makeProfileConsumer(BlizzardApiClient $client): object
{
    return new readonly class($client)
    {
        use FetchesProfileEndpoints;

        public function __construct(private BlizzardApiClient $blizzardApiClient) {}

        /**
         * @param  array<string, array{endpoint: string, query: array<string, string>}>  $endpoints
         * @return array<string, ResponsePayload>
         */
        public function fetch(array $endpoints): array
        {
            return $this->fetchPayloadsAsync($endpoints);
        }
    };
}

/**
 * Answers the endpoint with each given outcome in turn, then keeps repeating the last one.
 *
 * @param  list<Throwable|Response>  $outcomes
 */
function profileClientAnswering(array $outcomes, int &$calls): BlizzardApiClient
{
    /** @var BlizzardApiClient&MockInterface $mock */
    $mock = Mockery::mock(BlizzardApiClient::class);
    $mock->shouldReceive('getAsync')->andReturnUsing(function () use ($outcomes, &$calls): PromiseInterface {
        $outcome = $outcomes[min($calls, count($outcomes) - 1)];
        $calls++;

        return $outcome instanceof Throwable ? Create::rejectionFor($outcome) : Create::promiseFor($outcome);
    });

    return $mock;
}

/**
 * @return array<string, array{endpoint: string, query: array<string, string>}>
 */
function mountsEndpoint(): array
{
    return ['mounts' => ['endpoint' => 'profile/wow/character/hyjal/thrall/collections/mounts', 'query' => []]];
}

function mountsResponse(): Response
{
    return new Response(200, [], (string) json_encode(['mounts' => [['id' => 6]]]));
}

test('a transient failure is retried until the endpoint answers', function (Throwable $throwable): void {
    $calls = 0;
    $blizzardApiClient = profileClientAnswering([$throwable, mountsResponse()], $calls);

    $payloads = makeProfileConsumer($blizzardApiClient)->fetch(mountsEndpoint());

    expect($payloads['mounts']->objectList('mounts'))->toHaveCount(1)
        ->and($calls)->toBe(2);
})->with([
    'connection timeout' => [fn (): Throwable => new ConnectTimeoutException('cURL error 28: Connection timed out after 15001 milliseconds', new Request('GET', 'mounts'))],
    'timeout once the response has started' => [fn (): Throwable => new ResponseTimeoutException('cURL error 28: Operation timed out after 15000 milliseconds', new Request('GET', 'mounts'), new Response(200))],
    'request failure without a response' => [fn (): Throwable => new RequestException('cURL error 56: Recv failure', new Request('GET', 'mounts'))],
    'server error' => [fn (): Throwable => new ServerException('Server error: `GET mounts` resulted in a `500 Internal Server Error` response', new Request('GET', 'mounts'), new Response(500))],
    'gateway timeout' => [fn (): Throwable => new ServerException('Server error: `GET mounts` resulted in a `504 Gateway Timeout` response', new Request('GET', 'mounts'), new Response(504))],
    'rate limit' => [fn (): Throwable => new ClientException('Client error: `GET mounts` resulted in a `429 Too Many Requests` response', new Request('GET', 'mounts'), new Response(429))],
]);

test('an endpoint that never recovers is given up as empty after two retries', function (): void {
    $calls = 0;
    $blizzardApiClient = profileClientAnswering([new ServerException('Server error: `GET mounts` resulted in a `500 Internal Server Error` response', new Request('GET', 'mounts'), new Response(500))], $calls);

    $payloads = makeProfileConsumer($blizzardApiClient)->fetch(mountsEndpoint());

    expect($payloads['mounts']->objectList('mounts'))->toBe([])
        ->and($calls)->toBe(3);
});

test('a missing resource is an empty answer and is not asked for again', function (): void {
    $calls = 0;
    $blizzardApiClient = profileClientAnswering([new ClientException('Client error: `GET mounts` resulted in a `404 Not Found` response', new Request('GET', 'mounts'), new Response(404))], $calls);

    $payloads = makeProfileConsumer($blizzardApiClient)->fetch(mountsEndpoint());

    expect($payloads['mounts']->objectList('mounts'))->toBe([])
        ->and($calls)->toBe(1);
});

test('a definitive refusal is an empty answer and is not asked for again', function (): void {
    $calls = 0;
    $blizzardApiClient = profileClientAnswering([new ClientException('Client error: `GET mounts` resulted in a `403 Forbidden` response', new Request('GET', 'mounts'), new Response(403))], $calls);

    $payloads = makeProfileConsumer($blizzardApiClient)->fetch(mountsEndpoint());

    expect($payloads['mounts']->objectList('mounts'))->toBe([])
        ->and($calls)->toBe(1);
});
