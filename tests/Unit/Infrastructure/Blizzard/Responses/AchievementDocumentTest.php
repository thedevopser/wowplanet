<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\AchievementDocument;
use App\Infrastructure\Blizzard\Responses\Exceptions\MissingFieldException;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * @param  array<string, mixed>  $decoded
 */
function achievementDocument(array $decoded): AchievementDocument
{
    return AchievementDocument::fromPayload(
        ResponsePayload::forEndpoint('data/wow/achievement/41802', $decoded),
    );
}

test('a detail carries the points of the achievement', function (): void {
    $achievementDocument = achievementDocument([
        'id' => 41802,
        'name' => 'Reprise des Chants éternels',
        'points' => 10,
    ]);

    expect($achievementDocument->id)->toBe(41802)
        ->and($achievementDocument->points)->toBe(10)
        ->and($achievementDocument->faction)->toBeNull();
});

test('a faction requirement becomes the faction of the achievement', function (): void {
    expect(achievementDocument(['id' => 447, 'points' => 0, 'requirements' => ['faction' => ['type' => 'HORDE', 'name' => 'Horde']]])->faction)->toBe('Horde')
        ->and(achievementDocument(['id' => 448, 'points' => 0, 'requirements' => ['faction' => ['type' => 'ALLIANCE', 'name' => 'Alliance']]])->faction)->toBe('Alliance');
});

test('a requirement that is not a faction leaves the achievement to both sides', function (): void {
    expect(achievementDocument(['id' => 500, 'points' => 5, 'requirements' => ['playable_class' => ['id' => 2]]])->faction)->toBeNull();
});

test('an unknown faction type is no faction at all', function (): void {
    expect(achievementDocument(['id' => 500, 'points' => 5, 'requirements' => ['faction' => ['type' => 'NEUTRAL']]])->faction)->toBeNull();
});

test('an achievement worth zero point is worth zero, not unknown', function (): void {
    expect(achievementDocument(['id' => 447, 'points' => 0])->points)->toBe(0);
});

test('a detail without points breaks the contract', function (): void {
    achievementDocument(['id' => 41802]);
})->throws(MissingFieldException::class);
