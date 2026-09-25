<?php

declare(strict_types=1);

use App\Application\Health\ApplicationErrorLog;
use App\Models\ApplicationError;

test('it returns the most recent errors first, with their timestamp', function (): void {
    ApplicationError::factory()->create(['message' => 'older', 'occurred_at' => '2026-09-22 08:00:00']);
    ApplicationError::factory()->create([
        'message' => 'newer',
        'exception_class' => RuntimeException::class,
        'location' => 'app/Jobs/RunImportJob.php:42',
        'occurred_at' => '2026-09-22 09:00:00',
    ]);

    $errors = resolve(ApplicationErrorLog::class)->latest(10);

    expect(array_column($errors, 'message'))->toBe(['newer', 'older'])
        ->and($errors[0])->toMatchArray([
            'level' => 'ERROR',
            'exception_class' => RuntimeException::class,
            'location' => 'app/Jobs/RunImportJob.php:42',
        ])
        ->and($errors[0]['occurred_at'])->toStartWith('2026-09-22T09:00:00');
});

test('it returns no more than asked', function (): void {
    ApplicationError::factory()->count(3)->create();

    expect(resolve(ApplicationErrorLog::class)->latest(2))->toHaveCount(2);
});

test('a limit below one is refused', function (): void {
    resolve(ApplicationErrorLog::class)->latest(0);
})->throws(InvalidArgumentException::class);
