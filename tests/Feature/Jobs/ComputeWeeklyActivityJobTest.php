<?php

declare(strict_types=1);

use App\Application\Services\WeeklyActivityService;
use App\Jobs\ComputeWeeklyActivityJob;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;

/**
 * @return list<array{name: string, realm: string, realmSlug: string, level: int, classId: int}>
 */
function weeklyJobCharacters(): array
{
    return [['name' => 'Gothdroid', 'realm' => 'Dalaran', 'realmSlug' => 'dalaran', 'level' => 90, 'classId' => 2]];
}

function weeklyJob(): ComputeWeeklyActivityJob
{
    return new ComputeWeeklyActivityJob('job-1', '42', weeklyJobCharacters(), 'Gothdroid#2855');
}

test('it runs on the imports queue, under the account it computes', function (): void {
    expect(weeklyJob()->queue)->toBe('imports')
        ->and(weeklyJob()->tries)->toBe(1)
        ->and(weeklyJob()->label())->toBe('Semaine de jeu du compte')
        ->and(weeklyJob()->account())->toBe('Gothdroid#2855');
});

test('it computes the week of the account, marked running then completed', function (): void {
    $this->travelTo(new DateTimeImmutable('2026-11-14 20:00:00', new DateTimeZone('UTC')));
    $service = $this->mock(WeeklyActivityService::class);
    $service->expects('markJob')->with('job-1', 'running')->ordered();
    $service->expects('computeAndStore')->withArgs(fn (string $bnetUserId, array $characters, DateTimeImmutable $now): bool => $bnetUserId === '42'
        && $characters === weeklyJobCharacters()
        && $now->getTimestamp() === now()->getTimestamp())->ordered();
    $service->expects('markJob')->with('job-1', 'completed')->ordered();

    weeklyJob()->handle($service);
});

test('a failure marks the job failed, for the page to say so', function (): void {
    $this->mock(WeeklyActivityService::class)->expects('markJob')->with('job-1', 'failed');

    weeklyJob()->failed(new RuntimeException('Blizzard down'));
});

test('a failure is logged as an error naming the job and its cause', function (): void {
    $this->mock(WeeklyActivityService::class)->allows('markJob');
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $messageLogged) use (&$logged): void {
        $logged[] = [$messageLogged->level, $messageLogged->message, $messageLogged->context];
    });

    weeklyJob()->failed(new RuntimeException('Blizzard down'));

    expect($logged)->toContain(['error', 'Weekly activity job failed', ['jobId' => 'job-1', 'error' => 'Blizzard down']]);
});

test('it computes the week in the language of the page that queued it', function (): void {
    $service = $this->mock(WeeklyActivityService::class);
    $service->allows('markJob');
    $service->expects('computeAndStore')->andReturnUsing(function (): void {
        expect(app()->getLocale())->toBe('en');
    });

    new ComputeWeeklyActivityJob('job-1', '42', weeklyJobCharacters(), 'Gothdroid#2855', 'en')->handle($service);
});
