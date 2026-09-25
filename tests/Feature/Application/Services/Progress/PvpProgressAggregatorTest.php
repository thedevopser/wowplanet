<?php

declare(strict_types=1);

use App\Application\Services\Progress\PvpProgressAggregator;
use App\Infrastructure\Blizzard\Responses\Pvp\PvpBracketResponse;
use App\Infrastructure\Blizzard\Responses\Pvp\PvpSummaryResponse;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

beforeEach(function (): void {
    $this->aggregator = new PvpProgressAggregator;
});

/**
 * @param  array<string, mixed>  $summary
 * @param  array<string, array<string, mixed>>  $brackets
 * @param  array<int, array{name?: string, icon_url?: string}>  $tiers
 * @param  array<string, string>  $specNames
 * @return array<string, mixed>|null
 */
function aggregatePvp(PvpProgressAggregator $pvpProgressAggregator, array $summary, array $brackets, array $tiers, int $currentSeasonId, array $specNames = []): ?array
{
    return $pvpProgressAggregator->aggregate(
        PvpSummaryResponse::fromPayload(ResponsePayload::forEndpoint('profile/wow/character/hyjal/thrall/pvp-summary', $summary)),
        array_map(static fn (array $bracket): PvpBracketResponse => PvpBracketResponse::fromPayload(ResponsePayload::forEndpoint('profile/wow/character/hyjal/thrall/pvp-bracket', $bracket)), $brackets),
        $tiers,
        $currentSeasonId,
        $specNames,
    );
}

function pvpSummary(array $overrides = []): array
{
    return array_merge([
        'honor_level' => 500,
        'honorable_kills' => 12345,
        'pvp_map_statistics' => [],
    ], $overrides);
}

function pvpBracket(array $overrides = []): array
{
    return array_merge([
        'bracket' => ['id' => 2, 'type' => 'ARENA_3v3'],
        'rating' => 1842,
        'season' => ['id' => 40],
        'tier' => ['id' => 12],
        'season_match_statistics' => ['played' => 100, 'won' => 55, 'lost' => 45],
        'weekly_match_statistics' => ['played' => 10, 'won' => 6, 'lost' => 4],
    ], $overrides);
}

test('aggregate normalises brackets into ordered groups', function (): void {
    $result = aggregatePvp($this->aggregator,
        pvpSummary(),
        [
            'blitz-priest-shadow' => pvpBracket(['rating' => 1500, 'bracket' => ['type' => 'BLITZ']]),
            '3v3' => pvpBracket(['rating' => 1842]),
            'rbg' => pvpBracket(['rating' => 1600, 'bracket' => ['type' => 'BATTLEGROUNDS']]),
            '2v2' => pvpBracket(['rating' => 1400, 'bracket' => ['type' => 'ARENA_2v2']]),
            'shuffle-priest-shadow' => pvpBracket(['rating' => 1700, 'bracket' => ['type' => 'SHUFFLE']]),
        ],
        [],
        40,
    );

    expect($result)->not->toBeNull()
        ->and($result['season_id'])->toBe(40)
        ->and($result['honor_level'])->toBe(500)
        ->and($result['honorable_kills'])->toBe(12345)
        ->and($result['best_rating'])->toBe(1842)
        ->and(array_column($result['groups'], 'key'))->toBe(['arena', 'rbg', 'shuffle', 'blitz']);

    $arena = $result['groups'][0];
    expect($arena['label'])->toBe('Arène')
        ->and(array_column($arena['brackets'], 'slug'))->toBe(['2v2', '3v3'])
        ->and($arena['brackets'][0]['label'])->toBe('Arène 2c2')
        ->and($arena['brackets'][1]['label'])->toBe('Arène 3c3');

    expect($result['groups'][1]['brackets'][0]['label'])->toBe('Champs de bataille cotés');
});

