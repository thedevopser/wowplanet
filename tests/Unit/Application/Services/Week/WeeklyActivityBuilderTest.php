<?php

declare(strict_types=1);

use App\Application\Services\Week\WeeklyActivityBuilder;
use App\Domain\Services\GreatVault;
use App\Domain\Services\WeeklyReset;
use App\Domain\ValueObjects\RaidDifficulty;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterRaidsResponse;
use App\Infrastructure\Blizzard\Responses\Profile\MythicKeystoneProfileResponse;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

const WEEK_NOW = '2026-11-14 20:00:00';
const THIS_WEEK = 1_794_400_000_000;
const LAST_WEEK = 1_762_000_000_000;

const VOIDSPIRE = 1307;
const DREAMRIFT = 1314;

/**
 * @return array<int, array{name: string, encounters: array<int, string>}>
 */
function seasonRaidJournal(): array
{
    return [
        VOIDSPIRE => ['name' => 'La Flèche du Vide', 'encounters' => [11 => 'Imperator Averzian', 12 => 'Vorasius', 13 => 'Salhadaar']],
        DREAMRIFT => ['name' => 'La Faille du Rêve', 'encounters' => [21 => 'Chimaerus']],
    ];
}

/**
 * @return array<int, string>
 */
function seasonDungeons(): array
{
    return [585 => 'Arène de la Cicatrice du Vide', 249 => 'Repaire de la Nexus-reine', 586 => 'Terrasse des Magistères'];
}

/**
 * @param  array<int, array<string, array<int, int>>>  $killsByInstance  last kill of each boss, by instance then difficulty
 */
function raidsResponse(array $killsByInstance): CharacterRaidsResponse
{
    $instances = [];
    foreach ($killsByInstance as $instanceId => $modes) {
        $instances[] = [
            'instance' => ['id' => $instanceId, 'name' => 'Instance '.$instanceId],
            'modes' => array_map(
                fn (string $difficulty, array $kills): array => [
                    'difficulty' => ['type' => $difficulty],
                    'progress' => ['completed_count' => count($kills), 'total_count' => 3, 'encounters' => array_map(
                        fn (int $encounterId, int $killedAt): array => ['encounter' => ['id' => $encounterId, 'name' => 'Boss '.$encounterId], 'last_kill_timestamp' => $killedAt],
                        array_keys($kills),
                        $kills,
                    )],
                ],
                array_keys($modes),
                $modes,
            ),
        ];
    }

    return CharacterRaidsResponse::fromPayload(ResponsePayload::forEndpoint('profile/wow/character/dalaran/x/encounters/raids', [
        'expansions' => [['expansion' => ['id' => 505], 'instances' => $instances]],
    ]));
}

/**
 * @param  array<int, int>  $levelByDungeon
 */
function weeklyKeys(array $levelByDungeon): MythicKeystoneProfileResponse
{
    $runs = [];
    foreach ($levelByDungeon as $dungeonId => $level) {
        $runs[] = ['dungeon' => ['id' => $dungeonId, 'name' => 'Donjon '.$dungeonId], 'keystone_level' => $level, 'completed_timestamp' => THIS_WEEK];
    }

    return MythicKeystoneProfileResponse::fromPayload(ResponsePayload::forEndpoint('profile/wow/character/dalaran/x/mythic-keystone-profile', [
        'current_period' => ['period' => ['id' => 1089], 'best_runs' => $runs],
    ]));
}

/**
 * @return array{name: string, realm: string, realmSlug: string, level: int, classId: int, raids: CharacterRaidsResponse|null, mythicKeystone: MythicKeystoneProfileResponse|null}
 */
function weekCharacter(string $name, int $level = 90, ?CharacterRaidsResponse $characterRaidsResponse = null, ?MythicKeystoneProfileResponse $mythicKeystoneProfileResponse = null): array
{
    return ['name' => $name, 'realm' => 'Dalaran', 'realmSlug' => 'dalaran', 'level' => $level, 'classId' => 2, 'raids' => $characterRaidsResponse, 'mythicKeystone' => $mythicKeystoneProfileResponse];
}

/**
 * @param  list<array{name: string, realm: string, realmSlug: string, level: int, classId: int, raids: CharacterRaidsResponse|null, mythicKeystone: MythicKeystoneProfileResponse|null}>  $characters
 * @return array<string, mixed>
 */
function buildWeek(array $characters): array
{
    return new WeeklyActivityBuilder(new WeeklyReset, new GreatVault)->build(
        $characters,
        seasonRaidJournal(),
        seasonDungeons(),
        new DateTimeImmutable(WEEK_NOW, new DateTimeZone('Europe/Paris')),
    );
}

