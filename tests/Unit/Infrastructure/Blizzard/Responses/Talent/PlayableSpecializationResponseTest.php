<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\ResponsePayload;
use App\Infrastructure\Blizzard\Responses\Talent\PlayableSpecializationResponse;

/**
 * @param  array<string, mixed>  $decoded
 */
function playableSpecialization(array $decoded): PlayableSpecializationResponse
{
    return PlayableSpecializationResponse::fromPayload(ResponsePayload::forEndpoint('data/wow/playable-specialization/72', $decoded));
}

test('the talent tree id is read from the tree link', function (): void {
    expect(playableSpecialization([
        'spec_talent_tree' => ['key' => ['href' => 'https://eu.api.blizzard.com/data/wow/talent-tree/786/playable-specialization/72?namespace=static-eu']],
    ])->talentTreeId)->toBe(786);
});

test('a link that names no talent tree gives no id', function (): void {
    expect(playableSpecialization(['spec_talent_tree' => ['key' => ['href' => 'https://eu.api.blizzard.com/data/wow/other/1']]])->talentTreeId)->toBeNull();
});

test('a specialization without talent tree gives no id', function (): void {
    expect(playableSpecialization([])->talentTreeId)->toBeNull();
});