test('aggregate computes season and weekly statistics with win rate', function (): void {
    $result = aggregatePvp($this->aggregator,
        pvpSummary(),
        ['3v3' => pvpBracket()],
        [],
        40,
    );

    $bracket = $result['groups'][0]['brackets'][0];

    expect($bracket['rating'])->toBe(1842)
        ->and($bracket['played'])->toBe(100)
        ->and($bracket['won'])->toBe(55)
        ->and($bracket['lost'])->toBe(45)
        ->and($bracket['win_rate'])->toBe(55.0)
        ->and($bracket['weekly'])->toBe(['played' => 10, 'won' => 6, 'lost' => 4]);
});

test('aggregate resolves tier name and icon', function (): void {
    $result = aggregatePvp($this->aggregator,
        pvpSummary(),
        ['3v3' => pvpBracket(['tier' => ['id' => 12]])],
        [12 => ['name' => 'Duelliste', 'icon_url' => 'https://example.test/duelist.jpg']],
        40,
    );

    $bracket = $result['groups'][0]['brackets'][0];

    expect($bracket['tier_name'])->toBe('Duelliste')
        ->and($bracket['tier_icon_url'])->toBe('https://example.test/duelist.jpg');
});

test('aggregate leaves tier empty when the tier is unknown', function (): void {
    $result = aggregatePvp($this->aggregator,
        pvpSummary(),
        ['3v3' => pvpBracket(['tier' => []])],
        [],
        40,
    );

    $bracket = $result['groups'][0]['brackets'][0];

    expect($bracket['tier_name'])->toBeNull()
        ->and($bracket['tier_icon_url'])->toBeNull();
});

test('aggregate labels solo shuffle brackets with their specialization', function (): void {
    $result = aggregatePvp($this->aggregator,
        pvpSummary(),
        [
            'shuffle-priest-shadow' => pvpBracket([
                'bracket' => ['type' => 'SHUFFLE'],
                'rating' => 1700,
                'specialization' => ['id' => 258, 'name' => 'Ombre'],
            ]),
        ],
        [],
        40,
    );

    $group = $result['groups'][0];

    expect($group['key'])->toBe('shuffle')
        ->and($group['label'])->toBe('Mêlée solo')
        ->and($group['brackets'][0]['spec'])->toBe('Ombre')
        ->and($group['brackets'][0]['label'])->toBe('Mêlée solo — Ombre');
});

test('aggregate falls back to the slug when a shuffle bracket has no specialization', function (): void {
    $result = aggregatePvp($this->aggregator,
        pvpSummary(),
        ['shuffle-priest-shadow' => pvpBracket(['bracket' => ['type' => 'SHUFFLE'], 'rating' => 1700])],
        [],
        40,
    );

    $bracket = $result['groups'][0]['brackets'][0];

    expect($bracket['spec'])->toBeNull()
        ->and($bracket['label'])->toBe('Mêlée solo — Priest Shadow');
});

test('aggregate falls back to the provided french spec name', function (): void {
    $result = aggregatePvp($this->aggregator,
        pvpSummary(),
        ['shuffle-priest-shadow' => pvpBracket(['bracket' => ['type' => 'SHUFFLE'], 'rating' => 1700])],
        [],
        40,
        ['shuffle-priest-shadow' => 'Prêtre · Ombre'],
    );

    $bracket = $result['groups'][0]['brackets'][0];

    expect($bracket['spec'])->toBe('Prêtre · Ombre')
        ->and($bracket['label'])->toBe('Mêlée solo — Prêtre · Ombre');
});

test('aggregate prefers the specialization returned by the api over the fallback', function (): void {
    $result = aggregatePvp($this->aggregator,
        pvpSummary(),
        ['shuffle-priest-shadow' => pvpBracket([
            'bracket' => ['type' => 'SHUFFLE'],
            'rating' => 1700,
            'specialization' => ['name' => 'Ombre'],
        ])],
        [],
        40,
        ['shuffle-priest-shadow' => 'Prêtre · Ombre'],
    );

    expect($result['groups'][0]['brackets'][0]['spec'])->toBe('Ombre');
});