test('the raid of a character counts the bosses beaten since the reset only', function (): void {
    $raids = raidsResponse([VOIDSPIRE => [
        'HEROIC' => [11 => THIS_WEEK, 12 => THIS_WEEK],
        'NORMAL' => [11 => LAST_WEEK, 13 => THIS_WEEK],
    ]]);

    $raid = buildWeek([weekCharacter('Gothdroid', characterRaidsResponse: $raids)])['characters'][0]['raid'];

    expect($raid['count'])->toBe(3)
        ->and($raid['slots'][0])->toBe(['threshold' => 2, 'reached' => true, 'reward' => RaidDifficulty::Heroic]);
});

test('the raid of a character lists, by raid and difficulty, the bosses still to beat this week', function (): void {
    $raids = raidsResponse([VOIDSPIRE => [
        'HEROIC' => [11 => THIS_WEEK, 12 => THIS_WEEK],
        'NORMAL' => [11 => LAST_WEEK],
    ]]);

    $remaining = buildWeek([weekCharacter('Gothdroid', characterRaidsResponse: $raids)])['characters'][0]['raid']['remaining'];

    expect($remaining[0]['instanceId'])->toBe(VOIDSPIRE)
        ->and($remaining[0]['name'])->toBe('La Flèche du Vide')
        ->and(array_column($remaining[0]['difficulties'], 'encounters', 'difficulty'))->toBe([
            'LFR' => ['Imperator Averzian', 'Vorasius', 'Salhadaar'],
            'NORMAL' => ['Imperator Averzian', 'Vorasius', 'Salhadaar'],
            'HEROIC' => ['Salhadaar'],
            'MYTHIC' => ['Imperator Averzian', 'Vorasius', 'Salhadaar'],
        ])
        ->and($remaining[1]['name'])->toBe('La Faille du Rêve');
});

test('a raid fully beaten in a difficulty leaves that difficulty, and leaves the list once beaten in all', function (): void {
    $allBeaten = [21 => THIS_WEEK];
    $raids = raidsResponse([DREAMRIFT => ['LFR' => $allBeaten, 'NORMAL' => $allBeaten, 'HEROIC' => $allBeaten, 'MYTHIC' => [21 => LAST_WEEK]]]);

    $remaining = buildWeek([weekCharacter('Gothdroid', characterRaidsResponse: $raids)])['characters'][0]['raid']['remaining'];
    expect($remaining[1]['difficulties'])->toBe([['difficulty' => 'MYTHIC', 'encounters' => ['Chimaerus']]]);

    $raids = raidsResponse([DREAMRIFT => ['LFR' => $allBeaten, 'NORMAL' => $allBeaten, 'HEROIC' => $allBeaten, 'MYTHIC' => $allBeaten]]);

    $remaining = buildWeek([weekCharacter('Gothdroid', characterRaidsResponse: $raids)])['characters'][0]['raid']['remaining'];
    expect(array_column($remaining, 'instanceId'))->toBe([VOIDSPIRE]);

    $everyBoss = [11 => THIS_WEEK, 12 => THIS_WEEK, 13 => THIS_WEEK];
    $raids = raidsResponse([VOIDSPIRE => ['LFR' => $everyBoss, 'NORMAL' => $everyBoss, 'HEROIC' => $everyBoss, 'MYTHIC' => $everyBoss]]);

    $remaining = buildWeek([weekCharacter('Gothdroid', characterRaidsResponse: $raids)])['characters'][0]['raid']['remaining'];
    expect($remaining)->toHaveCount(1)
        ->and($remaining[0]['instanceId'])->toBe(DREAMRIFT);
});

test('a difficulty Blizzard sends without the site knowing it is left aside', function (): void {
    $raids = raidsResponse([VOIDSPIRE => ['TIMEWALKING' => [11 => THIS_WEEK], 'NORMAL' => [12 => THIS_WEEK]]]);

    expect(buildWeek([weekCharacter('Gothdroid', characterRaidsResponse: $raids)])['characters'][0]['raid']['count'])->toBe(1);
});

test('the Mythic+ of a character counts the keys of the week and names the dungeons without one', function (): void {
    $mythicPlus = buildWeek([weekCharacter('Gothdroid', mythicKeystoneProfileResponse: weeklyKeys([249 => 12, 585 => 10]))])['characters'][0]['mythicPlus'];

    expect($mythicPlus['count'])->toBe(2)
        ->and($mythicPlus['slots'][0])->toBe(['threshold' => 1, 'reached' => true, 'reward' => 12])
        ->and($mythicPlus['missingDungeons'])->toBe(['Terrasse des Magistères']);
});

