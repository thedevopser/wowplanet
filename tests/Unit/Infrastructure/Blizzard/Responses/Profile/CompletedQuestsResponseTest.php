<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\Profile\CompletedQuestsResponse;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * @param  array<string, mixed>  $decoded
 */
function completedQuests(array $decoded): CompletedQuestsResponse
{
    return CompletedQuestsResponse::fromPayload(
        ResponsePayload::forEndpoint('profile/wow/character/hyjal/thrall/quests/completed', $decoded),
    );
}

test('it lists the completed quest ids in order', function (): void {
    expect(completedQuests(['quests' => [['id' => 100], ['id' => 101]]])->questIds)->toBe([100, 101]);
});

test('a quest entry without id is skipped', function (): void {
    expect(completedQuests(['quests' => [['id' => 100], ['name' => 'orphan']]])->questIds)->toBe([100]);
});

test('a character with no completed quest has an empty list', function (): void {
    expect(completedQuests([])->questIds)->toBe([]);
});
