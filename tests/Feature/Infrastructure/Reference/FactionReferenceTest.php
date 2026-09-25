<?php

declare(strict_types=1);

use App\Infrastructure\Reference\FactionReference;
use Illuminate\Support\Facades\DB;

/** Identifiant de Hurlevent, dont le masque sert de référence Alliance. */
const STORMWIND = 72;

/** Faction parente servant d'en-tête d'extension pour Legion. */
const LEGION_HEADER = 1834;

/** Faction parente servant d'en-tête d'extension pour Dragonflight. */
const DRAGONFLIGHT_HEADER = 2506;

const REF_ALLIANCE = [-1321907123, 1427461461];

const REF_HORDE = [1309324210, -1440044374];

/**
 * @param  array{0: int|null, 1: int|null}|null  $raceMask
 */
function seedReferenceFaction(
    int $id,
    string $name = 'Une faction',
    int $reputationIndex = 0,
    int $parentFactionId = 0,
    ?int $renownCurrencyId = null,
    ?int $friendshipRepId = null,
    ?int $reputationMax0 = null,
    ?int $reputationMax1 = null,
    ?array $raceMask = null,
): void {
    DB::table('wow_ref_faction')->insert([
        'id' => $id,
        'name_lang' => $name,
        'reputation_index' => $reputationIndex,
        'parent_faction_id' => $parentFactionId,
        'expansion' => null,
        'friendship_rep_id' => $friendshipRepId,
        'renown_currency_id' => $renownCurrencyId,
        'reputation_max_0' => $reputationMax0,
        'reputation_max_1' => $reputationMax1,
        'reputation_race_masks0_0' => $raceMask[0] ?? null,
        'reputation_race_masks0_1' => $raceMask[1] ?? null,
    ]);
}

function seedReferenceCurrency(int $id, int $maxQty): void
{
    DB::table('wow_ref_currency_types')->insert(['id' => $id, 'name_lang' => 'Renom', 'max_qty' => $maxQty]);
}

function factionReference(): FactionReference
{
    return resolve(FactionReference::class);
}

// ─── Extension d'une réputation ─────────────────────────────

test('it resolves the expansion of a reputation through its parent header', function (): void {
    seedReferenceFaction(LEGION_HEADER, 'Legion');
    seedReferenceFaction(500, 'Les Sentinelles', parentFactionId: LEGION_HEADER);

    expect(factionReference()->expansions())->toBe([500 => 6]);
});

test('it walks up several parents to reach the expansion header', function (): void {
    seedReferenceFaction(DRAGONFLIGHT_HEADER, 'Dragonflight');
    seedReferenceFaction(600, 'Intermédiaire', parentFactionId: DRAGONFLIGHT_HEADER);
    seedReferenceFaction(601, 'Feuille', parentFactionId: 600);

    expect(factionReference()->expansions())->toHaveKey(601)
        ->and(factionReference()->expansions()[601])->toBe(9);
});

test('it leaves out a reputation with no parent at all', function (): void {
    seedReferenceFaction(500, 'Orpheline', parentFactionId: 0);

    expect(factionReference()->expansions())->toBe([]);
});

test('it leaves out a faction that is not a reputation', function (): void {
    seedReferenceFaction(LEGION_HEADER, 'Legion');
    seedReferenceFaction(500, 'Technique', reputationIndex: -1, parentFactionId: LEGION_HEADER);

    expect(factionReference()->expansions())->toBe([]);
});

test('it leaves out the reputations the API never returns', function (string $name): void {
    seedReferenceFaction(LEGION_HEADER, 'Legion');
    seedReferenceFaction(500, $name, parentFactionId: LEGION_HEADER);

    expect(factionReference()->expansions())->toBe([]);
})->with([
    'paragon' => ['Les Sentinelles (parangon)'],
    'delve season' => ['Gouffres saison 2'],
    'hunt season' => ["Traque\u{00A0}saison\u{00A0}3"],
    'deprecated' => ['DEPRECATED Something'],
    'do not translate' => ['[DNT] Something'],
    'player' => ['JOUEUR Something'],
]);

// ─── Nom d'une réputation ───────────────────────────────────

test('it reads the localized name of a reputation', function (): void {
    seedReferenceFaction(LEGION_HEADER, 'Legion');
    seedReferenceFaction(500, 'Les Sentinelles', parentFactionId: LEGION_HEADER);

    expect(factionReference()->names())->toBe([500 => 'Les Sentinelles']);
});

test('it names a reputation even when no expansion header sits above it', function (): void {
    seedReferenceFaction(500, 'Les Sentinelles', parentFactionId: 999);

    expect(factionReference()->names())->toBe([500 => 'Les Sentinelles']);
});

// ─── Renom maximal ──────────────────────────────────────────

test('it reads the maximum renown level through the currency a reputation grants', function (): void {
    seedReferenceCurrency(2900, 25);
    seedReferenceFaction(500, 'Renom', renownCurrencyId: 2900);

    expect(factionReference()->maxRenownLevels())->toBe([500 => 25]);
});

