<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\Profile\MythicKeystoneProfileResponse;
use App\Infrastructure\Blizzard\Responses\Profile\MythicRun;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * @param  array<string, mixed>  $decoded
 */
function mythicKeystoneProfile(array $decoded): MythicKeystoneProfileResponse
{
    return MythicKeystoneProfileResponse::fromPayload(
        ResponsePayload::forEndpoint('profile/wow/character/tarren-mill/slysorrows/mythic-keystone-profile', $decoded),
    );
}

test('it reads the keys of the current week, one per dungeon', function (): void {
    $mythicKeystoneProfileResponse = mythicKeystoneProfile([
        'current_period' => [
            'period' => ['id' => 1089],
            'best_runs' => [
                ['dungeon' => ['id' => 585, 'name' => 'Arène de la Cicatrice du Vide'], 'keystone_level' => 21, 'completed_timestamp' => 1_794_520_911_000, 'is_completed_within_time' => false],
                ['dungeon' => ['id' => 249, 'name' => 'Repaire de la Nexus-reine'], 'keystone_level' => 12, 'completed_timestamp' => 1_794_400_000_000, 'is_completed_within_time' => true],
            ],
        ],
        'current_mythic_rating' => ['rating' => 3100.5],
    ]);

    expect($mythicKeystoneProfileResponse->periodId)->toBe(1089)
        ->and(array_map(fn (MythicRun $run): array => [$run->dungeonId, $run->keystoneLevel], $mythicKeystoneProfileResponse->currentPeriodRuns))
        ->toBe([[585, 21], [249, 12]]);
});

test('a week without key carries no run', function (): void {
    $mythicKeystoneProfileResponse = mythicKeystoneProfile(['current_period' => ['period' => ['id' => 1089]]]);

    expect($mythicKeystoneProfileResponse->periodId)->toBe(1089)
        ->and($mythicKeystoneProfileResponse->currentPeriodRuns)->toBe([]);
});

test('a character who never played Mythic+ carries neither period nor run', function (): void {
    $mythicKeystoneProfileResponse = mythicKeystoneProfile([]);

    expect($mythicKeystoneProfileResponse->periodId)->toBeNull()
        ->and($mythicKeystoneProfileResponse->currentPeriodRuns)->toBe([]);
});
