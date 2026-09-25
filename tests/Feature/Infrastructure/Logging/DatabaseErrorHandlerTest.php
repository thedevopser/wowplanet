<?php

declare(strict_types=1);

use App\Infrastructure\Logging\DatabaseErrorHandler;
use App\Models\ApplicationError;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Facades\Log;
use Monolog\Level;
use Monolog\LogRecord;

function errorHandler(): DatabaseErrorHandler
{
    return new DatabaseErrorHandler(resolve(ConnectionResolverInterface::class));
}

/**
 * @param  array<string, Throwable|string>  $context
 */
function errorRecord(string $message, Level $level = Level::Error, array $context = []): LogRecord
{
    return new LogRecord(new DateTimeImmutable('2026-09-22 10:00:00'), 'testing', $level, $message, $context);
}

test('an error logged anywhere in the application lands in the error journal', function (): void {
    Log::error('Failed to fetch talents', ['exception' => 'timeout']);

    expect(ApplicationError::query()->sole())->toMatchArray([
        'level' => 'ERROR',
        'message' => 'Failed to fetch talents',
    ]);
});

test('a warning stays out of the error journal', function (): void {
    Log::warning('Slow upstream');

    expect(ApplicationError::query()->count())->toBe(0);
});

test('a logged exception keeps its class and where it was thrown', function (): void {
    $exception = new RuntimeException('boom');

    errorHandler()->handle(errorRecord('Import job failed', context: ['exception' => $exception]));

    expect(ApplicationError::query()->sole())->toMatchArray([
        'exception_class' => RuntimeException::class,
        'location' => $exception->getFile().':'.$exception->getLine(),
    ]);
});

test('an entry without an exception has no class nor location', function (): void {
    errorHandler()->handle(errorRecord('Plain failure'));

    expect(ApplicationError::query()->sole())->toMatchArray(['exception_class' => null, 'location' => null]);
});

test('the journal keeps only the most recent entries', function (): void {
    $databaseErrorHandler = errorHandler();

    foreach (range(1, DatabaseErrorHandler::RETAINED_ENTRIES + 3) as $index) {
        $databaseErrorHandler->handle(errorRecord('failure '.$index));
    }

    expect(ApplicationError::query()->count())->toBe(DatabaseErrorHandler::RETAINED_ENTRIES)
        ->and(ApplicationError::query()->orderBy('id')->value('message'))->toBe('failure 4');
});

test('a long message is cut to fit the journal', function (): void {
    errorHandler()->handle(errorRecord(str_repeat('a', DatabaseErrorHandler::MESSAGE_LENGTH + 100)));

    expect(mb_strlen((string) ApplicationError::query()->value('message')))->toBe(DatabaseErrorHandler::MESSAGE_LENGTH);
});

test('an unreachable database never breaks the request that was logging', function (): void {
    $mock = Mockery::mock(ConnectionResolverInterface::class);
    $mock->shouldReceive('connection')->andThrow(new RuntimeException('connection refused'));

    expect(fn (): bool => (new DatabaseErrorHandler($mock))->handle(errorRecord('Failure while the database is down')))
        ->not->toThrow(Throwable::class);
});