test('aggregate sorts shuffle brackets by rating descending', function (): void {
    $result = aggregatePvp($this->aggregator,
        pvpSummary(),
        [
            'shuffle-priest-shadow' => pvpBracket(['bracket' => ['type' => 'SHUFFLE'], 'rating' => 1500, 'specialization' => ['name' => 'Ombre']]),
            'shuffle-priest-discipline' => pvpBracket(['bracket' => ['type' => 'SHUFFLE'], 'rating' => 1900, 'specialization' => ['name' => 'Discipline']]),
        ],
        [],
        40,
    );

    expect(array_column($result['groups'][0]['brackets'], 'spec'))->toBe(['Discipline', 'Ombre']);
});

test('aggregate discards brackets from a previous season', function (): void {
    $result = aggregatePvp($this->aggregator,
        pvpSummary(),
        [
            '2v2' => pvpBracket(['season' => ['id' => 39], 'rating' => 2400]),
            '3v3' => pvpBracket(['season' => ['id' => 40], 'rating' => 1200]),
        ],
        [],
        40,
    );

    expect($result['best_rating'])->toBe(1200)
        ->and(array_column($result['groups'][0]['brackets'], 'slug'))->toBe(['3v3']);
});

test('aggregate keeps every bracket when the current season is unknown', function (): void {
    $result = aggregatePvp($this->aggregator,
        pvpSummary(),
        ['2v2' => pvpBracket(['season' => ['id' => 39], 'rating' => 2400])],
        [],
        0,
    );

    expect($result['groups'][0]['brackets'])->toHaveCount(1)
        ->and($result['season_id'])->toBe(39);
});

test('aggregate discards empty and unplayed brackets', function (): void {
    $result = aggregatePvp($this->aggregator,
        pvpSummary(),
        [
            '2v2' => [],
            'rbg' => pvpBracket([
                'bracket' => ['type' => 'BATTLEGROUNDS'],
                'rating' => 0,
                'season_match_statistics' => ['played' => 0, 'won' => 0, 'lost' => 0],
            ]),
            '3v3' => pvpBracket(),
        ],
        [],
        40,
    );

    expect($result['groups'])->toHaveCount(1)
        ->and($result['groups'][0]['key'])->toBe('arena')
        ->and(array_column($result['groups'][0]['brackets'], 'slug'))->toBe(['3v3']);
});

test('aggregate defaults missing statistics to zero without dividing by zero', function (): void {
    $result = aggregatePvp($this->aggregator,
        pvpSummary(),
        ['3v3' => ['bracket' => ['type' => 'ARENA_3v3'], 'rating' => 1500, 'season' => ['id' => 40]]],
        [],
        40,
    );

    $bracket = $result['groups'][0]['brackets'][0];

    expect($bracket['played'])->toBe(0)
        ->and($bracket['won'])->toBe(0)
        ->and($bracket['lost'])->toBe(0)
        ->and($bracket['win_rate'])->toBe(0.0)
        ->and($bracket['weekly'])->toBe(['played' => 0, 'won' => 0, 'lost' => 0]);
});

test('aggregate groups unknown bracket types under a catch-all group', function (): void {
    $result = aggregatePvp($this->aggregator,
        pvpSummary(),
        ['brawl-gambit' => pvpBracket(['bracket' => ['type' => 'BRAWL'], 'rating' => 1000])],
        [],
        40,
    );

    expect($result['groups'][0]['key'])->toBe('other')
        ->and($result['groups'][0]['label'])->toBe('Autres modes')
        ->and($result['groups'][0]['brackets'][0]['label'])->toBe('Brawl Gambit');
});

test('aggregate totals unrated battleground statistics', function (): void {
    $result = aggregatePvp($this->aggregator,
        pvpSummary([
            'pvp_map_statistics' => [
                ['world_map' => ['name' => 'Goulet'], 'match_statistics' => ['played' => 10, 'won' => 7, 'lost' => 3]],
                ['world_map' => ['name' => 'Arathi'], 'match_statistics' => ['played' => 6, 'won' => 2, 'lost' => 4]],
            ],
        ]),
        ['3v3' => pvpBracket()],
        [],
        40,
    );

    expect($result['battlegrounds'])->toBe([
        'played' => 16,
        'won' => 9,
        'lost' => 7,
        'win_rate' => 56.3,
    ]);
});

