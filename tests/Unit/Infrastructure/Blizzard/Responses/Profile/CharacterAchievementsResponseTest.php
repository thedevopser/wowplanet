<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\Profile\CharacterAchievementsResponse;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * @param  array<string, mixed>  $decoded
 */
function characterAchievements(array $decoded): CharacterAchievementsResponse
{
    return CharacterAchievementsResponse::fromPayload(
        ResponsePayload::forEndpoint('profile/wow/character/hyjal/thrall/achievements', $decoded),
    );
}

test('only achievements with a completion timestamp count as completed', function (): void {
    $characterAchievementsResponse = characterAchievements([
        'total_points' => 12345,
        'achievements' => [
            ['id' => 6, 'completed_timestamp' => 1_600_000_000_000],
            ['id' => 7],
            ['id' => 8, 'completed_timestamp' => 1_600_000_100_000],
        ],
    ]);

    expect($characterAchievementsResponse->completedAchievementIds)->toBe([6, 8])
        ->and($characterAchievementsResponse->totalPoints)->toBe(12345);
});

test('a completed entry without id is skipped', function (): void {
    expect(characterAchievements(['achievements' => [['completed_timestamp' => 1]]])->completedAchievementIds)->toBe([]);
});

test('a character without achievements has no points', function (): void {
    $characterAchievementsResponse = characterAchievements([]);

    expect($characterAchievementsResponse->completedAchievementIds)->toBe([])
        ->and($characterAchievementsResponse->totalPoints)->toBeNull();
});
