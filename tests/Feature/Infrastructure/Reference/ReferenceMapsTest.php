<?php

declare(strict_types=1);

use App\Infrastructure\Reference\ReferenceMaps;
use Illuminate\Support\Facades\DB;

/** Les deux moitiés du masque Alliance complet, telles que le socle les porte. */
const MAPS_ALLIANCE = [-1321907123, 1427461461];

/** Les deux moitiés du masque Horde complet. */
const MAPS_HORDE = [1309324210, -1440044374];

/**
 * @param  array{0: int|null, 1: int|null}|null  $raceMask
 */
function seedReferenceQuest(int $id, string $title = 'Une quête', ?int $contentTuningId = null, ?array $raceMask = null): void
{
    DB::table('wow_ref_quest_v2_cli_task')->insert([
        'id' => $id,
        'quest_title_lang' => $title,
        'content_tuning_id' => $contentTuningId,
        'filt_race_masks_0' => $raceMask[0] ?? null,
        'filt_race_masks_1' => $raceMask[1] ?? null,
    ]);
}

function seedReferenceContentTuning(int $id, ?int $expansionId): void
{
    DB::table('wow_ref_content_tuning')->insert(['id' => $id, 'expansion_id' => $expansionId]);
}

/**
 * @param  array{0: int|null, 1: int|null}|null  $raceMask
 */
function seedReferenceSkillLineAbility(int $id, ?array $raceMask = null): void
{
    DB::table('wow_ref_skill_line_ability')->insert([
        'id' => $id,
        'race_masks_0' => $raceMask[0] ?? null,
        'race_masks_1' => $raceMask[1] ?? null,
    ]);
}

function seedReferenceArea(int $id, int $factionGroupMask, string $name = 'Une zone'): void
{
    DB::table('wow_ref_area_table')->insert([
        'id' => $id,
        'area_name_lang' => $name,
        'faction_group_mask' => $factionGroupMask,
    ]);
}

function referenceMaps(): ReferenceMaps
{
    return resolve(ReferenceMaps::class);
}

// ─── Extension des quêtes ───────────────────────────────────

test('it reads the expansion of a quest through its content tuning', function (): void {
    seedReferenceContentTuning(900, 11);
    seedReferenceQuest(42, contentTuningId: 900);

    expect(referenceMaps()->questExpansions())->toBe([42 => 11]);
});

test('it places a quest in Classic when its content tuning says so', function (): void {
    seedReferenceContentTuning(900, 0);
    seedReferenceQuest(42, contentTuningId: 900);

    expect(referenceMaps()->questExpansions())->toBe([42 => 0]);
});

test('it leaves out a quest whose content tuning is unknown', function (): void {
    seedReferenceQuest(42, contentTuningId: 900);

    expect(referenceMaps()->questExpansions())->toBe([]);
});

test('it leaves out a quest carrying no content tuning at all', function (): void {
    seedReferenceQuest(42, contentTuningId: null);

    expect(referenceMaps()->questExpansions())->toBe([]);
});

test('it leaves out a quest with no title, as the CSV pass did', function (): void {
    seedReferenceContentTuning(900, 11);
    seedReferenceQuest(42, title: '', contentTuningId: 900);

    expect(referenceMaps()->questExpansions())->toBe([]);
});

// ─── Faction des quêtes ─────────────────────────────────────

test('it reads the faction of a quest from both halves of its race mask', function (): void {
    seedReferenceQuest(1, raceMask: MAPS_ALLIANCE);
    seedReferenceQuest(2, raceMask: MAPS_HORDE);

    expect(referenceMaps()->questFactions())->toBe([1 => 'Alliance', 2 => 'Horde']);
});

test('it never reads a quest identifier as if it were a race mask', function (): void {
    // 1 lu comme masque vaut le bit de l'humain, donc « Alliance » : c'est
    // exactement ce que produisait la colonne `ID` lue à la place du masque.
    seedReferenceQuest(1, raceMask: [null, null]);

    expect(referenceMaps()->questFactions())->toBe([]);
});

test('it leaves a quest open to both factions untagged', function (): void {
    seedReferenceQuest(1, raceMask: [-1, -1]);

    expect(referenceMaps()->questFactions())->toBe([]);
});