test('aggregate returns null for an empty summary', function (): void {
    expect(aggregatePvp($this->aggregator, [], [], [], 40))->toBeNull();
});

test('aggregate returns null when nothing rated and no honor earned', function (): void {
    $result = aggregatePvp($this->aggregator,
        pvpSummary(['honor_level' => 0, 'honorable_kills' => 0]),
        ['2v2' => []],
        [],
        40,
    );

    expect($result)->toBeNull();
});

test('aggregate keeps honor-only characters', function (): void {
    $result = aggregatePvp($this->aggregator,
        pvpSummary(['honor_level' => 42, 'honorable_kills' => 300]),
        [],
        [],
        40,
    );

    expect($result)->not->toBeNull()
        ->and($result['groups'])->toBe([])
        ->and($result['best_rating'])->toBe(0)
        ->and($result['honor_level'])->toBe(42);
});

test('aggregate returns null for an empty summary even with rated brackets', function (): void {
    expect(aggregatePvp($this->aggregator, [], ['3v3' => pvpBracket()], [], 40))->toBeNull();
});

test('aggregate keeps a character with only one sign of PvP activity', function (array $summary, array $brackets): void {
    expect(aggregatePvp($this->aggregator, pvpSummary($summary), $brackets, [], 40))->not->toBeNull();
})->with([
    'un bracket coté' => [['honor_level' => 0, 'honorable_kills' => 0], ['3v3' => pvpBracket()]],
    'des victoires honorables' => [['honor_level' => 0, 'honorable_kills' => 3], []],
    'des champs de bataille' => [[
        'honor_level' => 0,
        'honorable_kills' => 0,
        'pvp_map_statistics' => [['world_map' => ['name' => 'Goulet'], 'match_statistics' => ['played' => 1, 'won' => 0, 'lost' => 1]]],
    ], []],
]);

test('aggregate discards a previous season as soon as the current one is known', function (): void {
    $result = aggregatePvp($this->aggregator,
        pvpSummary(),
        ['2v2' => pvpBracket(['season' => ['id' => 1]]), '3v3' => pvpBracket(['season' => ['id' => 2]])],
        [],
        1,
    );

    expect(array_column($result['groups'][0]['brackets'], 'slug'))->toBe(['2v2']);
});

test('aggregate resolves no tier for a bracket that has none', function (): void {
    $result = aggregatePvp($this->aggregator,
        pvpSummary(),
        ['3v3' => pvpBracket(['tier' => []])],
        [1 => ['name' => 'Combattant']],
        40,
    );

    expect($result['groups'][0]['brackets'][0]['tier_name'])->toBeNull();
});

test('aggregate treats an empty tier name or icon as missing', function (): void {
    $result = aggregatePvp($this->aggregator,
        pvpSummary(),
        ['3v3' => pvpBracket(['tier' => ['id' => 12]])],
        [12 => ['name' => '', 'icon_url' => '']],
        40,
    );

    $bracket = $result['groups'][0]['brackets'][0];

    expect($bracket['tier_name'])->toBeNull()
        ->and($bracket['tier_icon_url'])->toBeNull();
});

test('aggregate falls back to the french spec name when the api returns an empty one', function (): void {
    $result = aggregatePvp($this->aggregator,
        pvpSummary(),
        ['shuffle-priest-shadow' => pvpBracket(['bracket' => ['type' => 'SHUFFLE'], 'specialization' => ['name' => '']])],
        [],
        40,
        ['shuffle-priest-shadow' => 'Ombre'],
    );

    expect($result['groups'][0]['brackets'][0]['spec'])->toBe('Ombre');
});

test('aggregate computes the win rate from a single match', function (): void {
    $result = aggregatePvp($this->aggregator,
        pvpSummary(),
        ['3v3' => pvpBracket(['season_match_statistics' => ['played' => 1, 'won' => 1, 'lost' => 0]])],
        [],
        40,
    );

    expect($result['groups'][0]['brackets'][0]['win_rate'])->toBe(100.0);
});
