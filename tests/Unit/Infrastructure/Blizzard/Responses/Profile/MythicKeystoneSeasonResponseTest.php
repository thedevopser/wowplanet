<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\Exceptions\MissingFieldException;
use App\Infrastructure\Blizzard\Responses\Profile\MythicKeystoneSeasonResponse;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * @param  array<string, mixed>  $decoded
 */
function mythicSeason(array $decoded): MythicKeystoneSeasonResponse
{
    return MythicKeystoneSeasonResponse::fromPayload(
        ResponsePayload::forEndpoint('profile/wow/character/hyjal/thrall/mythic-keystone-profile/season/15', $decoded),
    );
}

test('a complete season carries the rating and the best runs with their group', function (): void {
    $mythicKeystoneSeasonResponse = mythicSeason([
        'season' => ['id' => 15],
        'mythic_rating' => ['rating' => 2456.789, 'color' => ['r' => 255, 'g' => 128, 'b' => 0, 'a' => 1.0]],
        'best_runs' => [[
            'dungeon' => ['id' => 501, 'name' => 'Faille'],
            'keystone_level' => 12,
            'duration' => 1_800_000,
            'completed_timestamp' => 1_700_000_000_000,
            'is_completed_within_time' => true,
            'mythic_rating' => ['rating' => 250.45, 'color' => ['r' => 1, 'g' => 2, 'b' => 3, 'a' => 1]],
            'map_rating' => ['rating' => 300],
            'members' => [[
                'character' => ['name' => 'Thrall', 'realm' => ['name' => 'Hyjal']],
                'specialization' => ['name' => 'Amélioration'],
                'equipped_item_level' => 620,
            ]],
        ]],
    ]);

    $run = $mythicKeystoneSeasonResponse->bestRuns[0];
    $member = $run->members[0];

    expect($mythicKeystoneSeasonResponse->seasonId)->toBe(15)
        ->and($mythicKeystoneSeasonResponse->rating)->toBe(2456.789)
        ->and($mythicKeystoneSeasonResponse->ratingColor?->toArray())->toBe(['r' => 255, 'g' => 128, 'b' => 0, 'a' => 1.0])
        ->and($run->dungeonId)->toBe(501)
        ->and($run->dungeonName)->toBe('Faille')
        ->and($run->keystoneLevel)->toBe(12)
        ->and($run->durationMs)->toBe(1_800_000)
        ->and($run->completedTimestamp)->toBe(1_700_000_000_000)
        ->and($run->completedWithinTime)->toBeTrue()
        ->and($run->rating)->toBe(250.45)
        ->and($run->ratingColor?->toArray())->toBe(['r' => 1, 'g' => 2, 'b' => 3, 'a' => 1.0])
        ->and($run->mapRating)->toBe(300.0)
        ->and($run->mapRatingColor)->toBeNull()
        ->and($member->name)->toBe('Thrall')
        ->and($member->realmName)->toBe('Hyjal')
        ->and($member->specializationName)->toBe('Amélioration')
        ->and($member->equippedItemLevel)->toBe(620);
});

test('a season without runs nor rating is a normal case', function (): void {
    $mythicKeystoneSeasonResponse = mythicSeason(['season' => ['id' => 15]]);

    expect($mythicKeystoneSeasonResponse->rating)->toBeNull()
        ->and($mythicKeystoneSeasonResponse->ratingColor)->toBeNull()
        ->and($mythicKeystoneSeasonResponse->bestRuns)->toBe([]);
});

test('a run with partial data leaves the missing fields null', function (): void {
    $run = mythicSeason(['best_runs' => [['members' => [['character' => ['name' => 'Jaina']]]]]])->bestRuns[0];

    expect($run->dungeonName)->toBeNull()
        ->and($run->keystoneLevel)->toBeNull()
        ->and($run->completedWithinTime)->toBeNull()
        ->and($run->rating)->toBeNull()
        ->and($run->members[0]->realmName)->toBeNull()
        ->and($run->members[0]->equippedItemLevel)->toBeNull();
});

test('a rating color missing a channel breaks the contract', function (): void {
    mythicSeason(['mythic_rating' => ['rating' => 1.0, 'color' => ['r' => 1, 'g' => 2, 'b' => 3]]]);
})->throws(MissingFieldException::class, 'mythic_rating.color.a');