test('it leaves out a quest with no title when reading factions too', function (): void {
    seedReferenceQuest(1, title: '', raceMask: MAPS_ALLIANCE);

    expect(referenceMaps()->questFactions())->toBe([]);
});

// ─── Faction des recettes ───────────────────────────────────

test('it reads the faction of a recipe from both halves of its race mask', function (): void {
    seedReferenceSkillLineAbility(10, MAPS_ALLIANCE);
    seedReferenceSkillLineAbility(20, MAPS_HORDE);

    expect(referenceMaps()->recipeFactions())->toBe([10 => 'Alliance', 20 => 'Horde']);
});

test('it leaves a recipe open to everyone untagged', function (): void {
    seedReferenceSkillLineAbility(10, [0, 0]);
    seedReferenceSkillLineAbility(20, [-1, -1]);

    expect(referenceMaps()->recipeFactions())->toBe([]);
});

test('it keys recipe factions by skill line ability, which is the recipe identifier the API returns', function (): void {
    seedReferenceSkillLineAbility(58288, MAPS_HORDE);

    expect(referenceMaps()->recipeFactions())->toHaveKey(58288);
});

// ─── Faction des zones ──────────────────────────────────────

test('it reads the faction of a zone from its faction group mask', function (): void {
    seedReferenceArea(1, 2);
    seedReferenceArea(2, 4);

    expect(referenceMaps()->zoneFactions())->toBe([1 => 'Alliance', 2 => 'Horde']);
});

test('it leaves a neutral or contested zone untagged', function (int $factionGroupMask): void {
    seedReferenceArea(1, $factionGroupMask);

    expect(referenceMaps()->zoneFactions())->toBe([]);
})->with([
    'neutral' => [0],
    'contested' => [6],
]);

// ─── Socle vide ─────────────────────────────────────────────

test('it returns empty maps when the reference tables are empty', function (): void {
    $referenceMaps = referenceMaps();

    expect($referenceMaps->questExpansions())->toBe([])
        ->and($referenceMaps->questFactions())->toBe([])
        ->and($referenceMaps->recipeFactions())->toBe([])
        ->and($referenceMaps->zoneFactions())->toBe([]);
});

function seedReferenceMount(int $id, ?int $sourceSpellId): void
{
    DB::table('wow_ref_mount')->insert(['id' => $id, 'source_spell_id' => $sourceSpellId]);
}

function seedReferenceSpellMisc(int $id, ?int $spellId, ?int $iconFileDataId): void
{
    DB::table('wow_ref_spell_misc')->insert([
        'id' => $id,
        'spell_id' => $spellId,
        'spell_icon_file_data_id' => $iconFileDataId,
    ]);
}

test('it reads the source spell of a mount', function (): void {
    seedReferenceMount(6, 458);
    seedReferenceMount(14, 580);

    expect((new ReferenceMaps)->mountSpells())->toBe([6 => 458, 14 => 580]);
});

test('it leaves out a mount without a source spell rather than mapping it to zero', function (): void {
    seedReferenceMount(6, 458);
    seedReferenceMount(9, null);

    expect((new ReferenceMaps)->mountSpells())->toBe([6 => 458]);
});

test('it builds the mount icon from the icon file of its source spell', function (): void {
    seedReferenceMount(6, 458);
    seedReferenceSpellMisc(1, 458, 132261);

    expect((new ReferenceMaps)->mountIcons())
        ->toBe([6 => 'https://render.worldofwarcraft.com/eu/icons/56/132261.jpg']);
});

test('it leaves out a mount whose source spell carries no icon file', function (): void {
    seedReferenceMount(6, 458);
    seedReferenceMount(9, 470);
    seedReferenceSpellMisc(1, 458, 132261);
    seedReferenceSpellMisc(2, 470, null);

    expect((new ReferenceMaps)->mountIcons())->toHaveCount(1)
        ->and((new ReferenceMaps)->mountIcons())->toHaveKey(6);
});

test('it picks the lowest spell misc row when a spell carries several', function (): void {
    seedReferenceMount(6, 458);
    seedReferenceSpellMisc(7, 458, 222222);
    seedReferenceSpellMisc(2, 458, 132261);

    expect((new ReferenceMaps)->mountIcons())
        ->toBe([6 => 'https://render.worldofwarcraft.com/eu/icons/56/132261.jpg']);
});
