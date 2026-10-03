<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\MythicLeaderboardIndexResponse;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * @param  array<string, mixed>  $decoded
 */
function mythicLeaderboardIndex(array $decoded): MythicLeaderboardIndexResponse
{
    return MythicLeaderboardIndexResponse::fromPayload(
        ResponsePayload::forEndpoint('data/wow/connected-realm/1080/mythic-leaderboard/index', $decoded),
    );
}

test('the current leaderboards give the dungeons of the season, in order', function (): void {
    $dungeons = mythicLeaderboardIndex([
        'current_leaderboards' => [
            ['id' => 249, 'name' => 'Repos des rois'],
            ['id' => 584, 'name' => 'Le val Aveuglant'],
        ],
    ])->dungeons;

    expect($dungeons)->toHaveCount(2)
        ->and($dungeons[0]->id)->toBe(249)
        ->and($dungeons[0]->name)->toBe('Repos des rois')
        ->and($dungeons[1]->id)->toBe(584)
        ->and($dungeons[1]->name)->toBe('Le val Aveuglant');
});

test('an index without leaderboards gives no dungeon', function (): void {
    expect(mythicLeaderboardIndex([])->dungeons)->toBe([]);
});

test('a leaderboard without an identifier or a name is left out', function (): void {
    $dungeons = mythicLeaderboardIndex([
        'current_leaderboards' => [
            ['name' => 'Sans identifiant'],
            ['id' => 250],
            ['id' => 251, 'name' => ''],
            ['id' => 399, 'name' => 'Bassins de l’Essence rubis'],
        ],
    ])->dungeons;

    expect($dungeons)->toHaveCount(1)
        ->and($dungeons[0]->id)->toBe(399);
});