test('a key Blizzard sends without level or dungeon is left aside', function (): void {
    $keys = MythicKeystoneProfileResponse::fromPayload(ResponsePayload::forEndpoint('profile/wow/character/dalaran/x/mythic-keystone-profile', [
        'current_period' => ['best_runs' => [
            ['dungeon' => ['id' => 585], 'completed_timestamp' => THIS_WEEK],
            ['keystone_level' => 14, 'completed_timestamp' => THIS_WEEK],
            ['dungeon' => ['id' => 249], 'keystone_level' => 11, 'completed_timestamp' => THIS_WEEK],
        ]],
    ]));

    $mythicPlus = buildWeek([weekCharacter('Gothdroid', mythicKeystoneProfileResponse: $keys)])['characters'][0]['mythicPlus'];

    expect(array_column($mythicPlus['slots'], 'reward'))->toBe([14, null, null])
        ->and($mythicPlus['missingDungeons'])->toBe(['Terrasse des Magistères']);
});

test('a character without raid nor key stands at zero, with everything left', function (): void {
    $character = buildWeek([weekCharacter('Gothdroid')])['characters'][0];

    expect($character['raid']['count'])->toBe(0)
        ->and(array_column($character['raid']['remaining'], 'instanceId'))->toBe([VOIDSPIRE, DREAMRIFT])
        ->and($character['mythicPlus']['count'])->toBe(0)
        ->and($character['mythicPlus']['missingDungeons'])->toBe(array_values(seasonDungeons()))
        ->and($character['nextSlotGap'])->toBe(1);
});

test('it describes each character it shows', function (): void {
    $character = buildWeek([weekCharacter('Gothdroid')])['characters'][0];

    expect($character)->toMatchArray(['name' => 'Gothdroid', 'realm' => 'Dalaran', 'realmSlug' => 'dalaran', 'level' => 90, 'classId' => 2]);
});

test('the characters under the highest level of the account are left aside and counted', function (): void {
    $week = buildWeek([weekCharacter('Gothdroid', 90), weekCharacter('Apery', 80), weekCharacter('Jaina', 90), weekCharacter('Petit', 12)]);

    expect(array_column($week['characters'], 'name'))->toEqualCanonicalizing(['Gothdroid', 'Jaina'])
        ->and($week['belowMaxLevel'])->toBe(2)
        ->and($week['maxLevel'])->toBe(90);
});

test('an account without character has nothing to show', function (): void {
    expect(buildWeek([]))->toBe(['characters' => [], 'belowMaxLevel' => 0, 'maxLevel' => 0]);
});

test('the characters come from the closest to a slot to the farthest, the most advanced first on a tie, a full vault last', function (): void {
    $bosses = fn (int $count): CharacterRaidsResponse => raidsResponse([VOIDSPIRE => [
        'NORMAL' => array_fill_keys(array_slice([11, 12, 13, 14, 15, 16], 0, $count), THIS_WEEK),
    ]]);
    $keys = fn (int $count): MythicKeystoneProfileResponse => weeklyKeys(array_fill_keys(array_slice([585, 249, 586, 1, 2, 3, 4, 5], 0, $count), 10));

    $week = buildWeek([
        weekCharacter('Full', characterRaidsResponse: $bosses(6), mythicKeystoneProfileResponse: $keys(8)),
        weekCharacter('Alpha'),
        weekCharacter('Gamma', characterRaidsResponse: $bosses(4), mythicKeystoneProfileResponse: $keys(4)),
        weekCharacter('Beta', characterRaidsResponse: $bosses(3), mythicKeystoneProfileResponse: $keys(1)),
        weekCharacter('Aaron'),
    ]);

    expect(array_column($week['characters'], 'name'))->toBe(['Beta', 'Aaron', 'Alpha', 'Gamma', 'Full'])
        ->and(array_column($week['characters'], 'nextSlotGap'))->toBe([1, 1, 1, 2, null]);
});

test('on a tie, the slots reached in both vaults count to tell the most advanced', function (): void {
    $bosses = fn (int $count): CharacterRaidsResponse => raidsResponse([VOIDSPIRE => [
        'NORMAL' => array_fill_keys(array_slice([11, 12, 13, 14, 15, 16], 0, $count), THIS_WEEK),
    ]]);
    $keys = fn (int $count): MythicKeystoneProfileResponse => weeklyKeys(array_fill_keys(array_slice([585, 249, 586, 1, 2, 3, 4, 5], 0, $count), 10));

    $week = buildWeek([
        weekCharacter('Quentin', mythicKeystoneProfileResponse: $keys(6)),
        weekCharacter('Tom', characterRaidsResponse: $bosses(4), mythicKeystoneProfileResponse: $keys(2)),
        weekCharacter('Sam', characterRaidsResponse: $bosses(2), mythicKeystoneProfileResponse: $keys(6)),
        weekCharacter('Pierre', characterRaidsResponse: $bosses(4), mythicKeystoneProfileResponse: $keys(2)),
    ]);

    expect(array_column($week['characters'], 'nextSlotGap'))->toBe([2, 2, 2, 2])
        ->and(array_column($week['characters'], 'name'))->toBe(['Pierre', 'Sam', 'Tom', 'Quentin']);
});
