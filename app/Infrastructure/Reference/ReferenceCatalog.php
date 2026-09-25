<?php

declare(strict_types=1);

namespace App\Infrastructure\Reference;

/**
 * Les tables DB2 que l'API Blizzard n'expose pas, et les seules colonnes qu'on en tire.
 *
 * Les noms de colonnes sources sont ceux du build courant et changent d'un patch à
 * l'autre : Blizzard a par exemple scindé les masques de race en deux moitiés le jour
 * où les identifiants ont dépassé la largeur d'origine. Un renommage fait échouer la
 * synchronisation, ce qui est le comportement recherché.
 */
final class ReferenceCatalog
{
    /**
     * @return list<ReferenceTable>
     */
    public function tables(): array
    {
        return [
            new ReferenceTable('Faction', 'faction', 'frFR', [
                new ReferenceColumn('ID', 'id', ReferenceColumnType::Integer),
                new ReferenceColumn('Name_lang', 'name_lang', ReferenceColumnType::Text),
                new ReferenceColumn('ReputationIndex', 'reputation_index', ReferenceColumnType::Integer),
                new ReferenceColumn('ParentFactionID', 'parent_faction_id', ReferenceColumnType::Integer),
                new ReferenceColumn('Expansion', 'expansion', ReferenceColumnType::Integer),
                new ReferenceColumn('FriendshipRepID', 'friendship_rep_id', ReferenceColumnType::Integer),
                new ReferenceColumn('RenownCurrencyID', 'renown_currency_id', ReferenceColumnType::Integer),
                new ReferenceColumn('ReputationMax_0', 'reputation_max_0', ReferenceColumnType::Integer),
                new ReferenceColumn('ReputationMax_1', 'reputation_max_1', ReferenceColumnType::Integer),
                new ReferenceColumn('ReputationRaceMasks0_0', 'reputation_race_masks0_0', ReferenceColumnType::BigInteger),
                new ReferenceColumn('ReputationRaceMasks0_1', 'reputation_race_masks0_1', ReferenceColumnType::BigInteger),
            ]),
            new ReferenceTable('ContentTuning', 'content_tuning', null, [
                new ReferenceColumn('ID', 'id', ReferenceColumnType::Integer),
                new ReferenceColumn('ExpansionID', 'expansion_id', ReferenceColumnType::Integer),
            ]),
            new ReferenceTable('AreaTable', 'area_table', 'frFR', [
                new ReferenceColumn('ID', 'id', ReferenceColumnType::Integer),
                new ReferenceColumn('AreaName_lang', 'area_name_lang', ReferenceColumnType::Text),
                new ReferenceColumn('FactionGroupMask', 'faction_group_mask', ReferenceColumnType::Integer),
            ]),
            new ReferenceTable('QuestV2CliTask', 'quest_v2_cli_task', 'frFR', [
                new ReferenceColumn('ID', 'id', ReferenceColumnType::Integer),
                new ReferenceColumn('QuestTitle_lang', 'quest_title_lang', ReferenceColumnType::Text),
                new ReferenceColumn('ContentTuningID', 'content_tuning_id', ReferenceColumnType::Integer),
                new ReferenceColumn('FiltRaceMasks_0', 'filt_race_masks_0', ReferenceColumnType::BigInteger),
                new ReferenceColumn('FiltRaceMasks_1', 'filt_race_masks_1', ReferenceColumnType::BigInteger),
            ]),
            new ReferenceTable('SkillLineAbility', 'skill_line_ability', null, [
                new ReferenceColumn('ID', 'id', ReferenceColumnType::Integer),
                new ReferenceColumn('RaceMasks_0', 'race_masks_0', ReferenceColumnType::BigInteger),
                new ReferenceColumn('RaceMasks_1', 'race_masks_1', ReferenceColumnType::BigInteger),
            ]),
            new ReferenceTable('CurrencyTypes', 'currency_types', 'frFR', [
                new ReferenceColumn('ID', 'id', ReferenceColumnType::Integer),
                new ReferenceColumn('Name_lang', 'name_lang', ReferenceColumnType::Text),
                new ReferenceColumn('MaxQty', 'max_qty', ReferenceColumnType::Integer),
            ]),
            new ReferenceTable('Mount', 'mount', null, [
                new ReferenceColumn('ID', 'id', ReferenceColumnType::Integer),
                new ReferenceColumn('SourceSpellID', 'source_spell_id', ReferenceColumnType::Integer),
            ]),
            new ReferenceTable('SpellMisc', 'spell_misc', null, [
                new ReferenceColumn('ID', 'id', ReferenceColumnType::Integer),
                new ReferenceColumn('SpellID', 'spell_id', ReferenceColumnType::Integer),
                new ReferenceColumn('SpellIconFileDataID', 'spell_icon_file_data_id', ReferenceColumnType::Integer),
            ]),
        ];
    }

    public function find(string $source): ?ReferenceTable
    {
        foreach ($this->tables() as $referenceTable) {
            if ($referenceTable->source === $source) {
                return $referenceTable;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function sources(): array
    {
        return array_map(static fn (ReferenceTable $referenceTable): string => $referenceTable->source, $this->tables());
    }
}