test('it leaves out a reputation whose renown currency is unknown', function (): void {
    seedReferenceFaction(500, 'Renom', renownCurrencyId: 2900);

    expect(factionReference()->maxRenownLevels())->toBe([]);
});

test('it leaves out a traditional reputation, which grants no renown currency', function (): void {
    seedReferenceFaction(500, 'Classique');

    expect(factionReference()->maxRenownLevels())->toBe([]);
});

// ─── Réputations à l'échelle du compte ──────────────────────

test('it counts a renown reputation as account wide', function (): void {
    seedReferenceFaction(500, 'Renom', renownCurrencyId: 2900);

    expect(factionReference()->accountWideIds())->toHaveKey(500);
});

test('it counts a friendship reputation as account wide', function (): void {
    seedReferenceFaction(500, 'Amitié', friendshipRepId: 12);

    expect(factionReference()->accountWideIds())->toHaveKey(500);
});

test('it counts every reputation from Dragonflight onwards as account wide', function (): void {
    seedReferenceFaction(DRAGONFLIGHT_HEADER, 'Dragonflight');
    seedReferenceFaction(500, 'Djaradin', parentFactionId: DRAGONFLIGHT_HEADER);

    expect(factionReference()->accountWideIds())->toHaveKey(500);
});

test('it leaves an older traditional reputation per character', function (): void {
    seedReferenceFaction(LEGION_HEADER, 'Legion');
    seedReferenceFaction(500, 'Les Sentinelles', parentFactionId: LEGION_HEADER);

    expect(factionReference()->accountWideIds())->toBe([]);
});

// ─── Faction d'une réputation exclusive ─────────────────────

test('it sides an exclusive reputation with the Alliance when its races overlap Stormwind', function (): void {
    seedReferenceFaction(STORMWIND, 'Hurlevent', raceMask: REF_ALLIANCE);
    seedReferenceFaction(500, 'Exclusive', reputationMax0: 42000, reputationMax1: -1, raceMask: REF_ALLIANCE);

    expect(factionReference()->factions())->toBe([500 => 'Alliance']);
});

test('it sides an exclusive reputation with the Horde when its races do not', function (): void {
    seedReferenceFaction(STORMWIND, 'Hurlevent', raceMask: REF_ALLIANCE);
    seedReferenceFaction(500, 'Exclusive', reputationMax0: 42000, reputationMax1: -1, raceMask: REF_HORDE);

    expect(factionReference()->factions())->toBe([500 => 'Horde']);
});

test('it sides no reputation at all without the Stormwind reference mask', function (): void {
    seedReferenceFaction(500, 'Exclusive', reputationMax0: 42000, reputationMax1: -1, raceMask: REF_ALLIANCE);

    expect(factionReference()->factions())->toBe([]);
});

test('it leaves a non exclusive reputation unsided', function (): void {
    seedReferenceFaction(STORMWIND, 'Hurlevent', raceMask: REF_ALLIANCE);
    seedReferenceFaction(500, 'Partagée', reputationMax0: 42000, reputationMax1: 42000, raceMask: REF_ALLIANCE);

    expect(factionReference()->factions())->toBe([]);
});

test('it never sides a reputation on an identifier read as if it were a mask', function (): void {
    seedReferenceFaction(STORMWIND, 'Hurlevent', raceMask: REF_ALLIANCE);
    seedReferenceFaction(500, 'Sans masque', reputationMax0: 42000, reputationMax1: -1, raceMask: [null, null]);

    expect(factionReference()->factions())->toBe([]);
});

test('it leaves a reputation with no cap at all unsided', function (): void {
    seedReferenceFaction(STORMWIND, 'Hurlevent', raceMask: REF_ALLIANCE);
    seedReferenceFaction(500, 'Sans plafond', reputationMax0: 0, reputationMax1: -1, raceMask: REF_ALLIANCE);

    expect(factionReference()->factions())->toBe([]);
});

test('it gives up on a parent chain that never reaches an expansion header', function (): void {
    // Onze parents en chaîne, un de plus que la profondeur explorée.
    foreach (range(500, 510) as $id) {
        seedReferenceFaction($id, 'Maillon '.$id, parentFactionId: $id + 1);
    }

    seedReferenceFaction(511, 'Sommet sans extension', parentFactionId: 0);

    expect(factionReference()->expansions())->toBe([]);
});

// ─── Socle vide ─────────────────────────────────────────────

test('it returns empty maps when the reference tables are empty', function (): void {
    $factionReference = factionReference();

    expect($factionReference->expansions())->toBe([])
        ->and($factionReference->names())->toBe([])
        ->and($factionReference->maxRenownLevels())->toBe([])
        ->and($factionReference->accountWideIds())->toBe([])
        ->and($factionReference->factions())->toBe([]);
});
