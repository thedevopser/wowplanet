<?php

declare(strict_types=1);

use App\Domain\Services\WeeklyReset;

function parisTime(string $moment): DateTimeImmutable
{
    return new DateTimeImmutable($moment, new DateTimeZone('Europe/Paris'));
}

function milliseconds(DateTimeImmutable $moment): int
{
    return $moment->getTimestamp() * 1000;
}

test('in winter, the last reset is the latest Wednesday at 5 am in Paris', function (string $now, string $expected): void {
    expect((new WeeklyReset)->lastResetAt(parisTime($now)))->toEqual(parisTime($expected));
})->with([
    'on a Saturday' => ['2026-11-14 20:00', '2026-11-11 05:00'],
    'on the Wednesday before 5 am' => ['2026-11-11 04:59', '2026-11-04 05:00'],
    'on the Wednesday at 5 am' => ['2026-11-11 05:00', '2026-11-11 05:00'],
    'on the Tuesday evening' => ['2026-11-10 23:30', '2026-11-04 05:00'],
]);

test('the reset follows Blizzard at 4 am UTC all year, 5 am in Paris in winter and 6 am in summer', function (string $now, string $expected, string $parisHour): void {
    $lastReset = (new WeeklyReset)->lastResetAt(parisTime($now));

    expect($lastReset)->toEqual(parisTime($expected))
        ->and($lastReset->setTimezone(new DateTimeZone('Europe/Paris'))->format('H:i'))->toBe($parisHour)
        ->and($lastReset->setTimezone(new DateTimeZone('UTC'))->format('D H:i'))->toBe('Wed 04:00');
})->with([
    'in summer, as period 1069 of June 24, 2026' => ['2026-06-26 12:00', '2026-06-24 06:00', '06:00'],
    'in summer, on the Wednesday at 5:30 in Paris' => ['2026-06-24 05:30', '2026-06-17 06:00', '06:00'],
    'the week after winter time starts' => ['2026-10-29 12:00', '2026-10-28 05:00', '05:00'],
    'the week winter time starts' => ['2026-10-26 12:00', '2026-10-21 06:00', '06:00'],
    'the week after summer time starts' => ['2026-04-01 12:00', '2026-04-01 06:00', '06:00'],
]);

test('it reads the reset whatever the zone of the given moment', function (): void {
    $now = new DateTimeImmutable('2026-11-11 04:30', new DateTimeZone('UTC'));

    expect((new WeeklyReset)->lastResetAt($now))->toEqual(parisTime('2026-11-11 05:00'));
});

test('the next reset is one week after the last one', function (): void {
    expect((new WeeklyReset)->nextResetAt(parisTime('2026-11-14 20:00')))->toEqual(parisTime('2026-11-18 05:00'));
});

test('a kill counts for the week once it comes after the last reset', function (string $killedAt, bool $thisWeek): void {
    $now = parisTime('2026-11-11 12:00');

    expect((new WeeklyReset)->isThisWeek(milliseconds(parisTime($killedAt)), $now))->toBe($thisWeek);
})->with([
    'at 4:59 on the Wednesday' => ['2026-11-11 04:59', false],
    'at 5:01 on the Wednesday' => ['2026-11-11 05:01', true],
    'at 5:00 sharp' => ['2026-11-11 05:00', true],
    'the week before' => ['2026-11-08 21:00', false],
]);

test('a kill never recorded does not count', function (): void {
    expect((new WeeklyReset)->isThisWeek(0, parisTime('2026-11-11 12:00')))->toBeFalse();
});
